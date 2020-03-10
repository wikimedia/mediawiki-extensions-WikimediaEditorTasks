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

/**
 * Counter for Wikimedia Commons file caption edits from the official Wikipedia apps.
 */
class WikipediaAppCaptionEditCounter extends WikipediaAppCounter {

	/** @inheritDoc */
	public function onEditSuccess( int $centralId, WebRequest $request, RevisionRecord $revision ):
		void {
		$this->conditionallyIncrementEditCount( $centralId, $request, $revision );
		ChangeTags::addTags( 'apps-suggested-edits', null, $revision->getId() );
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

	/** @inheritDoc */
	protected function getAction(): string {
		return 'wbsetlabel';
	}

	/** @inheritDoc */
	protected function isLanguageSpecific() {
		return true;
	}

}
