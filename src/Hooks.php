<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\WikimediaEditorTasks;

use AutoCommitUpdate;
use DatabaseUpdater;
use DeferredUpdates;
use IDBAccessObject;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\USerIdentity;
use RequestContext;
use Title;
use User;
use WikiPage;

/**
 * Hooks for WikimediaEditorTasks extension
 */
class Hooks {

	/**
	 * Handler for PageSaveComplete hook
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		UserIdentity $userIdentity,
		string $summary,
		int $flags,
		RevisionRecord $revisionRecord,
		EditResult $editResult
	) {
		$undidRevId = $editResult->getUndidRevId();

		$cb = function () use ( $revisionRecord, $userIdentity, $undidRevId, $wikiPage ) {
			if ( $userIdentity->isRegistered() ) {
				$user = User::newFromIdentity( $userIdentity );
				self::countersOnEditSuccess( $user, $revisionRecord );
			}

			if ( $undidRevId ) {
				self::countersOnUndo( $undidRevId, $wikiPage );
			}
		};

		$services = MediaWikiServices::getInstance();
		DeferredUpdates::addUpdate(
			new AutoCommitUpdate(
				Utils::getUserCountsDB( DB_MASTER, $services ),
				__METHOD__,
				$cb,
				[ wfGetDB( DB_MASTER ) ]
			),
			DeferredUpdates::POSTSEND
		);
	}

	/**
	 * Handler for RollbackComplete hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RollbackComplete
	 *
	 * @param WikiPage $wikiPage The article that was edited
	 * @param User $agent The user who did the rollback
	 * @param RevisionRecord $newRev The revision the page was reverted back to
	 * @param RevisionRecord $oldRev The revision of the top edit that was reverted
	 */
	public static function onRollbackComplete( WikiPage $wikiPage, $agent, $newRev, $oldRev ) {
		$cb = function () use ( $wikiPage, $oldRev, $newRev ) {
			$victim = $oldRev->getUser();
			$isRegistered = $victim ? $victim->isRegistered() : false;

			// Ignore anonymous users and null rollbacks
			if ( $isRegistered && !$oldRev->hasSameContent( $newRev ) ) {
				// Is getUser returned null, the if condition fails
				// Need the full name because of T250765
				'@phan-var \MediaWiki\User\UserIdentity $victim';
				$victim = User::newFromIdentity( $victim );

				foreach ( self::getCounters() as $counter ) {
					$counter->onRevert(
						Utils::getCentralId( $victim ),
						$oldRev->getId(),
						$oldRev
					);
				}
			}
		};

		$services = MediaWikiServices::getInstance();
		DeferredUpdates::addUpdate(
			new AutoCommitUpdate(
				Utils::getUserCountsDB( DB_MASTER, $services ),
				__METHOD__,
				$cb,
				[ wfGetDB( DB_MASTER ) ]
			),
			DeferredUpdates::POSTSEND
		);
	}

	/**
	 * @param array &$tags
	 * @return bool true
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 */
	public static function onRegisterTags( array &$tags ) {
		$tags[] = 'apps-suggested-edits';
		return true;
	}

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$baseDir = dirname( __DIR__ );
		$updater->addExtensionTable( 'wikimedia_editor_tasks_keys', "$baseDir/sql/keys.sql" );
		$updater->addExtensionTable( 'wikimedia_editor_tasks_counts', "$baseDir/sql/counts.sql" );
		$updater->addExtensionTable( 'wikimedia_editor_tasks_edit_streak',
			"$baseDir/sql/edit_streak.sql" );
		$updater->dropExtensionTable(
			'wikimedia_editor_tasks_entity_description_exists',
			"$baseDir/sql/drop-wikimedia_editor_tasks_entity_description_exists.sql"
		);
		$updater->dropExtensionTable(
			'wikimedia_editor_tasks_targets_passed',
			"$baseDir/sql/drop-wikimedia_editor_tasks_targets_passed.sql"
		);
		$updater->addExtensionField(
			'wikimedia_editor_tasks_counts',
			'wetc_revert_count',
			"$baseDir/sql/alter-wikimedia_editor_tasks_counts.sql"
		);
	}

	/**
	 * @param User $user user who succeeded in editing
	 * @param RevisionRecord $revision revision representing the successful edit
	 */
	private static function countersOnEditSuccess( User $user, RevisionRecord $revision ) {
		$centralId = Utils::getCentralId( $user );

		// We need to check the underlying request headers to determine if this is an app edit
		$request = RequestContext::getMain()->getRequest();

		foreach ( self::getCounters() as $counter ) {
			$counter->onEditSuccess( $centralId, $request, $revision );
		}
	}

	/**
	 * @param int $undidRevId revision that was undone
	 * @param WikiPage $wikiPage wiki page that was just edited
	 */
	private static function countersOnUndo( $undidRevId, $wikiPage ) {
		$undidRev = MediaWikiServices::getInstance()->getRevisionStore()
			->getRevisionById( $undidRevId, IDBAccessObject::READ_LATEST_IMMUTABLE );

		if ( !$undidRev ) {
			return;
		}

		$undidUserId = $undidRev->getUser( RevisionRecord::FOR_PUBLIC )->getId();
		if ( !$undidUserId ) {
			return;
		}

		// Check that this isn't a spoofed revert (T59474)
		$undidTitle = Title::newFromLinkTarget( $undidRev->getPageAsLinkTarget() );
		if ( !$undidTitle->equals( $wikiPage->getTitle() ) ) {
			return;
		}

		$undidUser = User::newFromId( $undidUserId );
		foreach ( self::getCounters() as $counter ) {
			$counter->onRevert( Utils::getCentralId( $undidUser ), $undidRevId, $undidRev );
		}
	}

	/**
	 * @return Counter[]
	 */
	private static function getCounters() {
		$counterFactory = WikimediaEditorTasksServices::getInstance()->getCounterFactory();
		return $counterFactory->createAll( Utils::getEnabledCounters() );
	}

}
