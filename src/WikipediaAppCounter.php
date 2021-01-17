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
	 * @param string $comment Revision comment to be validated for whether to include this revision in the count.
	 * @return bool Whether this revision should be counted by this counter.
	 */
	abstract protected function validateComment( string $comment ): bool;

	/**
	 * @param string $comment Revision comment from which the language may be extracted.
	 * @return string|null Language parsed from the given comment, or a constant overridden language.
	 */
	abstract protected function getLanguageFromComment( string $comment ): ?string;

	/** @inheritDoc */
	public function onEditSuccess( int $centralId, WebRequest $request, RevisionRecord $revision ): void {
		$this->conditionallyIncrementEditCount( $centralId, $request, $revision );
	}

	/** @inheritDoc */
	public function onRevert( int $centralId, int $revisionId, RevisionRecord $revision ): void {
		if ( !$this->hasSuggestedEditsChangeTag( $revisionId ) ) {
			return;
		}
		if ( $this->isRevertCountingEnabled() ) {
			$this->conditionallyIncrementRevertCount( $centralId, $revision );
		} else {
			$this->reset( $centralId );
		}
	}

	/**
	 * Increment the counter corresponding to the provided MW API action
	 * @param int $centralId central ID of the editing user
	 * @param WebRequest $request
	 * @param RevisionRecord $revision revision representing the successful edit
	 */
	protected function conditionallyIncrementEditCount( int $centralId, WebRequest $request,
		RevisionRecord $revision ): void {
		if ( !$this->isWikipediaAppRequest( $request ) ) {
			return;
		}
		$comment = $revision->getComment()->text;
		if ( !$this->validateComment( $comment ) ) {
			return;
		}
		$lang = $this->getLanguageFromComment( $comment );
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
	 * @param RevisionRecord $revision the RevisionRecord corresponding with $revisionId
	 */
	protected function conditionallyIncrementRevertCount(
		int $centralId,
		RevisionRecord $revision
	): void {
		$comment = $revision->getComment()->text;
		if ( !$this->validateComment( $comment ) ) {
			return;
		}
		$lang = $this->getLanguageFromComment( $comment );
		if ( $lang ) {
			$this->incrementRevertCountForLang( $centralId, $lang );
		}
	}

	/**
	 * Return true if the suggested edits change tag is associated with the revision.
	 * @param int $revisionId
	 * @return bool
	 */
	protected function hasSuggestedEditsChangeTag( int $revisionId ): bool {
		$tags = ChangeTags::getTags( wfGetDB( DB_REPLICA ), null, $revisionId );
		return in_array( 'apps-suggested-edits', $tags, true );
	}

	/**
	 * Get the language code from the semi-structured Wikibase edit summary text.
	 * Examples:
	 *  \/* wbsetdescription-add:1|en *\/ 19th century French painter
	 *  \/* wbsetlabel-add:1|en *\/ A chicken in the snow
	 * See docs at mediawiki-extensions-Wikibase/docs/summaries.md.
	 * TODO: Update to use structured comment data when that's implemented (T215422)
	 * @param string $action
	 * @param string $comment
	 * @return string|null language code, if found
	 */
	protected function getLanguageFromWikibaseComment( string $action, string $comment ): ?string {
		if ( !$comment ) {
			return null;
		}
		$matches = [];
		$result = preg_match( $this->getMagicCommentPattern( $action ), $comment, $matches );
		if ( $result ) {
			return $matches[1];
		}
		return null;
	}

	/**
	 * @param WebRequest $request
	 * @return bool
	 */
	private function isWikipediaAppRequest( WebRequest $request ) {
		$ua = $request->getHeader( 'User-agent' );
		if ( $ua ) {
			return strpos( $ua, 'WikipediaApp/' ) === 0;
		}
		return false;
	}

	/**
	 * @param string $action Wikibase action to which this pattern will apply.
	 * @return string pattern matching Wikibase magic comments associated with this counter.
	 */
	protected function getMagicCommentPattern( string $action ): string {
		return '/^\/\* ' . $action . '-[a-z]{3}:[0-9]\|([a-z-]+) /';
	}

}
