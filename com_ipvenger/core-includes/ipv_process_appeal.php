<?php

/**
 *
 *	ipv_process_appeal.php:  validate appeal request captcha and email
 *
 *	Takes a args as POST variables:
 *
 *		email:  			user email to be mx-validated
 *		captcha_response: 	user response to captcha challenge
 *		ip: 				ip of origin for the appeal
 *		email: 				appealing user's email address
 *		
 *	Returns json encoded array of booleans [ "email_valid", "captcha_valid" ];
 *		
*/

	define( 'IPV_IN_AJAX', true );

	if ( ! isset(
		$_POST['captcha_response'],
		$_POST['request_id'],
		$_POST['email'] ) )
	{
		die( 'Unauthorized' );
	}


	$captcha_response 	= strtolower( $_POST['captcha_response'] );

	$ip 				= $_SERVER['REMOTE_ADDR'];
	$request_id 		= $_POST['request_id'];
	$email 				= $_POST['email'];

	/* "site" or "administrator" are handled differently */

	if ( isset( $_POST['site'] ) && $_POST['site'] == 'site' )
		$GLOBALS['ipv_site'] = 'site';

	require_once( '../cms-includes/ipv_cms_workarounds.php' );
	require_once( '../cms-includes/ipv_session.php' );

    // no direct access
    defined('_JEXEC') or die;

	$return_to = ipv_session_get( 'ipv_return_to' );

	/* validate email format and DNS domain record */

	require_once( 'ipv_validate_email.php' );

	$email_valid = ipv_validate_email( $email );

	/* validate captcha image */

	$captcha_valid = false;

	if ( ipv_session_get( 'ipv_captcha_text' ) == $captcha_response )
	{
		$captcha_valid = true;
	}

	/* if email and captcha pass, insert the appeal and send notify email */

	if ( $email_valid && $captcha_valid ) {

		// allow access for the remainder of this user session
		ipv_session_set( 'ipv_status', 'allow' );
		ipv_session_set( 'ipv_appeal', 'appeal' );
		ipv_session_set( 'ipv_create_time', time() );

		require_once( dirname( __FILE__ ) .
			'/../cms-includes/ipv_db_utils.php' );

		require_once( 'ipv_validate.php' );
		require_once( 'ipv_config_utils.php' );

		ipv_db_connect();

		$request_id = intval( $request_id );

		$q_str = 'SELECT appeal_id FROM '  . IPV_APPEAL .
			" WHERE request_id=$request_id";

		$q_result = ipv_db_query( $q_str );

		echo <<<EOR
		<meta http-equiv="Refresh" content="2; url=$return_to">
EOR;

		if ( ( $q_result ) && ( $row = ipv_db_fetch_assoc( $q_result ) ) )
		{
			echo 'Your appeal (' . $row['appeal_id'] .
				') is on file for review by the site administrator.';
		}
		else {

			echo 'Your appeal has been submitted, and temporary access ' .
				'granted.  Returning you to the site...';

			$ip         = ipv_db_escape_string( $ip );
			$email      = ipv_db_escape_string( $email );

			$insert_sql = 'INSERT INTO ' . IPV_APPEAL . ' VALUES ' .
				"( NULL, now(), '$ip', $request_id, '$email' )";

			$q_result = ipv_db_query( $insert_sql );

			if ( ! $q_result ) echo "appeal insert failed - $insert_sql";

			$admin_address  = ipv_get_admin_email();
			$ipcc_url 		= ipv_get_ipcc_url();

			$query = 'SELECT ipv_int_disp_reason as reason, ' .
						'ipv_int_factor_name as factor, ' .
						'ipv_int_category_name as category ' .
					 'FROM ' . IPV_REQUEST_DETAIL .
					 " WHERE ipv_int_request_id = $request_id ";

			$q_result = ipv_db_query( $query );

			$row = ipv_db_fetch_assoc( $q_result );

			$reason = $row['reason'];

			ipv_db_cleanup();

			$cat_str = '';

			if ( $reason == 'IPQ Score' ) {
				$category = $row['factor'];
				if ( $category == 'ipviking_category_factor' )
					$category = $row['category'];
				$cat_str = "Category:		$category\n";
			}

			$message = <<<EOM
<html><body>
You are receiving this email because a visitor was blocked from accessing
your website by IPVenger and has appealed this action. The visitor supplied
a valid email address and CAPTCHA challenge response and has been granted
temporary access to your site.<p>

<strong>Details:</strong>

<pre style="font-size:120%">
IP Address:	$ip
Block Reason:	$reason
${cat_str}Visitor Email:	<a href="mailto:$email">$email</a>
</pre>

This user's access will automatically expire within 48 hours and they
may appeal again at that time.<p>

If you choose to take action, you can whitelist this visitor's IP address,
or blacklist it and prevent further appeals from the
<a href="$ipcc_url">IPVenger IP Control Panel.</a>
<p>

</body></html>
EOM;

			mail( $admin_address,
			  'IPVenger Appeal Notification',
			  $message,
			  'Content-type: text/html' );

		}
	}
	else {
		echo <<<EOM
<script type="text/javascript">
alert( "A valid email address and CAPTCHA response are required to submit an appeal." );
</script>
<meta http-equiv="Refresh" content="0; url=$return_to">
EOM;

	}

?>
