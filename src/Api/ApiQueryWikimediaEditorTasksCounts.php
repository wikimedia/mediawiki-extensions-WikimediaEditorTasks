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

use ApiQuery;
use ApiQueryBase;
use Config;
use MediaWiki\Extension\WikimediaEditorTasks\CounterDao;
use MediaWiki\Extension\WikimediaEditorTasks\Utils;

class ApiQueryWikimediaEditorTasksCounts extends ApiQueryBase {

	/** @var CounterDao */
	private $counterDao;

	/** @var bool */
	private $editStreaksEnabled;

	/** @var bool */
	private $revertCountsEnabled;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param CounterDao $counterDao
	 * @param Config $extensionConfig
	 */
	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		CounterDao $counterDao,
		Config $extensionConfig
	) {
		parent::__construct( $queryModule, $moduleName, 'wmetc' );
		$this->counterDao = $counterDao;
		$this->editStreaksEnabled = $extensionConfig->get( 'WikimediaEditorTasksEnableEditStreaks' );
		$this->revertCountsEnabled = $extensionConfig->get( 'WikimediaEditorTasksEnableRevertCounts' );
	}

	/**
	 * Entry point for executing the module
	 * @inheritDoc
	 */
	public function execute() {
		if ( $this->getUser()->isAnon() ) {
			$this->dieWithError( [ 'apierror-mustbeloggedin',
				$this->msg( 'action-viewmyprivateinfo' ) ], 'notloggedin' );
		}
		$this->checkUserRightsAny( 'viewmyprivateinfo' );

		$dao = $this->counterDao;
		$centralId = Utils::getCentralId( $this->getUser() );

		$result = [ 'counts' => $dao->getAllEditCounts( $centralId ) ];
		if ( $this->editStreaksEnabled ) {
			$result['edit_streak'] = $dao->getEditStreak( $centralId );
		}
		if ( $this->revertCountsEnabled ) {
			$result['revert_counts'] = $dao->getAllRevertCounts( $centralId );
		}
		$this->getResult()->addValue( 'query', 'wikimediaeditortaskscounts', $result );
	}

	/**
	 * @inheritDoc
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			"action=query&formatversion=2&meta=wikimediaeditortaskscounts" =>
				'apihelp-query+wikimediaeditortaskscounts-example',
		];
	}

	/**
	 * @inheritDoc
	 * @return bool
	 */
	public function isInternal() {
		return true;
	}

}
