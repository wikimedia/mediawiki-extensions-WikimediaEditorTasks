<?php

use MediaWiki\Extension\WikimediaEditorTasks\CounterFactory;
use MediaWiki\Extension\WikimediaEditorTasks\CounterDao;
use MediaWiki\Extension\WikimediaEditorTasks\Utils;
use MediaWiki\Extension\WikimediaEditorTasks\WikimediaEditorTasksServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;

return [

	'WikimediaEditorTasksCirrusSearch' => function ( MediaWikiServices $services ): SearchEngine {
		$cirrusConfig = $services->getConfigFactory()->makeConfig( 'CirrusSearch' );
		$searchSort = WikimediaEditorTasksServices::getInstance()->getExtensionConfig()
			->get( 'WikimediaEditorTasksSuggestionSearchSort' );
		$cirrus = new CirrusSearch( $cirrusConfig );
		$cirrus->setSort( $searchSort );
		return $cirrus;
	},

	'WikimediaEditorTasksConfig' => function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'WikimediaEditorTasks' );
	},

	'WikimediaEditorTasksCounterFactory' => function ( MediaWikiServices $services ):
		CounterFactory {
		$wmetServices = WikimediaEditorTasksServices::wrap( $services );
		return new CounterFactory(
			$wmetServices->getCounterDao(),
			$wmetServices->getNameTableStore(),
			$wmetServices->getExtensionConfig()->get( 'WikimediaEditorTasksEnableEditStreaks' ),
			$wmetServices->getExtensionConfig()->get( 'WikimediaEditorTasksEnableRevertCounts' )
		);
	},

	'WikimediaEditorTasksCounterDao' => function ( MediaWikiServices $services ): CounterDao {
		return new CounterDao(
			Utils::getUserCountsDB( DB_MASTER, $services ),
			Utils::getUserCountsDB( DB_REPLICA, $services )
		);
	},

	'WikimediaEditorTasksNameTableStore' => function ( MediaWikiServices $services ):
		NameTableStore {
		$wmetServices = WikimediaEditorTasksServices::getInstance();
		$database = $wmetServices->getExtensionConfig()->get( 'WikimediaEditorTasksUserCountsDatabase' );
		$cluster = $wmetServices->getExtensionConfig()->get( 'WikimediaEditorTasksUserCountsCluster' );

		$loadBalancerFactory = $services->getDBLoadBalancerFactory();
		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );

		return new NameTableStore(
			$loadBalancer,
			$services->getMainWANObjectCache(),
			LoggerFactory::getInstance( 'WikimediaEditorTasks' ),
			'wikimedia_editor_tasks_keys',
			'wet_id',
			'wet_key',
			null,
			$database
		);
	},

];
