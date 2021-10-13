<?php

namespace MediaWiki\Extension\WikimediaEditorTasks\Test;

use CommentStoreComment;
use FauxRequest;
use MediaWiki\Extension\WikimediaEditorTasks\CounterDao;
use MediaWiki\Extension\WikimediaEditorTasks\Utils;
use MediaWiki\Extension\WikimediaEditorTasks\WikimediaEditorTasksServices;
use MediaWiki\Extension\WikimediaEditorTasks\WikipediaAppCounter;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionSlots;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\RevisionStoreRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWikiIntegrationTestCase;
use TextContent;
use Title;
use User;
use WebRequest;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @covers \MediaWiki\Extension\WikimediaEditorTasks\WikipediaAppCounter
 */
class WikipediaAppCounterTest extends MediaWikiIntegrationTestCase {

	private const DESCRIPTION_COMMENT =
		'/* wbsetdescription-add:1|zh */ 韓國高速鐵道, #suggestededit-add 1.0';
	private const CAPTION_COMMENT = '/* wbsetlabel-add:1|zh-hant */ 韓國高速鐵道, #suggestededit-add 1.0';
	private const DEPICTS_COMMENT = '/* wbeditentity-update:0| */ /* add-depicts: Q123|Test */';
	private const DEPICTS_COMMENT_OLD = '/* wbsetclaim-create:2||1 */ [[d:Special:EntityPage/P180]]: ' .
	'[[d:Special:EntityPage/Q42]], #suggestededit-add 1.0';

	/** @var User */
	private $user;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var WikipediaAppCounter[] */
	private $counters;

	/** @var CounterDao */
	private $counterDao;

	public function setUp(): void {
		parent::setUp();
		global $wgWikimediaEditorTasksEnableEditStreaks, $wgWikimediaEditorTasksEnableRevertCounts;
		$wgWikimediaEditorTasksEnableEditStreaks = true;
		$wgWikimediaEditorTasksEnableRevertCounts = true;

		$this->tablesUsed = array_merge( $this->tablesUsed, [
			'wikimedia_editor_tasks_keys',
			'wikimedia_editor_tasks_counts',
			'wikimedia_editor_tasks_edit_streak'
		] );

		$this->revisionStore = MediaWikiServices::getInstance()->getRevisionStore();

		$extensionServices = WikimediaEditorTasksServices::getInstance();
		$this->counterDao = $extensionServices->getCounterDao();

		$counterFactory = $extensionServices->getCounterFactory();
		$this->counters = array_map( static function ( $counter ) {
			return TestingAccessWrapper::newFromObject( $counter );
		}, $counterFactory->createAll( [
			[
				"class" => "MediaWiki\\Extension\\WikimediaEditorTasks\\WikipediaAppDescriptionEditCounter",
				"counter_key" => "app_description_edits"
			],
			[
				"class" => "MediaWiki\\Extension\\WikimediaEditorTasks\\WikipediaAppCaptionEditCounter",
				"counter_key" => "app_caption_edits"
			],
			[
				"class" => "MediaWiki\\Extension\\WikimediaEditorTasks\\WikipediaAppImageDepictsEditCounter",
				"counter_key" => "app_depicts_edits"
			],
		] ) );

		$this->user = $this::getTestUser()->getUser();
	}

	public function testIncrementDescriptionEditCount() {
		$id = Utils::getCentralId( $this->user );
		$request = $this->getRequest( 'wbsetdescription' );
		$revision = $this->getRevision( self::DESCRIPTION_COMMENT );
		foreach ( $this->counters as $counter ) {
			$counter->conditionallyIncrementEditCount( $id, $request, $revision );
		}
		$this->assertSame( 1, $this->getEditCountForCounter( $id, 'app_description_edits' ) );
		foreach ( $this->counters as $counter ) {
			$counter->conditionallyIncrementRevertCount( $id, $revision );
		}
		$this->assertSame( 1, $this->getRevertCountForCounter( $id, 'app_description_edits' ) );
	}

	public function testIncrementCaptionEditCount() {
		$id = Utils::getCentralId( $this->user );
		$request = $this->getRequest( 'wbsetlabel' );
		$revision = $this->getRevision( self::CAPTION_COMMENT );
		foreach ( $this->counters as $counter ) {
			$counter->conditionallyIncrementEditCount( $id, $request, $revision );
		}
		$this->assertSame( 1, $this->getEditCountForCounter( $id, 'app_caption_edits' ) );
		foreach ( $this->counters as $counter ) {
			$counter->conditionallyIncrementRevertCount( $id, $revision );
		}
		$this->assertSame( 1, $this->getRevertCountForCounter( $id, 'app_caption_edits' ) );
	}

	public function testIncrementDepictsEditCount() {
		$id = Utils::getCentralId( $this->user );
		$request = $this->getRequest( 'wbeditentity' );
		$revision = $this->getRevision( self::DEPICTS_COMMENT );
		foreach ( $this->counters as $counter ) {
			$counter->conditionallyIncrementEditCount( $id, $request, $revision );
		}
		$this->assertSame( 1, $this->getEditCountForCounter( $id, 'app_depicts_edits' ) );
		foreach ( $this->counters as $counter ) {
			$counter->conditionallyIncrementRevertCount( $id, $revision );
		}
		$this->assertSame( 1, $this->getRevertCountForCounter( $id, 'app_depicts_edits' ) );
	}

	public function testIncrementDepictsEditCountOld() {
		$id = Utils::getCentralId( $this->user );
		$request = $this->getRequest( 'wbsetclaim' );
		$revision = $this->getRevision( self::DEPICTS_COMMENT_OLD );
		foreach ( $this->counters as $counter ) {
			$counter->conditionallyIncrementEditCount( $id, $request, $revision );
		}
		$this->assertSame( 1, $this->getEditCountForCounter( $id, 'app_depicts_edits' ) );
		foreach ( $this->counters as $counter ) {
			$counter->conditionallyIncrementRevertCount( $id, $revision );
		}
		$this->assertSame( 1, $this->getRevertCountForCounter( $id, 'app_depicts_edits' ) );
	}

	public function testIsWikipediaAppRequest() {
		$this->assertTrue( $this->counters[0]->isWikipediaAppRequest( $this->getRequest( 'query' ) ) );
		$this->assertFalse( $this->counters[0]->isWikipediaAppRequest( new FauxRequest() ) );
	}

	public function testGetLanguageFromQualifyingComment() {
		$lang = $this->counters[0]->getLanguageFromWikibaseComment( "wbsetdescription", self::DESCRIPTION_COMMENT );
		$this->assertSame( 'zh', $lang );
	}

	public function testGetLanguageFromNonQualifyingNonWikibaseComment() {
		$comment = 'Foo';
		$lang = $this->counters[0]->getLanguageFromWikibaseComment( "wbsetdescription", $comment );
		$this->assertNull( $lang );
	}

	/**
	 * @param int $id central user id
	 * @param string $key counter key
	 * @return int
	 */
	private function getEditCountForCounter( int $id, string $key ): int {
		$countsByCounter = $this->counterDao->getAllEditCounts( $id );
		$countsByLang = $countsByCounter[$key];
		return array_sum( array_values( $countsByLang ) );
	}

	/**
	 * @param int $id central user id
	 * @param string $key counter key
	 * @return int
	 */
	private function getRevertCountForCounter( int $id, string $key ): int {
		$countsByCounter = $this->counterDao->getAllRevertCounts( $id );
		$countsByLang = $countsByCounter[$key];
		return array_sum( array_values( $countsByLang ) );
	}

	/**
	 * @param string $action
	 * @return WebRequest
	 */
	private function getRequest( string $action ): WebRequest {
		$request = new FauxRequest( [ 'action' => $action ] );
		$request->setHeader( 'User-agent', 'WikipediaApp/WikipediaAppCounterTest' );
		return $request;
	}

	/**
	 * @param string $comment
	 * @return RevisionStoreRecord
	 */
	private function getRevision( string $comment = '' ): RevisionStoreRecord {
		$title = Title::newFromText( 'Dummy' );
		$title->resetArticleID( 177 );

		$comment = CommentStoreComment::newUnsavedComment( $comment );

		$main = SlotRecord::newUnsaved( SlotRecord::MAIN, new TextContent( 'Lorem Ipsum' ) );
		$slots = new RevisionSlots( [ $main ] );

		$row = [
			'rev_id' => '7',
			'rev_page' => strval( $title->getArticleID() ),
			'rev_timestamp' => '20200101000000',
			'rev_deleted' => 0,
			'rev_minor_edit' => 0,
			'rev_parent_id' => '5',
			'rev_len' => $slots->computeSize(),
			'rev_sha1' => $slots->computeSha1(),
			'page_latest' => '178',
		];

		return new RevisionStoreRecord( $title, $this->user, $comment, (object)$row, $slots );
	}

}
