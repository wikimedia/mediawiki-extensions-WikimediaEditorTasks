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

use DBAccessObjectUtils;
use Wikimedia\Rdbms\DBConnRef;

class CounterDao {

	/** @var DBConnRef */
	private $dbw;

	/** @var DBConnRef */
	private $dbr;

	/**
	 * CounterDao constructor.
	 * @param DBConnRef $dbw handle to DB_PRIMARY for writes
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
	public function getAllEditCounts( $centralId ) {
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
	 * @param int $flags IDBAccessObject flags
	 * @return array counts for all langs for the specified key
	 */
	public function getAllEditCountsForKey( $centralId, $keyId, $flags = 0 ) {
		list( $index, $options ) = DBAccessObjectUtils::getDBOptions( $flags );
		$db = ( $index === DB_PRIMARY ) ? $this->dbw : $this->dbr;

		$wrapper = $db->select(
			'wikimedia_editor_tasks_counts',
			[ 'wetc_lang', 'wetc_count' ],
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
			],
			__METHOD__,
			$options
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
	public function getEditCountForKeyAndLang( $centralId, $keyId, $lang ) {
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
	public function incrementEditCountForKeyAndLang( $centralId, $keyId, $lang ) {
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
	public function decrementEditCountForKeyAndLang( $centralId, $keyId, $lang ) {
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
	public function setEditCountForKeyAndLang( $centralId, $keyId, $lang, $count ) {
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
	 * Get the edit streak length and last edit time for the user
	 * @param int $centralId central user ID
	 * @return array[] An array contains current streak length and last edit time
	 */
	public function getEditStreak( $centralId ) {
		$wrapper = $this->dbr->selectRow(
			[ 'wikimedia_editor_tasks_edit_streak' ],
			[ 'wetes_streak_length', 'wetes_last_edit_time' ],
			[ 'wetes_user' => $centralId ],
			__METHOD__
		);
		$result = [];
		if ( $wrapper != false ) {
			$result['length'] = (int)$wrapper->wetes_streak_length;
			$result['last_edit_time'] = $wrapper->wetes_last_edit_time;
		}
		return $result;
	}

	/**
	 * Set the edit streak for a user.
	 * Increase the edit streak length if the user makes an edit within 2 days.
	 * @param int $centralId central user ID
	 * @return bool true if no exception was thrown
	 */
	public function setEditStreak( $centralId ) {
		$currentTime = $this->dbw->timestamp( time() );
		return $this->dbw->upsert(
			'wikimedia_editor_tasks_edit_streak',
			[
				'wetes_user' => $centralId,
				'wetes_streak_length' => 1,
				'wetes_last_edit_time' => $currentTime,
			],
			'wetes_user',
			[
				'wetes_streak_length = IF (
					DATEDIFF ( ' . $currentTime . ', wetes_last_edit_time ) >= 2,
					1,
					IF (
						DATEDIFF ( ' . $currentTime . ', wetes_last_edit_time ) = 1,
						wetes_streak_length + 1,
						wetes_streak_length
					)
				)',
				'wetes_last_edit_time = ' . $currentTime
			],
			__METHOD__
		);
	}

	/**
	 * Increment a user's revert count for a key and language.
	 * @param int $centralId central user ID
	 * @param int $keyId
	 * @param string $lang language code for this count
	 * @return bool true if no exception was thrown
	 */
	public function incrementRevertCountForKeyAndLang( $centralId, $keyId, $lang ) {
		return $this->dbw->update(
			'wikimedia_editor_tasks_counts',
			[ 'wetc_revert_count = wetc_revert_count + 1' ],
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			],
			__METHOD__
		);
	}

	/**
	 * Get all stored reverts counts by lang for the user.
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
	public function getAllRevertCounts( $centralId ) {
		$wrapper = $this->dbr->select(
			[ 'wikimedia_editor_tasks_counts', 'wikimedia_editor_tasks_keys' ],
			[ 'wet_key', 'wetc_lang', 'wetc_revert_count' ],
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
			$result[$row->wet_key][$row->wetc_lang] = (int)$row->wetc_revert_count;
		}
		return $result;
	}

	/**
	 * Get a single revert count by key and lang for a user.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @param string $lang language code
	 * @return int revert count for the specified key (returns 0 if not found)
	 */
	public function getRevertCountForKeyAndLang( $centralId, $keyId, $lang ) {
		return (int)$this->dbr->selectField(
			'wikimedia_editor_tasks_counts',
			'wetc_revert_count',
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			],
			__METHOD__
		);
	}
}
