<?php

namespace MediaWiki\Extension\WikimediaEditorTasks\Test;

use MediaWiki\Extension\WikimediaEditorTasks\WikimediaEditorTasksServices;
use MediaWikiTestCase;
use Wikimedia\TestingAccessWrapper;

/** @covers \MediaWiki\Extension\WikimediaEditorTasks\WikipediaAppCounter */
class WikipediaAppCounterTest extends MediaWikiTestCase {

	/** @var TestCounter */
	private $counter;

	public function setUp(): void {
		parent::setUp();
		$counterFactory = WikimediaEditorTasksServices::getInstance()->getCounterFactory();
		$this->counter = TestingAccessWrapper::newFromObject( $counterFactory->create( [
			"class" => "MediaWiki\\Extension\\WikimediaEditorTasks\\Test\\TestCounter",
			"counter_key" => "test"
		] ) );
	}

	public function testGetLanguageFromQualifyingEditComment() {
		$comment = '/* wmettestedit-add:1|zh-hant */ 韓國高速鐵道';
		$lang = $this->counter->getLanguageFromWikibaseComment( $comment );
		$this->assertSame( 'zh-hant', $lang );
	}

	public function testGetLanguageFromNonQualifyingComment() {
		$comment = 'Foo';
		$lang = $this->counter->getLanguageFromWikibaseComment( $comment );
		$this->assertSame( null, $lang );
	}

}
