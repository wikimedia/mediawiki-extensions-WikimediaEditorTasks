<?php

use MediaWiki\Extension\WikimediaEditorTasks\CounterFactory;
use MediaWiki\Extension\WikimediaEditorTasks\Dao;
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
		return new CounterFactory( $wmeServices->getDao(), $wmeServices->getNameTableStore() );
	},

	'WikimediaEditorTasksDao' => function ( MediaWikiServices $services ): Dao {
		return new Dao(
			Utils::getDB( DB_MASTER, $services ),
			Utils::getDB( DB_REPLICA, $services )
		);
	},

	'WikimediaEditorTasksNameTableStore' => function ( MediaWikiServices $services ):
		NameTableStore {
		$wetServices = WikimediaEditorTasksServices::getInstance();
		$database = $wetServices->getExtensionConfig()->get( 'WikimediaEditorTasksDatabase' );
		$cluster = $wetServices->getExtensionConfig()->get( 'WikimediaEditorTasksCluster' );

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
			'wet_key'
		);
	},

];
