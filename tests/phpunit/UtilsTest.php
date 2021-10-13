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

namespace MediaWiki\Extension\WikimediaEditorTasks\Test;

use MediaWiki\Extension\WikimediaEditorTasks\Utils;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\DBConnRef;

/**
 * @covers \MediaWiki\Extension\WikimediaEditorTasks\Utils
 */
class UtilsTest extends MediaWikiIntegrationTestCase {

	public function testGetUserCountsDB() {
		$services = MediaWikiServices::getInstance();
		$dbw = Utils::getUserCountsDB( DB_PRIMARY, $services );
		$dbr = Utils::getUserCountsDB( DB_REPLICA, $services );
		$this->assertInstanceOf( DBConnRef::class, $dbw );
		$this->assertInstanceOf( DBConnRef::class, $dbr );
	}

}
