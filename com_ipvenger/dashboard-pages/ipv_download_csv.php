<?php

// no direct access
defined('_JEXEC') or die;

/**
 *
 *	ipv_download_csv.php:  download ipv_request_details table as CSV
 *
 *  Single GET variable arg, the number of days to go back
 *
 * 		days:	int number of days to "go back"
 *
*/

	require_once( dirname( __FILE__ ) .  
		'/../cms-includes/ipv_db_utils.php' );

    require_once( dirname( __FILE__ ) .
        '/../cms-includes/ipv_session.php' );

	if ( ! ipv_session_isset( 'ipv_is_admin' ) || 
		! ipv_session_get( 'ipv_is_admin' ) )
	{
		die( 'Unauthorized' );   
	}

	$outstream = fopen('php://output','w');

	header('Content-type: text/csv');
	header('Content-disposition: attachment; filename=ipv_request_data.csv');

	/* first get the columns */

	$ipv_request_detail_name =  IPV_REQUEST_DETAIL;
	$q_str = <<<EOQ
	SELECT column_name
	  FROM INFORMATION_SCHEMA.COLUMNS
	  WHERE table_name = '$ipv_request_detail_name'
	  ORDER BY ordinal_position
EOQ;

	ipv_db_connect();

	$q_result = ipv_db_query( $q_str );

    $raw_row = ipv_db_fetch_assoc_list( $q_result );

	foreach ( $raw_row as $key => $val ) {
		$row[] = $val['column_name'];
	}

	fputcsv($outstream, $row, ',', '"');  

	$date_limit = '';
	if ( isset( $_GET['days'] ) ) {
		$days = intval( $_GET['days'] );
		$date_limit = 
			"WHERE ipv_int_date > date_sub( curdate(), INTERVAL $days DAY ) ";
	}

	$q_str = ' SELECT * FROM ' . IPV_REQUEST_DETAIL . " $date_limit ";

	$q_result = ipv_db_query( $q_str );
    $raw_rows = ipv_db_fetch_assoc_list( $q_result );

	foreach ( $raw_rows as $raw_row ) {
		$row = array();
		foreach ( $raw_row as $key => $val ) {
			$row[] = $val;
		}
		$rows[] = $row;
	}

	$bytes = count( $rows ) * 200;

	$nrows = 0;
	foreach( $rows as $row ) {
		$nrows++;
		fputcsv($outstream, array_values( $row ), ',', '"');  
	}

	fclose($outstream); 

	exit();

?>
