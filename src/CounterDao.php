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

use Wikimedia\Rdbms\DBConnRef;

class CounterDao {

	/** @var DBConnRef */
	private $dbw;

	/** @var DBConnRef */
	private $dbr;

	/**
	 * CounterDao constructor.
	 * @param DBConnRef $dbw handle to DB_MASTER for writes
	 * @param DBConnRef $dbr handle to DB_REPLICA for reads
	 */
	public function __construct( $dbw, $dbr ) {
		$this->dbw = $dbw;
		$this->dbr = $dbr;
	}

	/**
	 * Get all stored counts by lang for the user.
	 * @param int $centralId central user ID
	 * @return array[] All counts in the form:
	 * [
	 * 	<counter key> => [
	 * 		<language code> => <count>,
	 * 		...
	 * 	],
	 * 	...
	 * ]
	 */
	public function getAllCounts( $centralId ) {
		$wrapper = $this->dbr->select(
			[ 'wikimedia_editor_tasks_counts', 'wikimedia_editor_tasks_keys' ],
			[ 'wet_key', 'wetc_lang', 'wetc_count' ],
			[ 'wetc_user' => $centralId ],
			__METHOD__,
			[],
			[
				'wikimedia_editor_tasks_keys' => [
					'LEFT JOIN',
					'wet_id=wetc_key_id',
				],
			]
		);
		$result = [];
		foreach ( $wrapper as $row ) {
			$result[$row->wet_key][$row->wetc_lang] = (int)$row->wetc_count;
		}
		return $result;
	}

	/**
	 * Get all counts by lang for a specific key for a user.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @return array counts for all langs for the specified key
	 */
	public function getAllCountsForKey( $centralId, $keyId ) {
		$wrapper = $this->dbr->select(
			'wikimedia_editor_tasks_counts',
			[ 'wetc_lang', 'wetc_count' ],
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
			],
			__METHOD__
		);
		$result = [];
		foreach ( $wrapper as $row ) {
			$result[$row->wetc_lang] = (int)$row->wetc_count;
		}
		return $result;
	}

	/**
	 * Get a single count by key and lang for a user.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @param string $lang language code
	 * @return int count for the specified key (returns 0 if not found)
	 */
	public function getCountForKeyAndLang( $centralId, $keyId, $lang ) {
		return (int)$this->dbr->selectField(
			'wikimedia_editor_tasks_counts',
			'wetc_count',
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			],
			__METHOD__
		);
	}

	/**
	 * Delete counts for all languages for a single key and user.
	 * @param int $centralId central user ID
	 * @param int $keyId ID for counter key
	 * @return bool true if no exception was thrown
	 */
	public function deleteAllCountsForKey( $centralId, $keyId ) {
		return $this->dbw->delete(
			'wikimedia_editor_tasks_counts',
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
			],
			__METHOD__
		);
	}

	/**
	 * Increment a user's count for a key and language.
	 * @param int $centralId central user ID
	 * @param int $keyId
	 * @param string $lang language code for this count
	 * @return bool true if no exception was thrown
	 */
	public function incrementCountForKeyAndLang( $centralId, $keyId, $lang ) {
		return $this->dbw->update(
			'wikimedia_editor_tasks_counts',
			[ 'wetc_count = wetc_count + 1' ],
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			],
			__METHOD__
		);
	}

	/**
	 * Decrement a user's count for a key and language.
	 * @param int $centralId central user ID
	 * @param int $keyId
	 * @param string $lang language code for this count
	 * @return bool true if no exception was thrown
	 */
	public function decrementCountForKeyAndLang( $centralId, $keyId, $lang ) {
		return $this->dbw->update(
			'wikimedia_editor_tasks_counts',
			[ 'wetc_count = wetc_count - 1' ],
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
				'wetc_count > 0'
			],
			__METHOD__
		);
	}

	/**
	 * Set the count for a given counter.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @param string $lang language code for this count
	 * @param int $count new count
	 * @return bool true if no exception was thrown
	 */
	public function setCountForKeyAndLang( $centralId, $keyId, $lang, $count ) {
		return $this->dbw->upsert(
			'wikimedia_editor_tasks_counts',
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
				'wetc_count' => $count,
			],
			[ [ 'wetc_user', 'wetc_key_id', 'wetc_lang' ] ],
			[ 'wetc_count = wetc_count + ' . (int)$count ],
			__METHOD__
		);
	}

	/**
	 * Get all target counts passed for each counter for which a target has been passed.
	 * @param int $centralId central user ID
	 * @return string[] list of counters with target met
	 */
	public function getAllTargetsPassed( $centralId ) {
		$wrapper = $this->dbr->select(
			[ 'wikimedia_editor_tasks_targets_passed', 'wikimedia_editor_tasks_keys' ],
			[ 'wet_key', 'wettp_count' ],
			[
				'wettp_user' => $centralId,
				'wettp_effective_time <= ' . $this->dbr->addQuotes( $this->dbr->timestamp() )
			],
			__METHOD__,
			[],
			[
				'wikimedia_editor_tasks_keys' => [
					'LEFT JOIN',
					'wet_id=wettp_key_id',
				],
			]
		);
		$result = [];
		foreach ( $wrapper as $row ) {
			$result[$row->wet_key][] = (int)$row->wettp_count;
		}
		return $result;
	}

	/**
	 * Get whether the user has passed the target count for a single counter.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @param int|null $count target count to check, or omit to return whether any target count is
	 * passed
	 * @return bool true if the user passed the specified target, or any target if $count is passed
	 * a null value
	 */
	public function getTargetPassed( $centralId, $keyId, $count = null ) {
		$where = [
			'wettp_user' => $centralId,
			'wettp_key_id' => $keyId,
			'wettp_effective_time <= ' . $this->dbr->addQuotes( $this->dbr->timestamp() )
		];
		if ( $count && is_int( $count ) ) {
			$where['wettp_count'] = $count;
		}
		return (bool)$this->dbr->selectField(
			'wikimedia_editor_tasks_targets_passed',
			'1',
			$where,
			__METHOD__
		);
	}

	/**
	 * Get whether the user has a pending target passed flag for a single counter.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @param int|null $count target count to check, or omit to see if any target passed flag is
	 * pending
	 * @return bool true if there is a target passed flag pending
	 */
	public function getPendingTargetPassed( $centralId, $keyId, $count = null ) {
		$where = [
			'wettp_user' => $centralId,
			'wettp_key_id' => $keyId,
			'wettp_effective_time > ' . $this->dbr->addQuotes( $this->dbr->timestamp() )
		];
		if ( $count && is_int( $count ) ) {
			$where['wettp_count'] = $count;
		}
		return (bool)$this->dbr->selectField(
			'wikimedia_editor_tasks_targets_passed',
			'1',
			$where,
			__METHOD__
		);
	}

	/**
	 * Add one or more rows to the targets_passed table to mark target(s) passed
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @param int[] $targetCounts array of target counts for which to update the table, if needed
	 * @param int $delay time, in seconds, to delay the effects of passing the target from taking
	 * 	effect
	 * @return bool true if no exception was thrown
	 */
	public function updateTargetsPassed( $centralId, $keyId, array $targetCounts, $delay = 0 ) {
		$ts = $this->dbw->timestamp( time() + $delay );
		return $this->dbw->insert(
			'wikimedia_editor_tasks_targets_passed',
			array_map( function ( $count ) use ( $centralId, $keyId, $ts ) {
				return [
					'wettp_user' => $centralId,
					'wettp_key_id' => $keyId,
					'wettp_count' => $count,
					'wettp_effective_time' => $ts,
				];
			}, $targetCounts ),
			__METHOD__,
			[ 'IGNORE' ]
		);
	}

	/**
	 * Delete any records of the user having passed a target where the delay period (if any) has not
	 * yet passed. This is needed when the user is reverted during the waiting period (delay) of
	 * having passed a counter.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @return bool true if no exception was thrown
	 */
	public function deletePendingTargetsPassed( $centralId, $keyId ) {
		return $this->dbw->delete(
			'wikimedia_editor_tasks_targets_passed',
			[
				'wettp_user' => $centralId,
				'wettp_key_id' => $keyId,
				'wettp_effective_time > ' . $this->dbw->addQuotes( $this->dbw->timestamp() ),
			],
			__METHOD__
		);
	}

}
