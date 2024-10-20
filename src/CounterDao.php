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

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IReadableDatabase;

class CounterDao {

	/** @var IDatabase */
	private $dbw;

	/** @var IReadableDatabase */
	private $dbr;

	/**
	 * CounterDao constructor.
	 * @param IDatabase $dbw handle to DB_PRIMARY for writes
	 * @param IReadableDatabase $dbr handle to DB_REPLICA for reads
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
		$wrapper = $this->dbr->newSelectQueryBuilder()
			->select( [ 'wet_key', 'wetc_lang', 'wetc_count' ] )
			->from( 'wikimedia_editor_tasks_counts' )
			->leftJoin( 'wikimedia_editor_tasks_keys', null, 'wet_id=wetc_key_id' )
			->where( [ 'wetc_user' => $centralId ] )
			->caller( __METHOD__ )
			->fetchResultSet();
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
		if ( ( $flags & IDBAccessObject::READ_LATEST ) == IDBAccessObject::READ_LATEST ) {
			$db = $this->dbw;
		} else {
			$db = $this->dbr;
		}

		$wrapper = $db->newSelectQueryBuilder()
			->select( [ 'wetc_lang', 'wetc_count' ] )
			->from( 'wikimedia_editor_tasks_counts' )
			->where( [ 'wetc_user' => $centralId, 'wetc_key_id' => $keyId ] )
			->recency( $flags )
			->caller( __METHOD__ )->fetchResultSet();
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
		return (int)$this->dbr->newSelectQueryBuilder()
			->select( 'wetc_count' )
			->from( 'wikimedia_editor_tasks_counts' )
			->where( [
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			] )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * Delete counts for all languages for a single key and user.
	 * @param int $centralId central user ID
	 * @param int $keyId ID for counter key
	 */
	public function deleteAllCountsForKey( $centralId, $keyId ) {
		$this->dbw->newDeleteQueryBuilder()
			->deleteFrom( 'wikimedia_editor_tasks_counts' )
			->where( [
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Increment a user's count for a key and language.
	 * @param int $centralId central user ID
	 * @param int $keyId
	 * @param string $lang language code for this count
	 */
	public function incrementEditCountForKeyAndLang( $centralId, $keyId, $lang ) {
		$this->dbw->newUpdateQueryBuilder()
			->update( 'wikimedia_editor_tasks_counts' )
			->set( [ 'wetc_count = wetc_count + 1' ] )
			->where( [
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Decrement a user's count for a key and language.
	 * @param int $centralId central user ID
	 * @param int $keyId
	 * @param string $lang language code for this count
	 */
	public function decrementEditCountForKeyAndLang( $centralId, $keyId, $lang ) {
		$this->dbw->newUpdateQueryBuilder()
			->update( 'wikimedia_editor_tasks_counts' )
			->set( [ 'wetc_count = wetc_count - 1' ] )
			->where( [
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
				$this->dbw->expr( 'wetc_count', '>', 0 ),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Set the count for a given counter.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @param string $lang language code for this count
	 * @param int $count new count
	 */
	public function setEditCountForKeyAndLang( $centralId, $keyId, $lang, $count ) {
		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'wikimedia_editor_tasks_counts' )
			->row( [
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
				'wetc_count' => $count,
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'wetc_user', 'wetc_key_id', 'wetc_lang' ] )
			->set( [ 'wetc_count = wetc_count + ' . (int)$count ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Get the edit streak length and last edit time for the user
	 * @param int $centralId central user ID
	 * @return array[] An array contains current streak length and last edit time
	 */
	public function getEditStreak( $centralId ) {
		$wrapper = $this->dbr->newSelectQueryBuilder()
			->select( [ 'wetes_streak_length', 'wetes_last_edit_time' ] )
			->from( 'wikimedia_editor_tasks_edit_streak' )
			->where( [ 'wetes_user' => $centralId ] )
			->caller( __METHOD__ )
			->fetchRow();
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
	 */
	public function setEditStreak( $centralId ) {
		$currentTime = $this->dbw->timestamp( time() );
		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'wikimedia_editor_tasks_edit_streak' )
			->row( [
				'wetes_user' => $centralId,
				'wetes_streak_length' => 1,
				'wetes_last_edit_time' => $currentTime,
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( 'wetes_user' )
			->set( [
				'wetes_streak_length = IF (
					DATEDIFF ( ' . $currentTime . ', wetes_last_edit_time ) >= 2,
					1,
					IF (
						DATEDIFF ( ' . $currentTime . ', wetes_last_edit_time ) = 1,
						wetes_streak_length + 1,
						wetes_streak_length
					)
				)',
				'wetes_last_edit_time' => $currentTime,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Increment a user's revert count for a key and language.
	 * @param int $centralId central user ID
	 * @param int $keyId
	 * @param string $lang language code for this count
	 */
	public function incrementRevertCountForKeyAndLang( $centralId, $keyId, $lang ) {
		$this->dbw->newUpdateQueryBuilder()
			->update( 'wikimedia_editor_tasks_counts' )
			->set( [ 'wetc_revert_count = wetc_revert_count + 1' ] )
			->where( [
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			] )
			->caller( __METHOD__ )
			->execute();
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
		$wrapper = $this->dbr->newSelectQueryBuilder()
			->select( [ 'wet_key', 'wetc_lang', 'wetc_revert_count' ] )
			->from( 'wikimedia_editor_tasks_counts' )
			->leftJoin( 'wikimedia_editor_tasks_keys', null, 'wet_id=wetc_key_id' )
			->where( [ 'wetc_user' => $centralId ] )
			->caller( __METHOD__ )
			->fetchResultSet();
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
		return (int)$this->dbr->newSelectQueryBuilder()
			->select( 'wetc_revert_count' )
			->from( 'wikimedia_editor_tasks_counts' )
			->where( [
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			] )
			->caller( __METHOD__ )
			->fetchField();
	}
}
