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

use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\AutoCommitUpdate;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\RollbackCompleteHook;
use MediaWiki\Page\WikiPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Hooks for WikimediaEditorTasks extension
 */
class Hooks implements
	RollbackCompleteHook,
	PageSaveCompleteHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook
{

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
	public function onPageSaveComplete(
		$wikiPage,
		$userIdentity,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		$undidRevId = $editResult->getUndidRevId();

		$cb = function () use ( $revisionRecord, $userIdentity, $undidRevId, $wikiPage ) {
			if ( $userIdentity->isRegistered() ) {
				self::countersOnEditSuccess( $userIdentity, $revisionRecord );
			}

			if ( $undidRevId ) {
				self::countersOnUndo( $undidRevId, $wikiPage );
			}
		};

		$services = MediaWikiServices::getInstance();
		DeferredUpdates::addUpdate(
			new AutoCommitUpdate(
				Utils::getUserCountsDB( DB_PRIMARY, $services ),
				__METHOD__,
				$cb,
				[ $services->getConnectionProvider()->getPrimaryDatabase() ]
			),
			DeferredUpdates::POSTSEND
		);
	}

	/**
	 * Handler for RollbackComplete hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RollbackComplete
	 *
	 * @param WikiPage $wikiPage The article that was edited
	 * @param UserIdentity $agent The user who did the rollback
	 * @param RevisionRecord $newRev The revision the page was reverted back to
	 * @param RevisionRecord $oldRev The revision of the top edit that was reverted
	 */
	public function onRollbackComplete( $wikiPage, $agent, $newRev, $oldRev ) {
		$cb = function () use ( $wikiPage, $oldRev, $newRev ) {
			$victim = $oldRev->getUser();

			// Ignore anonymous users and null rollbacks
			if ( $victim && $victim->isRegistered() && !$oldRev->hasSameContent( $newRev ) ) {
				$victimCentralId = Utils::getCentralId( $victim );
				$oldId = $oldRev->getId();
				foreach ( self::getCounters() as $counter ) {
					$counter->onRevert(
						$victimCentralId,
						$oldId,
						$oldRev
					);
				}
			}
		};

		$services = MediaWikiServices::getInstance();
		DeferredUpdates::addUpdate(
			new AutoCommitUpdate(
				Utils::getUserCountsDB( DB_PRIMARY, $services ),
				__METHOD__,
				$cb,
				[ $services->getConnectionProvider()->getPrimaryDatabase() ]
			),
			DeferredUpdates::POSTSEND
		);
	}

	/**
	 * @param array &$tags
	 */
	private static function onRegisterTags( array &$tags ) {
		$tags[] = 'apps-suggested-edits';
	}

	/**
	 * @param array &$tags
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 */
	public function onListDefinedTags( &$tags ) {
		self::onRegisterTags( $tags );
	}

	/**
	 * @param array &$tags
	 */
	public function onChangeTagsListActive( &$tags ) {
		self::onRegisterTags( $tags );
	}

	/**
	 * @param UserIdentity $user user who succeeded in editing
	 * @param RevisionRecord $revision revision representing the successful edit
	 */
	private static function countersOnEditSuccess( UserIdentity $user, RevisionRecord $revision ) {
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

		$undidUserIdentity = $undidRev->getUser( RevisionRecord::FOR_PUBLIC );
		if ( !$undidUserIdentity ) {
			return;
		}

		// Check that this isn't a spoofed revert (T59474)
		$undidTitle = Title::newFromLinkTarget( $undidRev->getPageAsLinkTarget() );
		if ( !$undidTitle->equals( $wikiPage->getTitle() ) ) {
			return;
		}

		$undidUserCentralId = Utils::getCentralId( $undidUserIdentity );
		foreach ( self::getCounters() as $counter ) {
			$counter->onRevert( $undidUserCentralId, $undidRevId, $undidRev );
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
