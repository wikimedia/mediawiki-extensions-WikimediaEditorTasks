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

use MediaWiki\Revision\RevisionRecord;
use WebRequest;

/**
 * Base counter class containing most of the logic for interacting with the DAO.  Subclasses must
 * implement onEditSuccess and onRevert methods containing any custom filtering logic according to
 * the counts they are meant to maintain (e.g., in-app description edits).  It is expected that
 * subclasses will call this class' DAO interaction methods (e.g., incrementForLang) after any
 * required filtering.
 */
abstract class Counter {

	/** @var CounterDao */
	private $dao;

	/** @var int */
	private $keyId;

	/** @var bool */
	private $editStreaksEnabled;

	/** @var bool */
	private $revertCountsEnabled;

	/**
	 * @param int $keyId edit counter key ID
	 * @param CounterDao $dao
	 * @param bool $editStreaksEnabled
	 * @param bool $revertCountsEnabled
	 */
	public function __construct(
		int $keyId,
		CounterDao $dao,
		bool $editStreaksEnabled,
		bool $revertCountsEnabled
	) {
		$this->keyId = $keyId;
		$this->dao = $dao;
		$this->editStreaksEnabled = $editStreaksEnabled;
		$this->revertCountsEnabled = $revertCountsEnabled;
	}

	/**
	 * Specifies the action to take when a successful edit is made.
	 * E.g., increment a counter if the edit is an in-app Wikidata description edit.
	 * @param int $centralId central ID user who edited
	 * @param WebRequest $request the request object
	 * @param RevisionRecord $revision revision representing the successful edit
	 */
	abstract public function onEditSuccess( int $centralId, WebRequest $request,
		RevisionRecord $revision ): void;

	/**
	 * Specifies the action to take when a revert is performed.
	 * E.g., increment revert counter.
	 * Note: this is currently called specifically in response to undo and rollback actions,
	 * although in principle this class is agnostic with respect to the definition of "revert"
	 * used.
	 * @param int $centralId central ID of the user who was reverted
	 * @param int $revisionId revision ID of the reverted edit
	 * @param RevisionRecord $revision RevisionRecord corresponding with $revisionID
	 */
	abstract public function onRevert(
		int $centralId,
		int $revisionId,
		RevisionRecord $revision
	): void;

	/** @return bool */
	protected function isRevertCountingEnabled(): bool {
		return $this->revertCountsEnabled;
	}

	/**
	 * Get count for lang for user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 * @return int|bool value of counter, or false if row does not exist
	 */
	protected function getEditCountForLang( $centralId, $lang ) {
		$count = $this->dao->getEditCountForKeyAndLang( $centralId, $this->keyId, $lang );
		if ( $count ) {
			return (int)$count;
		}
		return false;
	}

	/**
	 * Set count for lang for user
	 * @param int $centralId
	 * @param string $lang language code
	 * @param int $count value to set
	 * @return bool true if no exception was thrown
	 */
	protected function setEditCountForLang( $centralId, $lang, $count ) {
		return $this->dao->setEditCountForKeyAndLang( $centralId, $this->keyId, $lang, $count );
	}

	/**
	 * Increment count for lang and user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 */
	protected function incrementEditCountForLang( $centralId, $lang ) {
		if ( $this->getEditCountForLang( $centralId, $lang ) ) {
			$this->dao->incrementEditCountForKeyAndLang( $centralId, $this->keyId, $lang );
		} else {
			$this->setEditCountForLang( $centralId, $lang, 1 );
		}
	}

	/**
	 * Decrement count for user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 */
	protected function decrementEditCountForLang( $centralId, $lang ) {
		$this->dao->decrementEditCountForKeyAndLang( $centralId, $this->keyId, $lang );
	}

	/**
	 * Reset count for user
	 * @param int $centralId central ID of the user
	 */
	protected function reset( $centralId ) {
		$this->dao->deleteAllCountsForKey( $centralId, $this->keyId );
	}

	/**
	 * Update the edit streak length and last edit time for user
	 * @param int $centralId central ID of the user
	 */
	protected function updateEditStreak( $centralId ) {
		if ( !$this->editStreaksEnabled ) {
			return;
		}
		$this->dao->setEditStreak( $centralId );
	}

	/**
	 * Get the edit streak length and last edit time for user
	 * @param int $centralId central ID of the user
	 * @return array[]|false An array contains current streak length and last edit time
	 */
	protected function getEditStreak( $centralId ) {
		if ( !$this->editStreaksEnabled ) {
			return false;
		}
		return $this->dao->getEditStreak( $centralId );
	}

	/** Increment revert count for lang and user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 */
	protected function incrementRevertCountForLang( $centralId, $lang ) {
		$this->dao->incrementRevertCountForKeyAndLang( $centralId, $this->keyId, $lang );
	}

	/**
	 * Get revert count for lang for user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 * @return int|bool value of revert counter, or false if row does not exist
	 */
	protected function getRevertCountForLang( $centralId, $lang ) {
		$count = $this->dao->getRevertCountForKeyAndLang( $centralId, $this->keyId, $lang );
		if ( $count ) {
			return (int)$count;
		}
		return false;
	}
}
