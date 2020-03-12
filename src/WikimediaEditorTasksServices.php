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

use Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;

class WikimediaEditorTasksServices {

	/** @var MediaWikiServices */
	private $services;

	/**
	 * @return WikimediaEditorTasksServices
	 */
	public static function getInstance(): WikimediaEditorTasksServices {
		return new self( MediaWikiServices::getInstance() );
	}

	/**
	 * @param MediaWikiServices $services
	 * @return WikimediaEditorTasksServices
	 */
	public static function wrap( MediaWikiServices $services ): WikimediaEditorTasksServices {
		return new self( $services );
	}

	/**
	 * @param MediaWikiServices $services
	 */
	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	/**
	 * @return CounterFactory
	 */
	public function getCounterFactory(): CounterFactory {
		return $this->services->getService( 'WikimediaEditorTasksCounterFactory' );
	}

	/**
	 * @return CounterDao
	 */
	public function getCounterDao(): CounterDao {
		return $this->services->getService( 'WikimediaEditorTasksCounterDao' );
	}

	/**
	 * @return Config
	 */
	public function getExtensionConfig(): Config {
		return $this->services->getService( 'WikimediaEditorTasksConfig' );
	}

	/**
	 * @return NameTableStore
	 */
	public function getNameTableStore(): NameTableStore {
		return $this->services->getService( 'WikimediaEditorTasksNameTableStore' );
	}

}
