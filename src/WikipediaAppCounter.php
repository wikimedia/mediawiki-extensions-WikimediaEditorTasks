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
use MediaWiki\Revision\RevisionRecord;
use WebRequest;

abstract class WikipediaAppCounter extends Counter {

	/**
	 * @return string Wikibase edit action associated with the counter
	 */
	abstract protected function getAction(): string;

	/**
	 * Increment the counter corresponding to the provided MW API action
	 * @param int $centralId central ID of the editing user
	 * @param WebRequest $request
	 * @param RevisionRecord $revision revision representing the successful edit
	 */
	protected function conditionallyIncrementEditCount( int $centralId, WebRequest $request,
		RevisionRecord $revision ): void {
		if ( !$this->isWikipediaAppMwApiRequest( $request ) ) {
			return;
		}
		$params = $this->getRequestParams( $request );
		if ( $params['action'] !== $this->getAction() ) {
			return;
		}
		$comment = $revision->getComment()->text;
		if ( stripos( $comment, '#suggestededit' ) === false ) {
			return;
		}
		$lang = $this->isLanguageSpecific() ?
			$this->getLanguageFromWikibaseComment( $comment ) :
			'*';
		if ( !$lang ) {
			return;
		}
		$this->incrementEditCountForLang( $centralId, $lang );
		$this->updateEditStreak( $centralId );
		ChangeTags::addTags( 'apps-suggested-edits', null, $revision->getId() );
	}

	/**
	 * Increment the revert counter
	 * @param int $centralId central ID of the editing user
	 * @param int $revisionId revision ID of the reverted edit
	 * @param RevisionRecord $revision the RevisionRecord corresponding with $revisionId
	 */
	protected function conditionallyIncrementRevertCount( int $centralId, int $revisionId,
		RevisionRecord $revision ): void {
		$lang = $this->getLanguageFromWikibaseComment( $revision->getComment()->text );
		if ( $lang && $this->hasSuggestedEditsChangeTag( $revisionId ) ) {
			$this->incrementRevertCountForLang( $centralId, $lang );
		}
	}

	/**
	 * Get the language code from the semi-structured Wikibase edit summary text, if the comment
	 *  matches the corresponding pattern.
	 * Examples:
	 *  \/* wbsetdescription-add:1|en *\/ 19th century French painter
	 *  \/* wbsetlabel-add:1|en *\/ A chicken in the snow
	 * See docs at mediawiki-extensions-Wikibase/docs/summaries.md.
	 * TODO: Update to use structured comment data when that's implemented (T215422)
	 * @param string $comment
	 * @return string|null language code, if found
	 */
	private function getLanguageFromWikibaseComment( string $comment ): ?string {
		if ( !$comment ) {
			return null;
		}
		$matches = [];
		$result = preg_match( $this->getMagicCommentPattern(), $comment, $matches );
		if ( $result ) {
			return $matches[1];
		}
		return null;
	}

	private function hasSuggestedEditsChangeTag( $revisionId ) {
		$tags = ChangeTags::getTags( wfGetDB( DB_REPLICA ), null, $revisionId );
		return in_array( 'apps-suggested-edits', $tags, true );
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

	/**
	 * @return string pattern matching Wikibase magic comments associated with this counter.
	 */
	private function getMagicCommentPattern(): string {
		return '/^\/\* ' . $this->getAction() . '-[a-z]{3}:[0-9]\|([a-z-]+) \*\/.*?#suggestededit/';
	}

}
