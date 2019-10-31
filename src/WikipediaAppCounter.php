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

use ChangeTags;
use Revision;
use WebRequest;

abstract class WikipediaAppCounter extends Counter {

	/**
	 * Increment the counter corresponding to the provided MW API action
	 * @param int $centralId central ID of the editing user
	 * @param WebRequest $request
	 * @param Revision $revision
	 * @param string $action the MW API action the counter corresponds to, e.g., 'wbsetlabel'
	 */
	protected function conditionallyIncrementForAction( $centralId, WebRequest $request,
		Revision $revision, $action ) {
		if ( !$this->isWikipediaAppMwApiRequest( $request ) ) {
			return;
		}
		$params = $this->getRequestParams( $request );
		if ( $params['action'] === $action ) {
			$this->incrementForLang( $centralId, $params['language'] );
			$this->updateEditStreak( $centralId );
			ChangeTags::addTags( 'apps-suggested-edits', null, $revision->getId() );
		}
	}

	private function getRequestParams( WebRequest $request ) {
		// If the query string and post body contain duplicate keys, the post body value wins
		return array_merge( $request->getQueryValues(), $request->getPostValues() );
	}

	private function isWikipediaAppMwApiRequest( $request ) {
		return $request instanceof WebRequest
			   && $this->isRequestFromApp( $request )
			   && defined( 'MW_API' );
	}

	private function isRequestFromApp( WebRequest $request ) {
		$ua = $request->getHeader( 'User-agent' );
		if ( $ua ) {
			return strpos( $ua, 'WikipediaApp/' ) === 0;
		}
		return false;
	}

}
