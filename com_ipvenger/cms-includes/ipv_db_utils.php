<?php

// no direct access
defined('_JEXEC') or die;

/*
 * *********  Joomla! Version **********
 *
 * database connection and cleanup functions 
 * 
*/

/* use global definitions to automatically prefix database tables */
/* in a CMS-neutral way - for Drupal, just wrap with {} */

/* we build some table names explicitly rather than via #_ so that we can */
/* query against information_schema elsewhere in the plugin */

require_once 'ipv_cms_workarounds.php';

$cfg = new JConfig();
$pfx = $cfg->dbprefix;

define( 'IPV_EXCEPTION',  		'#__ipv_exception' );
define( 'IPV_GLOBAL_SETTINGS', 	'#__ipv_global_settings' );
define( 'IPV_REQUEST_DETAIL',  	$pfx . 'ipv_request_detail' );
define( 'IPV_APPEAL',  			'#__ipv_appeal' );

// these tables are all static or transient data and are dropped/created
// at each plugin activation to facilitate maintenance

define( 'IPV_SITE_TYPE',  		'#__ipv_site_type' );
define( 'IPV_TOOLTIP',  		'#__ipv_tooltip' );
define( 'IPV_CACHE',  			'#__ipv_cache' );
define( 'IPV_CAPTCHA_SERVED', 	'#__ipv_captcha_served' );
define( 'IPV_CAPTCHA_CACHE', 	'#__ipv_captcha_cache' );

function ipv_db_connect() {
}

function ipv_db_cleanup() {
}

function ipv_db_escape_string( $str ) {
	$db = JFactory::getDBO();
	return $db->escape( $str );
}

function ipv_db_insert_id() {
	$db = JFactory::getDBO();
	return $db->insertid();
}

static $next_assoc_idx = 0;
static $assoc_list;

function ipv_db_query( $query ) {

	global $next_assoc_idx;

	$next_assoc_idx = 0;

	$db = JFactory::getDBO();
	$db->setQuery( $query );
	$db->query();
	return $db;

}

function ipv_insert_id() {

	$db = JFactory::getDBO();
	return $db->insertid();

}

/** FIXME - document this semantic (single threading) */

function ipv_db_fetch_assoc( $query ) {

	global $assoc_list, $next_assoc_idx;

	if ( $next_assoc_idx == 0 ) { 
		$assoc_list = $query->loadAssocList();
	}	

	if ( count( $assoc_list ) == $next_assoc_idx ) return NULL;

	// return a single row as a flat array to mimic mysql_ semantics

	return $assoc_list[ $next_assoc_idx++ ];

}

/** get an entire list to loop through **/

function ipv_db_fetch_assoc_list( $query ) {
		return $query->loadAssocList();
}

function ipv_db_fetch_row( $query ) {
		return $query->loadResult();
}

/** FIXME doc this - must call assoc first... */
function ipv_db_num_rows( $query ) {
	global $assoc_list;
	return count( $assoc_list );
}

// return number of tables
function ipv_db_count_tables() {

	define(  'DB_NAME', "drupal" );

	$count = ipv_db_query(
		'SELECT table_name FROM INFORMATION_SCHEMA.TABLES ' . 
 		'WHERE table_schema = \'' . DB_NAME . '\' AND ' .
 		'table_name LIKE \'%_ipv_%\'' 

	);

	return 0;
	// return $count;
}

/**
 * Called to determine if it's time to clean up request detail.
 * If it hasn't been done in over 24 hours, call ipv_db_purge_expired()
*/

function ipv_db_purge_check() {

    $result = ipv_db_query("SELECT DATEDIFF(NOW(), last_data_purge) AS PURGE_TIME FROM " . IPV_GLOBAL_SETTINGS);

    if ( $result  ) {
        $row = ipv_db_fetch_assoc( $result );
        $q_result = $row['PURGE_TIME'];
        if ($q_result >= 1) {
            ipv_db_purge_expired();
        }

    }

}

/** 
 *  Called by nightly cleanup to delete request detail records over 30 days
 *  old and appeal records over 48 hours old
*/
 
function ipv_db_purge_expired() {

	// purge request details more than 30 days old
	ipv_db_query(
		'DELETE FROM ' . IPV_REQUEST_DETAIL .
		' WHERE ipv_int_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)' 
	);

	// purge appeal requests more than 48 hours old
	ipv_db_query(
		'DELETE FROM ' . IPV_APPEAL .
		' WHERE timestamp < (NOW() - INTERVAL 48 HOUR)' 
	);

    //update purge timestamp
    ipv_db_query(
        'UPDATE ' . IPV_GLOBAL_SETTINGS .
        ' SET last_data_purge = NOW() WHERE configuration_id = 1'
    );

}

function ipv_db_drop_tables() {

	$query = 'DROP TABLE ' . 
			IPV_EXCEPTION . ', ' .
			IPV_GLOBAL_SETTINGS . ', ' .
			IPV_REQUEST_DETAIL . ', ' .
			IPV_APPEAL;

	ipv_db_query( $query );

	ipv_db_drop_static_tables();
		
}

function ipv_db_create_tables() {

	// redefine constants as variables for convenience in heredocs 

	$ipv_exception_name 		= IPV_EXCEPTION;
	$ipv_global_settings_name 	= IPV_GLOBAL_SETTINGS;
	$ipv_request_detail_name 	= IPV_REQUEST_DETAIL;
	$ipv_appeal_name 			= IPV_APPEAL;

	$err_text = 'Database creation failed';

	$query = <<<EOQ
	# these are the exceptions (initially, actions are either allow (whitelist)
	# or deny (blacklist).  We have separate subcategories for exact match and 
	# for wildcard, since wildcard elements must be processed individually while
	# exact matches can be searched using SQL LIKE.  we expect the number of 
	# exceptions to be small enough to put all in one table

	# mask and type together constitute a unique rule i.e. we enforce that for
	# a given field (for example) we cannot have both a whitelist and blacklist
	# defined by the same mask

	create table $ipv_exception_name ( 
		id int key not null auto_increment,
		action enum( 'allow', 'deny' ),
		mask varchar(200) not null,
		mask_type enum( 'exact', 'wildcard', 'ip_range' ),
		excp_type varchar(55), 
		unique key ( mask, excp_type )
	);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ
	# user appeals 

	create table $ipv_appeal_name ( 
		appeal_id 	int key not null auto_increment,
		timestamp 	timestamp,	
		ip 			varchar(16),
		request_id 	bigint,
		email	  	varchar(128)
	);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ
	# global configuration settings
	create table $ipv_global_settings_name (
		configuration_id int primary key not null auto_increment,
		default_risk_threshold float,
		plugin_is_active bool,
		site_type varchar(32),
		api_key varchar(255),
		api_valid bool,
		api_reason varchar(32),
		api_valid_as_of timestamp,
		receive_update_email bool,
		notification_email varchar(320),
		notification_is_custom bool,
		ipcc_url varchar(1024),
		block_path varchar(1024),
		logo_url varchar(1024),
		stylesheet_url varchar(1024),
		blog_name varchar(1024),
		blog_description varchar(1024),
		detail_record_retention_days int,
		ipv_server_url varchar(255),
		block_msg_general varchar( 4096 ),
		block_msg_proxy varchar( 4096 ),
		block_msg_botnet varchar( 4096 ),
		appeals_enabled bool,
		last_data_purge timestamp
	);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ
	insert into $ipv_global_settings_name values ( 
		null, 
		42, 
		false,
		'default',
		'',
		false, 
		'never', 	
		NULL, 
		true,
		NULL,
		false,	
		NULL,
		NULL,
		NULL,
		NULL,
		NULL,
		NULL,
		30, 
		'http://us.api.ipviking.com/api/', 
		'<h1>Forbidden</h1><p>Your access to this website has been blocked because your IP address has been identified as a potential threat.  If you believe that this is an error, and wish to be allowed access, please contact the site administrator.</p>',
		'<h1>Forbidden</h1><p>Your access to this website has been blocked because you are accessing the Internet through a Proxy that has been identified as a potential threat.  If you believe that this is an error, and wish to be allowed access, please contact the site administrator.</p>',
		'<h1>Forbidden</h1><p>Your access to this website has been blocked because the computer you are using appears to have been hijacked by malicious software.  Please update your antivirus and antimalware software and contact the site administrator to request access if the problem persists.</p>',
		TRUE,
		NOW()
		);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ
	# detail reporting table - raw dump of all IPV risk data. IMPORTANT - 
	# any "internal use" records should start ipv_int as the other columns
	# are automatically extracted as keywords for parsing the response xml
	create table $ipv_request_detail_name (
		ipv_int_request_id bigint key not null auto_increment,
		ipv_int_time timestamp,
		ipv_int_date date,
		ip varchar(16),
		risk_factor float,
		risk_color varchar(16),
		risk_name varchar(16),
		risk_desc varchar(32),
		timestamp timestamp default '1999-01-01 01:00:00',
		factor_entries smallint,
		autonomous_system_number varchar(16),
		autonomous_system_name varchar(255),
		country varchar(255),
		country_code varchar(32),
		region varchar(32),
		region_code varchar(32),
		city varchar(128),
		latitude varchar(16),
		longtitude varchar(16),
		internet_service_provider varchar(255),
		organization varchar(255),
		country_risk_factor float,
		region_risk_factor float,
		ip_resolve_factor float,
		asn_record_factor float,
		asn_threat_factor float,
		bgp_delegation_factor float,
		iana_allocation_factor float,
		ipviking_personal_factor float,
		ipviking_category_factor float,
		ipviking_geofilter_factor float,
		ipviking_geofilter_rule float,
		data_age_factor float,
		search_volume_factor float,
		ipv_int_category_name varchar(128),
		ipv_int_category_id smallint,
		ipv_int_factor_name varchar(128),
		ipv_int_disp bool,
		ipv_int_disp_reason varchar(40)
	);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	/* create time index to support fast IP cacheing direct from this table */
	$query = 
		"create index time_index on $ipv_request_detail_name ( ipv_int_time )";

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	ipv_db_create_static_tables();

}

/* new plugin version schema updates to tables containing user data */

function ipv_db_update_schema() {

	// nothing to do yet

}

/* broken out as separate functions so tables containing "static" data 	*/
/* can be automatically added/deleted/modified with plugin updates 	 	*/

function ipv_db_drop_static_tables() {

	try 
	{
		ipv_db_query( 'DROP TABLE ' . IPV_TOOLTIP );
		ipv_db_query( 'DROP TABLE ' . IPV_SITE_TYPE );
		ipv_db_query( 'DROP TABLE ' . IPV_CACHE );
		ipv_db_query( 'DROP TABLE ' . IPV_CAPTCHA_SERVED );
		ipv_db_query( 'DROP TABLE ' . IPV_CAPTCHA_CACHE );
	}
	catch( PDOException $e ) {};

}

/* broken out as separate functions so tables containing "static" data 	*/
/* can be automatically added/deleted/modified with plugin updates 	 	*/

function ipv_db_create_static_tables() {

	$ipv_cache_name 			= IPV_CACHE;
	$ipv_captcha_served_name	= IPV_CAPTCHA_SERVED;
	$ipv_captcha_cache_name		= IPV_CAPTCHA_CACHE;
	$ipv_site_type_name 		= IPV_SITE_TYPE;
	$ipv_tooltip_name 			= IPV_TOOLTIP;

	$query = <<<EOQ

	create table $ipv_cache_name ( 
		ipv_cache_invalid_time timestamp,
		ipv_captcha_count int,
		ipv_captcha_last int,
		ipv_captcha_first int
	);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = "insert into $ipv_cache_name values ( now(), 100, -1, -1 )";
	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ

	create table $ipv_captcha_cache_name ( 
		id			int,
		captcha		blob,
		response	varchar(32)
	);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ
	create table $ipv_captcha_served_name ( 
		ip		varchar(16) key,
		id		int
	);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ

	create table $ipv_site_type_name ( 
		type_short_name varchar(32), 
		type_display_name varchar(64),
		ipq_level float,
		ipq_level_desc varchar(32),
		type_descriptive_text varchar(255)
	);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ
	insert into $ipv_site_type_name values 
		( 'default', 'Default Site', 42, 'medium-high', 
		  'You will block traffic that is shown to have <ul> <li> significant risk characteristics seen in past 24 hours</li> <li> significant prior risky characteristics seen</li> <li> significant IP address irregularities seen</li> </ul>' 
		), 
		( 'custom', 'Custom Site', 50, 'custom', ''
		), 
		( 'ecommerce', 'eCommerce Store', 33, 'high', 
		  'You will block traffic that is shown to have <ul> <li> some risky characteristics seen in past 24 hours</li> <li> some prior risky characteristics seen</li> <li> some IP address irregularities seen</li> </ul>' 
		), 
		( 'social', 'Social Platform', 52, 'medium', 
		  'You will block traffic that is shown to have <ul> <li> extreme risk indicated due to risk category activities seen in past 24 hours</li> <li> extreme prior risk category activities seen</li> <li> extreme IP address risk behavior seen</li> </ul>' 
		), 
		( 'corporate', 'Corporate Site', 50, 'medium',  
		  'You will block traffic that is shown to have <ul> <li> extreme risk indicated due to risk category activities seen in past 24 hours</li> <li> extreme prior risk category activities seen</li> <li> extreme IP address risk behavior seen</li> </ul>' 
		),
		( 'webapp', 'Web Application', 40, 'medium-high', 
		  'You will block traffic that is shown to have <ul> <li> significant risk characteristics seen in past 24 hours</li> <li> significant prior risky characteristics seen</li> <li> significant IP address irregularities seen</li> </ul>' 
		), 
		( 'blog', 'Blog', 48, 'medium', 
		  'You will block traffic that is shown to have <ul> <li> extreme risk indicated due to risk category activities seen in past 24 hours</li> <li> extreme prior risk category activities seen</li> <li> extreme IP address risk behavior seen</li> </ul>' 
		), 
		( 'marketing', 'Marketing Site', 54, 'medium', 
		  'You will block traffic that is shown to have <ul> <li> extreme risk indicated due to risk category activities seen in past 24 hours</li> <li> extreme prior risk category activities seen</li> <li> extreme IP address risk behavior seen</li> </ul>' 
		)
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ
	# category and factor help text for ajax tooltips
	create table $ipv_tooltip_name (
		id 		varchar(128),
		text	varchar(1024)
	);
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

	$query = <<<EOQ
	insert into $ipv_tooltip_name values 
		( 'Country Risk Factor', 'Relative risk factor associated with the country of record for this IP' ),
		( 'Region Risk Factor', 'Relative risk factor associated with the region from which this IP originates.' ),
		( 'IP Resolve Factor', 'Relative risk factor associated with the domain name and other “reverse lookup” information associated with this IP, both current and historical' ),
		( 'ASN Record Factor', ' For routing purposes, each IP on the Internet is assigned to one of thousands of “autonomous systems” (AS), each with a unique AS Number (ASN).   This factor might appear when the ASN does not match a valid autonomous system, suggesting a forged request.' ),
		( 'ASN Threat Factor', 'For routing purposes, each IP on the Internet is assigned to one of thousands of “autonomous systems” (AS), each with a unique AS Number (ASN).   This factor appears when the entire ASN that this IP belongs to is commonly associated with high-risk activity.' ),
		( 'BGP Delegation Factor', 'BGP (Border Gateway Protocol) is the routing protocol used by the core Internet.  BGP “delegates” routing requests for the specified IP to the Autonomous System to which the IP is assigned.  ' ),
		( 'IANA Allocation Factor', 'IANA (Internet Assigned Numbers Authority) is responsible for allocating IP addresses to Regional Internet Registries (RIR’s), which, in turn, allocate IP addresses to ISP’s and end users.  This factor may appear if an IP has not been allocated, and thus is not a legitimately assigned address.' ),
		( 'IPViking Category Factor', 'This factor appears when the IP has exhibited one or more specific types of risky behavior' ),
		( 'Bogon Unadv', ' A “bogus” IP address that has been assigned by the IANA or a Regional Internet Registry, but has not yet been advertised in the BGP routing tables, and is thus not reachable from the global Internet.' ),
		( 'Bogon Unass', 'A “bogus” IP address that has not been assigned by the IANA or a Regional Internet Registry  to an ISP or end user.' ),
		( 'Proxy', 'An IP address associated with an anonymizer or other Proxy service that indicates likely malicious behavior' ),
		( 'Botnet', 'The IP address has demonstrated behavior consistent with a computer that has been infected by malicious software and is under the control of an attacker.  In most cases the owner of the computer is unaware that it is part of a Botnet.' ),
		( 'Other', 'There are many other categories that may contribute to a high IPQ score, including Child Pornography, CyberTerrorism, Identity Theft, Drugs, Espionage, etc.  ' )
EOQ;

	if ( ipv_db_query( $query ) === FALSE ) {
		trigger_error( $err_text, E_USER_ERROR );
	}

}

?>
