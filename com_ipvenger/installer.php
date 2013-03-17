<?php

// no direct access
defined('_JEXEC') or die;

/**
 * @file
 * 
 * Install/uninstall IPVenger component
*/

class com_ipvengerInstallerScript {

	public function install( $adapter ) {

		$dir = dirname( __FILE__ );

		require_once $dir . "/cms-includes/ipv_db_utils.php";	
		require_once $dir . "/core-includes/ipv_captcha_utils.php";	
		require_once $dir . "/core-includes/ipv_config_utils.php";	

		ipv_db_create_tables();

		// pregenerate some CAPTCHAs
		ipv_init_captcha_cache();

		// store the direct URL of the IP control center so we can point to it 
		// from "outside" Joomla when sending emails from appeal processor

		$base_url = JURI::base();
		$comp_url = $base_url . 'components/com_ipvenger/';

		$ipcc_url 		= $base_url . 
			'index.php?option=com_ipvenger&view=ipv_ipcc';

		$stylesheet_uri = $comp_url . 'css/style.css';
		$block_path     = $comp_url;

		$jConfig = new JConfig();
		$name    = $jConfig->sitename;

		ipv_set_blog_info(
			$ipcc_url, 
			$stylesheet_uri, 
			NULL,
			$name, 
			NULL,
			$block_path
		);

		ipv_set_default_email( $jConfig->mailfrom );

		// set the database flag
		ipv_plugin_set_active( true );

		// enable the plugin

		$db = JFactory::getDbo();

		$tableExtensions = $db->quoteName("#__extensions");
		$columnElement   = $db->quoteName("element");
		$columnType      = $db->quoteName("type");
		$columnEnabled   = $db->quoteName("enabled");

		$db->setQuery("UPDATE $tableExtensions SET $columnEnabled=1 WHERE $columnElement='ipvenger' AND $columnType='plugin'");

		$db->query();

	}

	public function uninstall( $adapter ) {

		$dir = dirname( __FILE__ );

		require_once $dir . "/cms-includes/ipv_db_utils.php";	

		ipv_db_drop_tables();
	}

	public function update( $adapter ) {

		ipv_db_drop_static_tables();
		ipv_db_create_static_tables();
		ipv_db_update_schema();

		// update the database in case we've reactivated with custom threshold
		ipv_update_site_ipq( "custom", ipv_get_default_risk() );

	}
}


