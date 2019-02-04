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

use WebRequest;

/**
 * Counter for Wikidata description edits from the official Wikipedia apps.
 */
class WikipediaAppDescriptionEditCounter extends Counter {

	/**
	 * @inheritDoc
	 */
	public function onEditSuccess( $centralId, $request ) {
		if ( !(
			$request instanceof WebRequest
			&& $this->isRequestFromApp( $request )
			&& defined( 'MW_API' )
		) ) {
			return;
		}
		// If the query string and post body contain duplicate keys, the post body value wins
		$params = array_merge( $request->getQueryValues(), $request->getPostValues() );
		if ( $params['action'] === 'wbsetdescription' ) {
			$this->incrementForLang( $centralId, $params['language'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onRevert( $centralId ) {
		$this->reset( $centralId );
	}

	private function isRequestFromApp( WebRequest $request ) {
		$ua = $request->getHeader( 'User-agent' );
		if ( $ua ) {
			return strpos( $ua, 'WikipediaApp/' ) === 0;
		}
		return false;
	}

}
