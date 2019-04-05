<?php

namespace MediaWiki\Extension\WikimediaEditorTasks;

use Maintenance;
use MediaWiki\MediaWikiServices;

/**
 * Populate the entity_description_exists DB table with data on whether a description exists for the
 * entity in the language of each linked article.
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class PopulateEntityDescriptionExistsTable extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Populates a database table of Wikibase entities with data on ' .
			'whether a description exists for the entity in the language of each linked article';
		$this->requireExtension( 'WikimediaEditorTasks' );
	}

	public function execute() {
		$services = MediaWikiServices::getInstance();
		$dbw = Utils::getTaskSuggestionsDB( DB_MASTER, $services );
		$dbr = Utils::getTaskSuggestionsDB( DB_REPLICA, $services, [ 'vslow' ] );
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();

		$batchSize = 10000;

		$start = $dbw->selectField( 'wb_items_per_site', 'MIN(ips_row_id)', '', __METHOD__ );
		$end = $dbw->selectField( 'wb_items_per_site', 'MAX(ips_row_id)', '', __METHOD__ );
		if ( !$start || !$end ) {
			$this->output( "Nothing to do.\n" );
			return;
		}

		$blockStart = $start;
		$blockEnd = $start + $batchSize - 1;

		while ( $blockStart <= $end ) {
			$wrapper = $dbr->select(
				[ 'wb_items_per_site', 'wb_terms' ],
				[ 'ips_item_id', 'ips_site_id', 'term_language' ],
				[
					'ips_row_id >= ' . (int)$blockStart,
					'ips_row_id <= ' . (int)$blockEnd,
				],
				__METHOD__,
				[],
				[
					'wb_terms' => [
						'LEFT JOIN',
						[
							'term_full_entity_id = ' . $dbr->buildConcat( [
								$dbr->addQuotes( 'Q' ),
								'ips_item_id',
							] ),
							'term_type' => 'description',
							'ips_site_id = ' . $dbr->buildConcat( [
								'term_language',
								$dbr->addQuotes( 'wiki' ),
							] ),
						],
					],
				]
			);

			$dbw->replace(
				'wikimedia_editor_tasks_entity_description_exists',
				[ 'wetede_entity_id', 'wetede_language' ],
				$this->generateRowsForReplace( $wrapper ),
				__METHOD__
			);

			$this->output( "." );
			$loadBalancerFactory->waitForReplication();

			$blockStart += $batchSize;
			$blockEnd += $batchSize;
		}
	}

	private function generateRowsForReplace( $wrapper ) {
		$result = [];
		foreach ( $wrapper as $row ) {
			$result[] = [
				'wetede_entity_id' => $row->ips_item_id,
				'wetede_language' => substr( $row->ips_site_id, 0, -4 ),
				'wetede_description_exists' => (bool)$row->term_language,
				'wetede_rand' => mt_rand( 0, mt_getrandmax() - 1 ) / mt_getrandmax(),
			];
		}
		return $result;
	}

}

$maintClass = PopulateEntityDescriptionExistsTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
