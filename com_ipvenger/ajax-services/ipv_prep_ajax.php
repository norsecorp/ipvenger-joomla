<?php

/**
 * ipv_prep_ajax.php
 *
 * Perform security checks and other housekeeping needed by ajax services
 * Every ajax service should include this before any other processing
 *
*/

	define( 'IPV_IN_AJAX', true );

	require( dirname( __FILE__ ) . '/../cms-includes/ipv_cms_workarounds.php' );
	require_once( dirname( __FILE__ ) . '/../cms-includes/ipv_session.php' );

    // no direct access
    defined('_JEXEC') or die;

	// make sure we are called by a valid, admin level user and have the 
	// correct session authentication (anti csrf) token

	if ( ! ipv_session_isset( 'ipv_is_admin' ) 		|| 
		 ! ipv_session_isset( 'ipv_auth_token' )	|| 
		 ! isset( $_POST['ipv_auth_token'] ) 		|| 
		 ! ipv_session_get( 'ipv_is_admin' )		|| 
		 ! (ipv_session_get( 'ipv_auth_token' ) == $_POST['ipv_auth_token' ] ))
	{
		$err_string = 'Unauthorized.  ipv_is_admin: ';

		if ( ipv_session_isset( 'ipv_is_admin' ) ) $err_string .= "true, ";
		else $err_string .="false, ";

		$err_string .="ipv_auth_token: ";

		if ( ipv_session_isset( 'ipv_auth_token' ) )
			$err_string .= ipv_session_get( 'ipv_auth_token' ) . ".";
		else $err_string .="UNINITIALIZED.";

		die( $err_string );	
	}

?>
