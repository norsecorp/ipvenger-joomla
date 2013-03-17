<?php

// no direct access
defined('_JEXEC') or die;

// no direct access
defined('_JEXEC') or die;

JHTML::_('behavior.framework', true);

if ( isset( $_GET['view'] ) ) $view = $_GET['view'];
else $view = 'ipv_general';

// Set up toolbar

JToolBarHelper::title( 'IPVenger', 'ipv-hdr-icon' );
JToolBarHelper::help( 'ipvenger_help', true );

JSubMenuHelper::addEntry(
	"General Settings",
	'index.php?option=com_ipvenger',
	$view == 'ipv_general'
);

JSubMenuHelper::addEntry(
	"Analytics",
	'index.php?option=com_ipvenger&view=ipv_analytics',
	$view == 'ipv_analytics'
);

JSubMenuHelper::addEntry(
	"IP Control Center",
	'index.php?option=com_ipvenger&view=ipv_ipcc',
	$view == 'ipv_ipcc'
);

JSubMenuHelper::addEntry(
	"Advanced Settings",
	'index.php?option=com_ipvenger&view=ipv_advanced',
	$view == 'ipv_advanced'
);

$document =& JFactory::GetDocument();
$document ->addStyleDeclaration( '.icon-48-ipv-hdr-icon {background-image: url(../administrator/components/com_ipvenger/images/IPVenger48x48.png);}');

// relative path variables

$abs_path = JPATH_ADMINISTRATOR . '/components/com_ipvenger/';

$GLOBALS['ipv_cms_includes']    = $abs_path . 'cms-includes';
$GLOBALS['ipv_core_includes']   = $abs_path . 'core-includes';

require_once ( $GLOBALS['ipv_core_includes'] . '/ipv_api_key.php' );

$api_is_valid = ipv_api_keymaster( $ipv_api_key );

// URL path variables for use in analytics html and scripts

$base_url = JURI::base() . 'components/com_ipvenger/';

$GLOBALS['ipv_base_url'] = $base_url;
$GLOBALS['ipv_image_url'] = $base_url . 'images';

$GLOBALS['ipv_securimage_home'] = $base_url . 'securimage';
$GLOBALS['ipv_jquery_home']     = $base_url . 'jquery';
$GLOBALS['ipv_css_home']        = $base_url . 'css';
$GLOBALS['ipv_core_home']       = $base_url . 'core-includes';
$GLOBALS['ipv_dashboard_home']  = $base_url . 'dashboard-pages';
$GLOBALS['ipv_ajax_home']       = $base_url . 'ajax-services';

$d =& JFactory::getDocument();
JHtml::_('bootstrap.framework');

$d->addStyleSheet( $GLOBALS['ipv_css_home'] . '/style.css' );
$d->addStyleSheet( $GLOBALS['ipv_jquery_home'] . '/jquery-ui-1.8.19.custom.css' );
$d->addScript( $GLOBALS['ipv_dashboard_home'] . '/ipv_handle_timeout.js' );
$d->addStyleSheet( $GLOBALS['ipv_jquery_home'] . '/jquery.cluetip.css' );

$d->addStyleSheet( $GLOBALS['ipv_jquery_home'] . '/jquery-jvectormap-1.0.css' );
$d->addStyleSheet( $GLOBALS['ipv_jquery_home'] . '/jquery.combobox.css' );

$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery-1.8.3.min.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery-ui-1.9.2.custom.min.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.combobox.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.blockUI.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.cluetip.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery-jvectormap-1.0.min.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery-jvectormap-world-en.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/country_codes.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.tablesorter.min.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.tablesorter.widgets.min.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.fixedHeader.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.flot.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.flot.orderBars.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.flot.pie.js' );
$d->addScript( $GLOBALS['ipv_jquery_home'] . '/jquery.flot.stack.js' );

$d->addScript( $GLOBALS['ipv_dashboard_home'] . '/ipv_ip_bw_list.js' );

$cfg = new JConfig();
$GLOBALS['ipv_admin_e_mail']   = $cfg->mailfrom;

$user =& JFactory::getUser();

require_once ( $GLOBALS['ipv_cms_includes'] . '/ipv_session.php' );
ipv_session_set( 'ipv_is_admin', $user->get( 'isRoot' ) );

if ( ! ipv_session_get( 'ipv_auth_token' ) ) {

	if ( function_exists( 'openssl_random_pseudo_bytes' ) )
		$auth_token = bin2hex( openssl_random_pseudo_bytes(128) );
	else
		$auth_token = sha1( uniqid( mt_rand() ) );

	ipv_session_set( 'ipv_auth_token', $auth_token );

}

$view_path = $abs_path . '/dashboard-pages/';

switch ( $view ) {
	case 'ipv_advanced' : 
		if ( $api_is_valid )
			$output = require $view_path . 'ipv_advanced.php';
		else 
			$output = require $view_path . 'ipv_api_invalid.php';
		break;
	case 'ipv_analytics' : 
		if ( $api_is_valid )
			$output = require $view_path . 'ipv_analytics.php';
		else 
			$output = require $view_path . 'ipv_api_invalid.php';
		break;
		break;
	case 'ipv_ipcc' : 
		if ( $api_is_valid )
			$output = require $view_path . 'ipv_ipcc.php';
		else 
			$output = require $view_path . 'ipv_api_invalid.php';
		break;
		break;
	default:
		$output = require $view_path . 'ipv_general.php';
		break;
}

echo $output;
?>
