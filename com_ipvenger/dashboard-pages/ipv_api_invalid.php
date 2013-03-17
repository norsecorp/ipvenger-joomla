<?php

// no direct access
defined('_JEXEC') or die;

    require_once( dirname( __FILE__ ) .
        '/../cms-includes/ipv_session.php' );

    if ( ! ipv_session_isset( 'ipv_is_admin' ) ||
        ! ipv_session_get( 'ipv_is_admin' ) )
    {
        die( 'Unauthorized' );
    }

	$base_url = JURI::base();
	$general_settings_url = $base_url .  
		'index.php?option=com_ipvenger&view=ipv_general';

	$out = <<<EOB
	Please supply a valid product license key on the 
	<a href="$general_settings_url">General Settings</a> page to activate 
	this option.
EOB;

return $out;
