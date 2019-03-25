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

use ApiQueryBase;
use ApiUsageException;
use MediaWiki\Extension\WikimediaEditorTasks\Utils;
use MediaWiki\Extension\WikimediaEditorTasks\WikimediaEditorTasksServices;

class ApiQueryWikimediaEditorTasksCounts extends ApiQueryBase {

	/**
	 * Entry point for executing the module
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
	 * Performs the DB query and returns results.
	 * @throws ApiUsageException
	 */
	private function run() {
		if ( $this->getUser()->isAnon() ) {
			$this->dieWithError( [ 'apierror-mustbeloggedin',
			   $this->msg( 'action-viewmyprivateinfo' ) ], 'notloggedin' );
		}
		$this->checkUserRightsAny( 'viewmyprivateinfo' );

		$dao = WikimediaEditorTasksServices::getInstance()->getCounterDao();
		$centralId = Utils::getCentralId( $this->getUser() );

		$counts = $dao->getAllCounts( $centralId );
		$targetsPassed = $dao->getAllTargetsPassed( $centralId );

		$this->getResult()->addValue( 'query', 'wikimediaeditortaskscounts', [
			'counts' => $counts,
			'targets_passed' => $targetsPassed
		] );
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
