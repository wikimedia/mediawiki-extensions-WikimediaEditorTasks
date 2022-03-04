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

use CentralIdLookup;
use ConfigException;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDatabase;

class Utils {

	/**
	 * Get the central user ID for $user
	 * @param UserIdentity $user local User
	 * @return int central user ID
	 */
	public static function getCentralId( UserIdentity $user ) {
		return MediaWikiServices::getInstance()->getCentralIdLookupFactory()->getLookup()
			->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
	}

	/**
	 * Get the enabled counter definitions from the extension configuration
	 * @return array enabled counter config
	 */
	public static function getEnabledCounters() {
		$config = WikimediaEditorTasksServices::getInstance()->getExtensionConfig();
		return $config->get( 'WikimediaEditorTasksEnabledCounters' );
	}

	/**
	 * Get a database connection to the user counts database.
	 * @param int $db Type of the connection to get, e.g. DB_PRIMARY or DB_REPLICA.
	 * @param MediaWikiServices $services
	 * @param array $groups Query groups [optional]
	 * @return IDatabase
	 * @throws ConfigException
	 */
	public static function getUserCountsDB( $db, $services, $groups = [] ) {
		$wetServices = WikimediaEditorTasksServices::wrap( $services );
		$database = $wetServices->getExtensionConfig()->get( 'WikimediaEditorTasksUserCountsDatabase' );
		$cluster = $wetServices->getExtensionConfig()->get( 'WikimediaEditorTasksUserCountsCluster' );
		return self::getDB( $db, $services, $database, $cluster, $groups );
	}

	/**
	 * Get a database connection.
	 * @param int $db Type of the connection to get, e.g. DB_PRIMARY or DB_REPLICA.
	 * @param MediaWikiServices $services
	 * @param string $database DB name from extension config
	 * @param string $cluster cluster name from extension config
	 * @param array $groups Query groups [optional]
	 * @return IDatabase
	 * @throws ConfigException
	 */
	private static function getDB( $db, $services, $database, $cluster, $groups = [] ) {
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();
		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );
		return $loadBalancer->getConnectionRef( $db, $groups, $database );
	}

}
