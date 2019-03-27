<?php

use MediaWiki\Extension\WikimediaEditorTasks\CounterFactory;
use MediaWiki\Extension\WikimediaEditorTasks\CounterDao;
use MediaWiki\Extension\WikimediaEditorTasks\SuggestionsDao;
use MediaWiki\Extension\WikimediaEditorTasks\Utils;
use MediaWiki\Extension\WikimediaEditorTasks\WikimediaEditorTasksServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;

return [

	'WikimediaEditorTasksConfig' => function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'WikimediaEditorTasks' );
	},

	'WikimediaEditorTasksCounterFactory' => function ( MediaWikiServices $services ):
		CounterFactory {
		$wmeServices = WikimediaEditorTasksServices::wrap( $services );
		return new CounterFactory( $wmeServices->getCounterDao(),
			$wmeServices->getNameTableStore() );
	},

	'WikimediaEditorTasksCounterDao' => function ( MediaWikiServices $services ): CounterDao {
		return new CounterDao(
			Utils::getUserCountsDB( DB_MASTER, $services ),
			Utils::getUserCountsDB( DB_REPLICA, $services )
		);
	},

	'WikimediaEditorTasksNameTableStore' => function ( MediaWikiServices $services ):
		NameTableStore {
		$wetServices = WikimediaEditorTasksServices::getInstance();
		$database = $wetServices->getExtensionConfig()->get( 'WikimediaEditorTasksUserCountsDatabase' );
		$cluster = $wetServices->getExtensionConfig()->get( 'WikimediaEditorTasksUserCountsCluster' );

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

	'WikimediaEditorTasksSuggestionsDao' => function ( MediaWikiServices $services ):
		SuggestionsDao {
		return new SuggestionsDao( Utils::getTaskSuggestionsDB( DB_REPLICA, $services ) );
	}
];
