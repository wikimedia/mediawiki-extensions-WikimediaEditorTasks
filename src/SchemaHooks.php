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

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use RuntimeException;

/**
 * Schema hooks for WikimediaEditorTasks extension
 */
class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		if ( $updater->getDB()->getType() !== 'mysql' ) {
			// Wikimedia specific extension
			throw new RuntimeException( 'only mysql is supported' );
		}
		$baseDir = dirname( __DIR__ ) . '/sql';

		$updater->addExtensionTable( 'wikimedia_editor_tasks_keys', "$baseDir/tables-generated.sql" );
		$updater->addExtensionTable( 'wikimedia_editor_tasks_edit_streak',
			"$baseDir/sql/edit_streak.sql" );
		$updater->dropExtensionTable(
			'wikimedia_editor_tasks_entity_description_exists',
			"$baseDir/drop-wikimedia_editor_tasks_entity_description_exists.sql"
		);
		$updater->dropExtensionTable(
			'wikimedia_editor_tasks_targets_passed',
			"$baseDir/drop-wikimedia_editor_tasks_targets_passed.sql"
		);
		$updater->addExtensionField(
			'wikimedia_editor_tasks_counts',
			'wetc_revert_count',
			"$baseDir/alter-wikimedia_editor_tasks_counts.sql"
		);
	}

}
