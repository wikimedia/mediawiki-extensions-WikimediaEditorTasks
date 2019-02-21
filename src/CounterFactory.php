<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See thes
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\WikimediaEditorTasks;

use MediaWiki\Storage\NameTableStore;

/**
 * Factory for creating Counter objects based on the extension configuration.
 * Counters definitions contain three keys:
 * * class (required) - the class implementing the counter
 * * counter_key (required) - string to identify the counter in the DB
 * * target_count (optional) - count required to trigger some behavior
 */
class CounterFactory {

	/** @var CounterDao */
	private $dao;

	/** @var NameTableStore */
	private $nameTableStore;

	/**
	 * @param CounterDao $dao
	 * @param NameTableStore $nameTableStore store for the wikimedia_editor_tasks_keys table
	 */
	public function __construct( CounterDao $dao, NameTableStore $nameTableStore ) {
		$this->dao = $dao;
		$this->nameTableStore = $nameTableStore;
	}

	/**
	 * @param array $config array of counter definitions
	 * @return Counter[] array of Counters
	 */
	public function createAll( $config ) {
		return array_map( function ( $definition ) {
			return $this->create( $definition );
		}, $config );
	}

	/**
	 * @param array $definition counter definition
	 * @return Counter
	 */
	public function create( $definition ) {
		return new $definition['class'](
			$this->nameTableStore->acquireId( $definition['counter_key'] ),
			$definition['target_counts'],
			$definition['delay'],
			$this->dao
		);
	}

}
