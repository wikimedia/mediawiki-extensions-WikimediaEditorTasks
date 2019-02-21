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

class SuggestionsDao {

	/** @var DBConnRef */
	private $dbr;

	/**
	 * CounterDao constructor.
	 * @param DBConnRef $dbr handle to DB_REPLICA for reads
	 */
	public function __construct( $dbr ) {
		$this->dbr = $dbr;
	}

	/**
	 * Get a set of random Wikibase entities with a linked Wikipedia article but not a description
	 * in the requested language.
	 * @param string $lang language code
	 * @param int $limit number of results desired (to be used in the query as LIMIT $limit)
	 * @return string[] set of random Wikibase entries meeting the criteria
	 */
	public function getMissingDescriptionSuggestions( $lang, $limit ) {
		$rand = $this->getRandomFloat();
		$fname = __METHOD__;

		$cond = [
			'wetede_language' => $lang,
			'wetede_description_exists' => 0,
			'wetede_rand > ' . strval( $rand ),
		];

		$select = function ( $cond ) use ( $fname, $limit ) {
			return $this->dbr->selectFieldValues(
				'wikimedia_editor_tasks_entity_description_exists',
				'wetede_entity_id',
				$cond,
				$fname,
				[
					'ORDER BY' => 'wetede_rand',
					'LIMIT' => $limit,
				]
			);
		};

		$result = $select( $cond );

		if ( count( $result ) < $limit ) {
			$cond[0] = str_replace( '>', '<=', $cond[0] );
			$result = array_merge( $result, $select( $cond ) );
		}

		return $result;
	}

	/**
	 * Get a set of random Wikibase entities in Wikidata with a description in $sourceLang but not
	 * $targetLang (where Wikipedia articles corresponding to the entity exist in both languages)
	 * @param string $sourceLang language in which a description exists
	 * @param string $targetLang language in which a description does not exist
	 * @param int $limit number of results desired (to be used in the query as LIMIT $limit)
	 * @return string[] set of random Wikibase entries meeting the criteria
	 */
	public function getDescriptionTranslationSuggestions( $sourceLang, $targetLang, $limit ) {
		$rand = $this->getRandomFloat();
		$fname = __METHOD__;

		$cond = [
			'source.wetede_description_exists' => 1,
			'target.wetede_description_exists' => 0,
			'source.wetede_rand > ' . strval( $rand ),
		];

		$select = function ( $cond ) use ( $sourceLang, $targetLang, $fname, $limit ) {
			return $this->dbr->selectFieldValues(
				[
					'source' => 'wikimedia_editor_tasks_entity_description_exists',
					'target' => 'wikimedia_editor_tasks_entity_description_exists',
				],
				'source.wetede_entity_id',
				$cond,
				$fname,
				[
					'ORDER BY' => 'source.wetede_rand',
					'LIMIT' => $limit,
				],
				[
					'target' => [
						'INNER JOIN',
						[
							'source.wetede_entity_id=target.wetede_entity_id',
							'source.wetede_language' => $sourceLang,
							'target.wetede_language' => $targetLang,
						]
					],
				] );
		};

		$result = $select( $cond );

		if ( count( $result ) < $limit ) {
			$cond[0] = str_replace( '>', '<=', $cond[0] );
			$result = array_merge( $result, $select( $cond ) );
		}

		return $result;
	}

	private function getRandomFloat() {
		return mt_rand( 0, mt_getrandmax() - 1 ) / mt_getrandmax();
	}

}
