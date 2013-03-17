<?php

/* 	CMS specific workarounds to be included by ajax routines that need to 
    exist "outside" the main presentation loop */

	// bootstrap just enough JOOMLA to get access to the database and session

	if ( ! defined( '_JEXEC' ) ) {
		define( '_JEXEC', 1 );
		define( 'JPATH_BASE', realpath(dirname(__FILE__).'/../../..' ));

		require_once ( JPATH_BASE . '/includes/defines.php' );
		require_once ( JPATH_BASE . '/includes/framework.php' );

		/* site and admin have different session stores */
		if ( isset( $GLOBALS['ipv_site'] ) && $GLOBALS['ipv_site'] == 'site' )
			$mainframe =& JFactory::getApplication('site');
		else
			$mainframe =& JFactory::getApplication('administrator');

		$mainframe->initialise();
	}

    // no direct access
    defined('_JEXEC') or die;

?>
