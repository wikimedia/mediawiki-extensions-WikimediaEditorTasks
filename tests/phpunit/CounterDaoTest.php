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

use MediaWiki\Extension\WikimediaEditorTasks\CounterDao;
use MediaWiki\Extension\WikimediaEditorTasks\WikimediaEditorTasksServices;
use MediaWikiTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\WikimediaEditorTasks\CounterDao
 */
class CounterDaoTest extends MediaWikiTestCase {

	const KEY_ID = 0;
	const LANG = 'test';

	/** @var CounterDao */
	private $dao;

	/** @var int */
	private $userId;

	public function setUp() : void {
		parent::setUp();
		$this->tablesUsed = array_merge( $this->tablesUsed, [
			'wikimedia_editor_tasks_keys',
			'wikimedia_editor_tasks_counts',
			'wikimedia_editor_tasks_edit_streak'
		] );
		$this->dao = WikimediaEditorTasksServices::getInstance()->getCounterDao();
		$this->userId = $this::getTestUser()->getUser()->getId();
	}

	public function testEmpty() {
		$this->assertEquals( [], $this->dao->getAllCounts( $this->userId ) );
	}

	public function testCounts() {
		$this->dao->setCountForKeyAndLang( $this->userId, self::KEY_ID, self::LANG, 0 );
		$this->dao->incrementCountForKeyAndLang( $this->userId, self::KEY_ID, self::LANG );
		$this->assertEquals( 1, $this->dao->getCountForKeyAndLang( $this->userId, self::KEY_ID,
			self::LANG ) );
		$this->dao->decrementCountForKeyAndLang( $this->userId, self::KEY_ID, self::LANG );
		$this->assertEquals( 0, $this->dao->getCountForKeyAndLang( $this->userId, self::KEY_ID,
			self::LANG ) );
	}

	public function testEditStreak() {
		$this->dao->setEditStreak( $this->userId );
		$this->assertCount( 2, $this->dao->getEditStreak( $this->userId ) );
		$this->assertEquals( 1, $this->dao->getEditStreak( $this->userId )['length'] );
	}
}
