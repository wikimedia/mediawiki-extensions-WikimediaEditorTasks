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

/**
 * Counter for Wikimedia Commons file caption edits from the official Wikipedia apps.
 */
class WikipediaAppCaptionEditCounter extends WikipediaAppCounter {

	/**
	 * @inheritDoc
	 */
	protected function validateComment( string $comment ): bool {
		if ( stripos( $comment, 'wbsetlabel-' ) !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getLanguageFromComment( string $comment ): ?string {
		return $this->getLanguageFromWikibaseComment( "wbsetlabel", $comment );
	}

}
