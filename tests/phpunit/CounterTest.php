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

use MediaWiki\Extension\WikimediaEditorTasks\Counter;
use MediaWiki\Extension\WikimediaEditorTasks\WikimediaEditorTasksServices;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\RevisionStore;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @covers \MediaWiki\Extension\WikimediaEditorTasks\Counter
 * @covers \MediaWiki\Extension\WikimediaEditorTasks\CounterFactory
 */
class CounterTest extends MediaWikiIntegrationTestCase {

	private const LANG = '*';
	private const TEST_EDIT_COUNT = 2;

	/** @var Counter[] */
	private $counters;

	/** @var int */
	private $userId;

	/** @var int */
	private $revId;

	/** @var RevisionStore */
	private $revisionStore;

	public function setUp(): void {
		parent::setUp();
		global $wgWikimediaEditorTasksEnableEditStreaks, $wgWikimediaEditorTasksEnableRevertCounts;
		$wgWikimediaEditorTasksEnableEditStreaks = true;
		$wgWikimediaEditorTasksEnableRevertCounts = true;

		$wikiPage = $this->getExistingTestPage();
		$this->revId = $wikiPage->getId();

		$this->revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$counterFactory = WikimediaEditorTasksServices::getInstance()->getCounterFactory();

		$this->counters = array_map( static function ( $counter ) {
			return TestingAccessWrapper::newFromObject( $counter );
		}, $counterFactory->createAll( [
			[
				"class" => "MediaWiki\\Extension\\WikimediaEditorTasks\\Test\\TestCounter",
				"counter_key" => "test"
			]
		] ) );

		$this->userId = $this::getTestUser()->getUser()->getId();
	}

	public function testInitialState() {
		foreach ( $this->counters as $counter ) {
			$this->assertFalse( $counter->getEditCountForLang( $this->userId, self::LANG ) );
		}
	}

	public function testIncrementDecrement() {
		foreach ( $this->counters as $counter ) {
			$counter->incrementEditCountForLang( $this->userId, self::LANG );
			$this->assertSame( 1, $counter->getEditCountForLang( $this->userId, self::LANG ) );
			$counter->decrementEditCountForLang( $this->userId, self::LANG );
			$this->assertFalse( $counter->getEditCountForLang( $this->userId, self::LANG ) );
		}
	}

	public function testOnEditSuccess() {
		foreach ( $this->counters as $counter ) {
			$counter->onEditSuccess( $this->userId, new WebRequest(),
				$this->revisionStore->getRevisionById( $this->revId ) );
			$this->assertSame( 1, $counter->getEditCountForLang( $this->userId, self::LANG ) );
			$this->assertSame( 1, $counter->getEditStreak( $this->userId )['length'] );
			$this->assertCount( 2, $counter->getEditStreak( $this->userId ) );
		}
	}

	public function testOnRevert() {
		$testCounter = $this->counters[0];

		foreach ( $this->counters as $counter ) {
			for ( $i = 0; $i < self::TEST_EDIT_COUNT; $i++ ) {
				$counter->onEditSuccess( $this->userId, new WebRequest(),
					$this->revisionStore->getRevisionById( $this->revId ) );
			}
			$this->assertEquals( 2, $counter->getEditCountForLang( $this->userId, self::LANG ) );
		}

		foreach ( $this->counters as $counter ) {
			$counter->onRevert( $this->userId, $this->revId,
				$this->revisionStore->getRevisionById( $this->revId ) );
		}
		$this->assertSame( 1, $testCounter->getRevertCountForLang( $this->userId, self::LANG ) );
	}
}
