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

/**
 * Base counter class containing most of the logic for interacting with the DAO.  Subclasses must
 * implement onEditSuccess and onRevert methods containing any custom filtering logic according to
 * the counts they are meant to maintain (e.g., in-app description edits).  It is expected that
 * subclasses will call this class' DAO interaction methods (e.g., incrementForLang) after any
 * required filtering.
 */
abstract class Counter {

	/** @var Dao */
	private $dao;

	/** @var int */
	private $keyId;

	/** @var int[]|null */
	private $targetCounts;

	/**
	 * @param int $keyId edit counter key ID
	 * @param int|int[]|null $targetCounts target count(s) for the counter (if any)
	 * @param Dao $dao
	 */
	public function __construct( $keyId, $targetCounts, Dao $dao ) {
		$this->keyId = $keyId;
		$this->targetCounts = is_int( $targetCounts ) ? [ $targetCounts ] : $targetCounts;
		$this->dao = $dao;
	}

	/**
	 * Specifies the action to take when a successful edit is made.
	 * E.g., increment a counter if the edit is an in-app Wikidata description edit.
	 * @param int $centralId central ID user who edited
	 */
	abstract public function onEditSuccess( $centralId );

	/**
	 * Specifies the action to take when a revert is performed.
	 * E.g., decrement or reset an editor's counter if the reverted edit is an in-app Wikidata
	 *  description edit.
	 * Note: this is called specifically in response to undo and rollback actions, although in
	 *  principle this class is agnostic with respect to the definition used.
	 * @param int $centralId central ID of the user who was reverted
	 */
	abstract public function onRevert( $centralId );

	/**
	 * Get count for lang for user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 * @return int|bool value of counter, or false if row does not exist
	 */
	protected function getCountForLang( $centralId, $lang ) {
		$count = $this->dao->getCountForKeyAndLang( $centralId, $this->keyId, $lang );
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
	protected function setCountForLang( $centralId, $lang, $count ) {
		return $this->dao->setCountForKeyAndLang( $centralId, $this->keyId, $lang, $count );
	}

	/**
	 * Increment count for lang and user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 */
	protected function incrementForLang( $centralId, $lang ) {
		if ( !$this->getCountForLang( $centralId, $lang ) ) {
			$this->setCountForLang( $centralId, $lang, 0 );
		}

		$this->dao->incrementCountForKeyAndLang( $centralId, $this->keyId, $lang );

		$this->updateTargetsPassed( $centralId );
	}

	/**
	 * Decrement count for user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 */
	protected function decrementForLang( $centralId, $lang ) {
		$this->dao->decrementCountForKeyAndLang( $centralId, $this->keyId, $lang );
	}

	/**
	 * Reset count for user
	 * @param int $centralId central ID of the user
	 */
	protected function reset( $centralId ) {
		$this->dao->deleteAllCountsForKey( $centralId, $this->keyId );
	}

	/**
	 * Mark the target passed for this counter if the total of all per-language counts is greater
	 * than or equal to the target count.
	 * @param int $centralId central ID of this user
	 */
	private function updateTargetsPassed( $centralId ) {
		if ( !$this->targetCounts ) {
			return;
		}
		$counts = array_values( $this->dao->getAllCountsForKey( $centralId, $this->keyId ) );
		$total = array_sum( $counts );
		$targetsPassed = array_filter( $this->targetCounts, function ( $target ) use ( $total ) {
			return $total >= $target;
		} );
		if ( !$targetsPassed ) {
			return;
		}
		$this->dao->updateTargetsPassed( $centralId, $this->keyId, $targetsPassed );
	}
}
