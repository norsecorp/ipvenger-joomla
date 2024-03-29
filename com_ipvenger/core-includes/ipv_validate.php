<?php

// no direct access
defined('_JEXEC') or die;

/**
 * function ipv_validate( $ip, args ) validate incoming IP
 *
 * validate the input IP against local exception lists and Norse IP Viking
 * and log the request to the reporting database
 *
 * @param   string 	$ip					address to validated
 * @param	boolean	&$allow				true: allow request, false: deny 
 * @param	string	&$terse_reason		short reason for allow/deny
 * @param	int		&$risk_factor		IPQ score returned by IPV api
 * @param	string	&$risk_color		risk color from IPV api 
 * @param	string	&$country			country of origin (IPV api)
 * @param	string	&$primary_factor	highest weight factor affecting IPQ
 * @param	string	&$primary_category_name		highest weight category in IPQ
 * @param	string	&$request_id		database key for this insert
 * @param	string	$insert_request		"insert": update database, otherwise no
 * @param	string	$insert_time		"actual_time" "random_time" or specified
 * 										(unix) time to use for database insert
 *
 * @return 	int	 	http status code of response from IP Viking
 *
*/

require_once( 'ipv_call_ipv_api.php' );
require_once( 'ipv_config_utils.php' );

require_once( dirname( __FILE__ ) . '/../cms-includes/ipv_db_utils.php' );
require_once( dirname( __FILE__ ) . '/../cms-includes/ipv_session.php' );

function ipv_validate( 
	$ip, 
	&$allow, 
	&$terse_reason, 
	&$risk_factor, 
	&$risk_color, 
	&$country, 
	&$primary_factor, 
	&$primary_category_name, 
	&$request_id, 
	$insert_request='insert',
	$insert_time='actual_time' ) 
{

		$done = false;

		// ie $ip = "random" generate a random ip address and a random
		// insertion time - to populate database for testing reports

		if ( $ip == 'random' ) {
			$ip = rand( 1, 255 ) . '.' . rand( 0,255 ) . '.' .
				rand( 0, 255 ) . '.' . rand( 0, 255 );
		}

		ipv_db_connect();

		$allow = false;
		$primary_factor_name = 'None';
		$primary_category_name = 'None';
		$primary_category_id = '-1';

		$q_result = ipv_db_query( 
			'SELECT ipv_server_url, api_key, api_valid ' . 
			'FROM ' . IPV_GLOBAL_SETTINGS );

		if ( ! $q_result )  $status = 5000;
		else {

			extract( ipv_db_fetch_assoc( $q_result ) );

			$result = ipv_call_ipv_api( 
					$ipv_server_url, $api_key, $ip, $status );

		}

		// deal with IPV status codes 

		switch ( $status ) {

			case 302: break;		// success - process as normal

			case 503:				// IP Viking down for maint. - allow
				$allow = true;
				$reason = $terse_reason = 'IPV unavailable';
				$done = true;
				break;

			case 417:				// Invalid IP address - disallow
				$allow = true;	
				$reason = $terse_reason = 'Local Network';
				$done = true;
				break;

			case 204:				// request ok but no info on IP - allow
				$allow = true;
				$reason = $terse_reason = 'No info';
				$done = true;
				break;

			// api key errors - allow 
			case 400:				
			case 401:
			case 402:
				$allow = true;
				$reason = $terse_reason = "API Key err $status";
				$done = true;
				break;

			case 5000:
				$allow = true;
				$reason = $terse_reason = 'Database Error';
				$done = true;
				break;

			// any other error is an internal error or problem at IPV
			default:
				$allow = true;
				$reason = $terse_reason = "Error $status";
				$done = true;
				break;
			
		}
		
		if ( ! $done ) {

			$result = iconv('UTF-8','UTF-8//IGNORE',$result);	// force UTF-8

			// we sometimes get valid xml and sometimes get raw ampersand
			// work around this

			$result = str_replace('&amp;', '&', $result);
			$result = str_replace('&', '&amp;', $result);

			$risk = @simplexml_load_string( $result );			// parse

			if ( $risk == false ) {
				$allow = true;
				$reason = $terse_reason = "Can't parse API response";
			}
			else {

				// get column names from the detail record except internal ones
				$q_result = ipv_db_query( 
					'SELECT column_name FROM information_schema.columns ' .
					'WHERE table_name = "' . IPV_REQUEST_DETAIL . '" ' . 
					'and column_name NOT LIKE \'ipv_int_%\' ' );

				// for each name, pull the data out of the xml with xpath
				// building up the insert sql as we go

				$primary_factor_value = -100;
			
				$rows = ipv_db_fetch_assoc_list( $q_result );

				foreach( $rows as $row ) {

					list( $col, $name ) = each($row) ;

					// the complete list
					$ar = $risk->xpath("//$name");

					$name = ipv_db_escape_string( $name );

					$values[$name] =
						"'" . ipv_db_escape_string( array_pop($ar) ) . "'";

					// and save the largest factor going toward the risk
					$ar = $risk->xpath("//factoring/reason/$name");
					if ( count( $ar ) > 0 ) {

						$val = array_pop($ar);

						if ( (float) $val > (float) $primary_factor_value ) {
							$primary_factor_value = $val;
							$primary_factor_name = $name;
						}

					}
				}

				$risk_factor = substr( $values['risk_factor'], 1, -1 );

				$risk_color = $values['risk_color'];
				$country = $values['country'];
			
				// convert ISO 8601 timestamp to SQL format (drop timezone)
				$values['timestamp']  = 
					substr( $values['timestamp'], 0, -7 ) . "'";

				// if there are category details, find the highest weighted
				$arn = $risk->xpath('//entries/reason/category_name');
				$ari = $risk->xpath('//entries/reason/category_id');
				$arf = $risk->xpath('//entries/reason/category_factor');

				$primary_category_factor = 0;
				$primary_category_name = 'None';
				foreach( $arn as $name ) {
					list( $col, $id ) = each($ari) ;
					list( $col, $factor ) = each($arf) ;
					if ( $factor >= $primary_category_factor ) {
						$primary_category_name = $name;
						$primary_category_id = $id;
						$primary_category_factor = $factor;
					}
				}

				$primary_category_name = 
					ipv_db_escape_string( $primary_category_name );

				$primary_category_id = 
					ipv_db_escape_string( $primary_category_id );

				$primary_category_factor = 
					ipv_db_escape_string( $primary_category_factor );

				// check blacklists first, then IPQ score

				if ( ipv_ip_is_blacklisted( $ip ) ) {
					$allow = false;
					$reason = $terse_reason = 'IP Blacklist';
				}
				else if ( ipv_country_is_blacklisted( $country ) ) {
					$allow = false;
					$reason = $terse_reason = 'Country Blacklist';
				}
				else if ( $risk_factor > ipv_get_default_risk() ) {
					$allow = false;
					$reason = $terse_reason = 'IPQ Score';
				}
				else { 
					$allow = true;
					$reason = $terse_reason = 'IPQ Score';
				}

			}

		}

		if ( $allow ) $disp = 1;
		else $disp = 0;

		// build the insertion statement to put this record in the db
		// (if there is anything to insert)

		if ( isset( $values ) && ( $insert_request == 'insert' ) ) {

			if ( $insert_time == 'actual_time' ) {
					$values['ipv_int_date'] = 'CURRENT_DATE';
			}
			else {

				if ( $insert_time == 'random_time' ) {
					// if this is a random ip address - set the time to 
					// something random in the last 10 days
					$t = strtotime( trim( $values['timestamp'], '"' ) );
					$t -= rand( 0, 10*24*60*60 );
					$values['ipv_int_time'] = '"' . 
						date( 'Y-m-d H:i:s', $t ) . '"';
					$values['ipv_int_date'] = '"' . 
						date( 'Y-m-d', $t ) . '"';
				}
				else {
					// otherwise timestamp is the user specified time
					$t = strtotime( $insert_time );
					$values['ipv_int_time'] = '"' . 
						date( 'Y-m-d H:i:s', $t ) . '"';
					$values['ipv_int_date'] = '"' . 
						date( 'Y-m-d', $t ) . '"';
				}
			}	

			$insert_sql = 'INSERT INTO ' . IPV_REQUEST_DETAIL . ' ( ' . 
                implode( array_keys( $values ), ',' ) .
				', ipv_int_category_name, ipv_int_category_id, ' . 
				' ipv_int_factor_name, ipv_int_disp, ' .
				' ipv_int_disp_reason ) VALUES ( ' . 
                implode( array_values( $values ), ',' ) .
				", '$primary_category_name', '$primary_category_id' " . 
				", '$primary_factor_name', $disp, '$terse_reason' )";
			
			$q_result = ipv_db_query( $insert_sql );

			$request_id = ipv_db_insert_id();

		}

		$primary_factor = $primary_factor_name;

		ipv_db_cleanup();

		return $status;
}

/**
 *  function ipv_gatekeeper()
 *
 *  allow or deny the request - if ipviking allows the request just return,
 *  otherwise  put up the error page and die
 * 
 * 
*/
function ipv_gatekeeper() { 

	if ( defined( 'IPV_IN_AJAX' ) || ! ipv_plugin_is_active() ) return;

	if ( isset($_SERVER['REMOTE_ADDR']) ) {

		$ip =  $_SERVER['REMOTE_ADDR'];

		ipv_session_set( 'ipv_ip', $ip );

		// we only call the api once per session 
		// expire non-admin sessions according to schedule 

		if ( ipv_session_get( 'ipv_status' ) && 
			 ! ipv_session_get( 'ipv_is_admin' ) ) {

			$create_time = ipv_session_get( 'ipv_create_time' );

			// expire session after 48 hours to force api call (48h = 172800s),
			// or if older than the last cache flush but not an appeal

			if ( time() - $create_time > 172800 )
			{
				ipv_session_unset( 'ipv_status' );
			}
			else if ( $create_time < ipv_cache_last_invalidated() )
			{ 
				ipv_session_unset( 'ipv_status' );
			}

		}

		if ( ipv_session_isset( 'ipv_status' ) ) {

			if ( ipv_session_get( 'ipv_status' ) == 'allow' ) {
				return;	
			}
			else  {
				ipv_reject( $ip );
			}

		}
		else {

			// first check short-term cache and whitelist to minimize 
			// redundant and undesirable API calls, e.g. whitelisted
			// monitoring service "pings" should not cause an API call 

			if ( ipv_ip_is_whitelisted( $ip ) ) {
				$allow = true;
			}
			else if ( ipv_ip_is_cached( $ip, $cached_status, $id ) ) 
			{
					$allow = (bool) $cached_status;
			} 
			else {

				$rc = ipv_validate( $ip,
									 $allow,
									 $t, $rf, $rclr, $c, $pf, $pcn, $id );

				ipv_session_set( 'ipv_disposition', $t );

				if ( stripos( $pcn, 'botnet' ) !== false ) {
					$msg_type = 'botnet';
				}
				else if ( stripos( $pcn, 'proxy' ) !== false ) {
					$msg_type = 'proxy';
				}
				else $msg_type = 'general';

				ipv_session_set( 'ipv_msg_type', $msg_type );
				ipv_session_set( 'ipv_request_id', $id );

			}

			if ( $allow ) {
				ipv_session_set( 'ipv_status', 'allow' );
				ipv_session_set( 'ipv_create_time', time() );
				return;
			}
			else {
				ipv_session_set( 'ipv_status', 'deny' );
				ipv_session_set( 'ipv_create_time', time() );
				ipv_reject( $ip );
			}

		}

	}

	ipv_reject( "Unknown IP" );

}

function ipv_reject( $ip ) {

	require_once( dirname( __FILE__ ) .  
		'/../cms-includes/ipv_block_page.php' );

	if ( ipv_session_isset( 'ipv_request_id' ) ) {
		$request_id = ipv_session_get( 'ipv_request_id' );
	}
	else {
		$request_id = -1;
	}

	if ( ipv_session_isset( 'ipv_msg_type' ) ) {
		$msg_type   = ipv_session_get( 'ipv_msg_type' );
	}
	else {
		$msg_type = 'general';
	}

	if ( ipv_session_isset( 'ipv_disposition' ) ) {
		$disp = ipv_session_get(  'ipv_disposition' );
	}
	else {
		if ( ipv_ip_is_blacklisted( $ip ) ) $disp = "IP Blacklist";
		else $disp = "IP Cache";
	} 

	ipv_echo_block(
		$request_id, 
		$msg_type,
		$disp,
		$ip,
		NULL
	);

	die();

}
?>
