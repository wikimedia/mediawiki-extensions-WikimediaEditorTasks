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

use Content;
use DatabaseUpdater;
use DeferredUpdates;
use IDBAccessObject;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use RequestContext;
use Revision;
use Status;
use Title;
use User;
use WikiPage;
use AutoCommitUpdate;

/**
 * Hooks for WikimediaEditorTasks extension
 */
class Hooks {

	/**
	 * Handler for PageContentSaveComplete hook
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 *
	 * @param WikiPage &$wikiPage modified WikiPage
	 * @param User &$user User who edited
	 * @param Content $content New article text
	 * @param string $summary Edit summary
	 * @param bool $minoredit Minor edit or not
	 * @param bool $watchthis Watch this article?
	 * @param string $sectionanchor Section that was edited
	 * @param int &$flags Edit flags
	 * @param Revision $revision Revision that was created
	 * @param Status &$status
	 * @param int $baseRevId
	 * @param int $undidRevId
	 */
	public static function onPageContentSaveComplete(
		&$wikiPage,
		&$user,
		$content,
		$summary,
		$minoredit,
		$watchthis,
		$sectionanchor,
		&$flags,
		$revision,
		Status &$status,
		$baseRevId,
		$undidRevId = 0
	) {
		$cb = function () use ( $revision, $status, $user, $undidRevId, $wikiPage ) {
			if ( $revision && $status->isGood() && $user && $user->isLoggedIn() ) {
				self::countersOnEditSuccess( $user, $revision->getId() );
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
	 * Handler for ArticleRollbackComplete hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleRollbackComplete
	 *
	 * @param WikiPage $wikiPage The article that was edited
	 * @param User $agent The user who did the rollback
	 * @param Revision $newRev The revision the page was reverted back to
	 * @param Revision $oldRev The revision of the top edit that was reverted
	 */
	public static function onArticleRollbackComplete( WikiPage $wikiPage, $agent, $newRev, $oldRev ) {
		$cb = function () use ( $wikiPage, $oldRev, $newRev ) {
			$victimId = $oldRev->getUser();
			if (
				// Ignore anonymous users and null rollbacks
				$victimId && !$oldRev->getContent()->equals( $newRev->getContent() )
			) {
				$victim = User::newFromId( $victimId );
				foreach ( self::getCounters() as $counter ) {
					$counter->onRevert( Utils::getCentralId( $victim ), $oldRev->getId(), $oldRev );
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
	 * @param int $revisionId revisionId of the successful edit
	 */
	private static function countersOnEditSuccess( $user, $revisionId ) {
		$centralId = Utils::getCentralId( $user );

		// We need to check the underlying request headers to determine if this is an app edit
		$request = RequestContext::getMain()->getRequest();

		foreach ( self::getCounters() as $counter ) {
			$counter->onEditSuccess( $centralId, $request, $revisionId );
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

	private static function getCounters() {
		$counterFactory = WikimediaEditorTasksServices::getInstance()->getCounterFactory();
		return $counterFactory->createAll( Utils::getEnabledCounters() );
	}

}
