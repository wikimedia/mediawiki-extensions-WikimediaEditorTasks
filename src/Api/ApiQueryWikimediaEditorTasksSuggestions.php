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

namespace MediaWiki\Extension\WikimediaEditorTasks\Api;

use ApiBase;
use ApiPageSet;
use ApiQuery;
use ApiQueryGeneratorBase;
use ApiUsageException;
use LogicException;
use MediaWiki\Extension\WikimediaEditorTasks\SuggestionsDao;
use MediaWiki\Extension\WikimediaEditorTasks\WikimediaEditorTasksServices;
use Title;

class ApiQueryWikimediaEditorTasksSuggestions extends ApiQueryGeneratorBase {

	/** @var SuggestionsDao */
	private $dao;

	/** @var string API module prefix */
	private static $prefix = 'wets';

	/**
	 * ApiQueryWikimediaEditorTasksSuggestions constructor.
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $queryModule, $moduleName ) {
		parent::__construct( $queryModule, $moduleName, static::$prefix );
		$this->dao = WikimediaEditorTasksServices::getInstance()->getSuggestionsDao();
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		try {
			$this->run();
		} catch ( ApiUsageException $e ) {
			$this->dieWithException( $e );
		}
	}

	/**
	 * @inheritDoc
	 * @param ApiPageSet $resultPageSet
	 */
	public function executeGenerator( $resultPageSet ) {
		try {
			$this->run( $resultPageSet );
		} catch ( ApiUsageException $e ) {
			$this->dieWithException( $e );
		}
	}

	/**
	 * Main API logic.
	 * @param ApiPageSet|null $resultPageSet
	 * @throws ApiUsageException
	 */
	public function run( $resultPageSet = null ) {
		$task = $this->getParameter( 'task' );
		$source = $this->getParameter( 'source' );
		$target = $this->getParameter( 'target' );
		$limit = $this->getParameter( 'limit' );

		if ( $source && $task === 'missingdescriptions' ) {
			$this->dieWithError( [ 'apierror-invalidparammix-cannotusewith', 'source',
				'task=missingdescriptions' ], 'invalidparammix' );
		} elseif ( !$source && $task === 'descriptiontranslations' ) {
			$this->dieWithError( [ 'apierror-invalidparammix-mustusewith',
				'task=descriptiontranslations', 'source' ], 'invalidparammix' );
		}

		$wikibaseIds = $this->prependQs( $this->getSuggestionsForTask( $task, $source, $target,
			$limit ) );

		if ( $resultPageSet ) {
			$resultPageSet->populateFromTitles( array_map( function ( $id ) {
				// FIXME: either disable this API when not on the Wikibase repo, or return
				// an external title pointing there
				return Title::newFromText( $id );
			}, $wikibaseIds ) );
		} else {
			$this->getResult()->addValue( 'query', 'wikimediaeditortaskssuggestions',
				[ $task => $wikibaseIds ] );
		}
	}

	/**
	 * @inheritDoc
	 * @return array allowed params
	 */
	public function getAllowedParams() {
		return [
			'task' => [
				ApiBase::PARAM_TYPE => [
					'missingdescriptions',
					'descriptiontranslations',
				],
				ApiBase::PARAM_REQUIRED => true,
			],
			'source' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'target' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 10,
				ApiBase::PARAM_MAX2 => 10,
				ApiBase::PARAM_DFLT => 1,
			],
		];
	}

	/**
	 * @inheritDoc
	 * @return array
	 */
	protected function getExamplesMessages() {
		$prefix = static::$prefix;
		return [
			"action=query&formatversion=2&list=wikimediaeditortaskssuggestions" .
				"&${prefix}task=descriptiontranslations&${prefix}source=it&${prefix}target=ja" .
				"&${prefix}limit=10"
			=> 'apihelp-query+wikimediaeditortaskssuggestions-example-1',
			"action=query&formatversion=2&generator=wikimediaeditortaskssuggestions" .
				"&g${prefix}task=missingdescriptions&g${prefix}target=it&g${prefix}limit=10"
			=> 'apihelp-query+wikimediaeditortaskssuggestions-example-2',
		];
	}

	/**
	 * @inheritDoc
	 * @return bool
	 */
	public function isInternal() {
		return true;
	}

	/**
	 * Translates 'task' arguments to DAO methods and returns suggestions of the desired type.
	 * @param string $task task name
	 * @param string $source source lang
	 * @param string $target target lang
	 * @param int $limit desired number of suggestions
	 * @return string[]
	 * @throws ApiUsageException
	 */
	private function getSuggestionsForTask( $task, $source, $target, $limit ) {
		switch ( $task ) {
			case 'missingdescriptions':
				return $this->dao->getMissingDescriptionSuggestions( $target, $limit );
			case 'descriptiontranslations':
				return $this->dao->getDescriptionTranslationSuggestions( $source, $target, $limit );
			default:
				// make static analyzers happy
				throw new LogicException( 'API failed to validate task parameter' );
		}
	}

	/**
	 * Prepends initial Qs to Wikibase IDs. The Qs are omitted as redundant in the DB to save space.
	 * @param string[] $results Wikibase IDs from the DB, as integer strings
	 * @return string[] Wikibase IDs with 'Q' prepended
	 */
	private function prependQs( $results ) {
		return array_map( function ( $id ) {
			return 'Q' . $id;
		},  $results );
	}
}
