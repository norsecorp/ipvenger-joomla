<?php
// no direct access
defined('_JEXEC') or die;

/**
 *
 *	ipv_get_block_html.php:  display sample html for a blocked page
 *
 *  One GET variable args
 *
 * 		type	string		type of block (general, proxy, botnet)
 *
*/

    require_once( dirname( __FILE__ ) .
        '/../cms-includes/ipv_cms_workarounds.php' );

    require_once( dirname( __FILE__ ) .
        '/../cms-includes/ipv_session.php' );

    if ( ! ipv_session_isset( 'ipv_is_admin' ) ||
        ! ipv_session_get( 'ipv_is_admin' ) )
    {
        die( 'Unauthorized' );
    }

    require_once( dirname( __FILE__ ) .
        '/../cms-includes/ipv_block_page.php' );

	ipv_echo_block( 0, $_GET['type'], 'IPQ Score', '192.0.0.1', 'ipv_demo', $_GET['msg'] );

?>
