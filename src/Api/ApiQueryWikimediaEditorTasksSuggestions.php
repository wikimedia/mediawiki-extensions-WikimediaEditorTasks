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
use ConfigException;
use LogicException;
use MediaWiki\Extension\WikimediaEditorTasks\WikimediaEditorTasksServices;
use SearchEngine;
use Status;
use Title;

class ApiQueryWikimediaEditorTasksSuggestions extends ApiQueryGeneratorBase {

	/** @var SearchEngine */
	private $cirrus;

	/** @var string API module prefix */
	private static $prefix = 'wets';

	/**
	 * ApiQueryWikimediaEditorTasksSuggestions constructor.
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @throws ConfigException
	 */
	public function __construct( ApiQuery $queryModule, $moduleName ) {
		parent::__construct( $queryModule, $moduleName, static::$prefix );
		$this->cirrus = WikimediaEditorTasksServices::getInstance()->getCirrusSearch();
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

		if ( $source && (
			$task === 'missingdescriptions'
			|| $task === 'missinglabels'
			|| $task === 'missingcaptions' )
		) {
			$this->dieWithError( [ 'apierror-invalidparammix-cannotusewith', 'source',
				'task=' . $task ], 'invalidparammix' );
		} elseif ( !$source && (
			$task === 'descriptiontranslations'
			|| $task === 'labeltranslations'
			|| $task === 'captiontranslations' )
		) {
			$this->dieWithError( [ 'apierror-invalidparammix-mustusewith',
				'task=' . $task, 'source' ], 'invalidparammix' );
		}

		$resultTitles = $this->getSuggestionsForTask( $task, $source, $target, $limit );

		if ( $resultPageSet ) {
			$resultPageSet->populateFromTitles( array_map( function ( $id ) {
				// FIXME: either disable this API when not on the Wikibase repo, or return
				// an external title pointing there
				return Title::newFromText( $id );
			}, $resultTitles ) );
		} else {
			$this->getResult()->addValue( [ 'query', 'wikimediaeditortaskssuggestions' ],
				$task, $resultTitles );
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
					'missinglabels',
					'labeltranslations',
					'missingcaptions',
					'captiontranslations',
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
				ApiBase::PARAM_MAX => 500,
				ApiBase::PARAM_MAX2 => 5000,
				ApiBase::PARAM_DFLT => 10,
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
				"action=query&formatversion=2&list=wikimediaeditortaskssuggestions" .
				"&${prefix}task=labeltranslations&${prefix}source=it&${prefix}target=ja" .
				"&${prefix}limit=10"
			=> 'apihelp-query+wikimediaeditortaskssuggestions-example-3',
				"action=query&formatversion=2&generator=wikimediaeditortaskssuggestions" .
				"&g${prefix}task=missinglabels&g${prefix}target=it&g${prefix}limit=10"
			=> 'apihelp-query+wikimediaeditortaskssuggestions-example-4',
				"action=query&formatversion=2&list=wikimediaeditortaskssuggestions" .
				"&${prefix}task=captiontranslations&${prefix}source=it&${prefix}target=ja" .
				"&${prefix}limit=10"
			=> 'apihelp-query+wikimediaeditortaskssuggestions-example-5',
				"action=query&formatversion=2&generator=wikimediaeditortaskssuggestions" .
				"&g${prefix}task=missingcaptions&g${prefix}target=it&g${prefix}limit=10"
			=> 'apihelp-query+wikimediaeditortaskssuggestions-example-6',
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
	 * @param string $term search term
	 * @param int $limit max number of results to return
	 * @return string[] result page titles
	 * @throws ApiUsageException
	 */
	private function searchEntities( $term, $limit ) {
		$result = [];

		$this->cirrus->setLimitOffset( $limit );
		$matches = $this->cirrus->searchText( $term );

		if ( $matches instanceof Status ) {
			$status = $matches;
			$matches = $status->getValue();
		} else {
			$status = null;
		}

		if ( $status ) {
			if ( $status->isOK() ) {
				$this->getMain()->getErrorFormatter()->addMessagesFromStatus(
					$this->getModuleName(),
					$status
				);
			} else {
				$this->dieStatus( $status );
			}
		} elseif ( is_null( $matches ) ) {
			$this->dieWithError( [ 'apierror-searchdisabled', 'text' ], "search-text-disabled" );
		}

		foreach ( $matches as $match ) {
			if ( !( $match->isBrokenTitle() || $match->isMissingRevision() ) ) {
				$result[] = $match->getTitle()->getPrefixedText();
			}
		}

		return $result;
	}

	/**
	 * Get description addition suggestions.
	 * @param string $target target lang
	 * @param int $limit desired number of suggestions
	 * @return string[] result page titles
	 * @throws ApiUsageException
	 */
	private function getMissingDescriptionSuggestions( $target, $limit ) {
		return $this->searchEntities( '-hasdescription:' . $target, $limit );
	}

	/**
	 * Get description translation suggestions.
	 * @param string $source source lang
	 * @param string $target target lang
	 * @param int $limit desired number of suggestions
	 * @return string[]
	 * @throws ApiUsageException
	 */
	private function getDescriptionTranslationSuggestions( $source, $target, $limit ) {
		$term = 'hasdescription:' . $source . ' -hasdescription:' . $target;
		return $this->searchEntities( $term, $limit );
	}

	/**
	 * Get label/caption addition suggestions.
	 * @param string $target target lang
	 * @param int $limit desired number of suggestions
	 * @return string[] result page titles
	 * @throws ApiUsageException
	 */
	private function getMissingLabelSuggestions( $target, $limit ) {
		return $this->searchEntities( '-haslabel:' . $target, $limit );
	}

	/**
	 * Get label/caption translation suggestions.
	 * @param string $source source lang
	 * @param string $target target lang
	 * @param int $limit desired number of suggestions
	 * @return string[]
	 * @throws ApiUsageException
	 */
	private function getLabelTranslationSuggestions( $source, $target, $limit ) {
		$term = 'haslabel:' . $source . ' -haslabel:' . $target;
		return $this->searchEntities( $term, $limit );
	}

	/**
	 * Get suggestions for the requested task type.
	 * @param string $task task name
	 * @param string $source source lang
	 * @param string $target target lang
	 * @param int $limit desired number of suggestions
	 * @return string[] page titles
	 * @throws ApiUsageException
	 */
	private function getSuggestionsForTask( $task, $source, $target, $limit ) {
		switch ( $task ) {
			case 'missingdescriptions':
				return $this->getMissingDescriptionSuggestions( $target, $limit );
			case 'descriptiontranslations':
				return $this->getDescriptionTranslationSuggestions( $source, $target, $limit );
			case 'missinglabels':
			case 'missingcaptions':
				return $this->getMissingLabelSuggestions( $target, $limit );
			case 'labeltranslations':
			case 'captiontranslations':
				return $this->getLabelTranslationSuggestions( $source, $target, $limit );
			default:
				// make static analyzers happy
				throw new LogicException( 'API failed to validate task parameter' );
		}
	}

}
