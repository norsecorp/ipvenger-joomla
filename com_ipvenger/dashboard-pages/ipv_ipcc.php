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

	$ipv_auth_token = ipv_session_get( 'ipv_auth_token' );

    require_once( $GLOBALS['ipv_core_includes'] .
        '/ipv_config_utils.php' );

	$appeal_checked = '';
	$thirty_days_ago = strftime('%Y-%m-%d',strtotime('-30 days'));
	$today = date( 'Y-m-d' );
	

	if (isset( $_GET['appeal_only'] )) $appeal_checked = ' checked="checked" ';

	$query = 'SELECT DISTINCT country FROM '  . IPV_REQUEST_DETAIL . 
			 ' WHERE ipv_int_disp = 0 ORDER BY country' ;

	ipv_db_connect();

    $q_result = ipv_db_query( $query );

	$avail_country_options = '';

    $rows = ipv_db_fetch_assoc_list( $q_result );

	foreach( $rows as $row ) {
		$ctry = $row['country'];
		$avail_country_options .= "<option value=\"$ctry\">$ctry</option>";
	}
	
	$avail_disp_options = '';
	$query = 'SELECT DISTINCT ipv_int_disp_reason as disp ' . 
			 'FROM ' . IPV_REQUEST_DETAIL . ' WHERE ipv_int_disp = 0';

    $q_result = ipv_db_query( $query );

    $rows = ipv_db_fetch_assoc_list( $q_result );

	foreach( $rows as $row ) {
		$disp = $row['disp'];
		$avail_disp_options .= "<option value=\"$disp\">$disp</option>";
	}

	$avail_cat_options = '';
	$ipv_request_detail_name = IPV_REQUEST_DETAIL;

	$query = <<<EOQ
		SELECT DISTINCT category FROM 
			(	SELECT DISTINCT ipv_int_category_name AS category
					FROM $ipv_request_detail_name 
					WHERE ipv_int_category_name != "None" 
					AND ipv_int_disp = 0
			UNION 
				SELECT DISTINCT ipv_int_factor_name AS category 
					FROM $ipv_request_detail_name
					WHERE ipv_int_factor_name != "ipviking_category_factor"
					AND ipv_int_disp = 0
			) AS cat_union
EOQ;

    $q_result = ipv_db_query( $query );

    $rows = ipv_db_fetch_assoc_list( $q_result );

	foreach( $rows as $row ) {
		$cat = $row["category"];
		$avail_cat_options .= "<option value=\"$cat\">$cat</option>";
	}

	$out = <<<EOB

<div id="ipv">

	<div id="ipv-ip-dialog" style="display:none;"></div>

    <script type="text/javascript">

		var ipv_ajax_home = "${GLOBALS['ipv_ajax_home']}";

	    var ipv_auth_token = "$ipv_auth_token";

		var ipv_max_count;

		var ipv_ip_bw_action = function(){ ipv_reload_table( false ) };

		// set all the filters to least restrictive option and reload table

		function ipv_reset_filters() {
			jQuery( "#ipv_country" ).val( "all" );	
			jQuery( "#ipv_disposition" ).val( "all" );	
			jQuery( "#ipv_category" ).val( "all" );	
			jQuery( "#ipv_appeal_only" ).attr( "checked", false );

			d = new Date();
			jQuery( "#ipv_end_date" ).datepicker( "setDate", d );

			d.setDate( d.getDate() - 30 );
			jQuery( "#ipv_start_date" ).datepicker( "setDate", d );

			ipv_reload_table( false );
		}

		// reload the table, applying any specified filters.  If "more" is
		// true, this means the user has said "show more data" so the new
		// table will contain additional data rows

		function ipv_reload_table( more ) {

			if ( more ) ipv_max_count += 20;
			else ipv_max_count = 20; 

			jq_parms = { 	ipv_auth_token: ipv_auth_token,
							max_recs: ipv_max_count };
	
			if ( jQuery( "#ipv_appeal_only" ).is( ":checked" ) )
				jq_parms["appeal_only"] = true;

            country_filter = jQuery("#ipv_country option:selected").val();
			if ( country_filter != "all" ) { 
				jq_parms["country_filter"] = country_filter;
			}

            disp_filter = jQuery("#ipv_disposition option:selected").val();
			if ( disp_filter != "all" ) { 
				jq_parms["disp_filter"] = disp_filter;
			}

            category_filter = jQuery("#ipv_category option:selected").val();
			if ( category_filter != "all" ) { 
				jq_parms["category_filter"] = category_filter;
			}

            start_date = jQuery("#ipv_start_date").val();
			if ( start_date !== "" ) {
				jq_parms["start_date"] = start_date;
			}

            end_date = jQuery("#ipv_end_date").val();
			if ( end_date !== "" ) {
				jq_parms["end_date"] = end_date;
			}

			jQuery.post( ipv_ajax_home + 
				"/ipv_gen_request_table.php",
				jq_parms,
				function( data ) {
					jQuery( "#ipv_request_table" ).html( data );
				}
			);
		}

		function ipv_ip_is_valid( ip ) {
			var pattern = /^(([0-9\*]|[1-9][0-9\*]|1[0-9][0-9\*]|2[0-4][0-9\*]|25[0-5\*])\.){3}([0-9\*]|[1-9][0-9\*]|1[0-9][0-9\*]|2[0-4][0-9\*]|25[0-5\*])$/g;
			return pattern.test(ip);
		}

		function ipv_validate_and_set_ip_button( ip ) {

			if ( ipv_ip_is_valid( ip ) )
				jQuery( "#ipv_lookup_ip" ).removeAttr( "disabled" );
			else
				jQuery( "#ipv_lookup_ip" ).attr( "disabled", "disabled" );

		}

		jQuery(document).ajaxComplete( ipv_handle_timeout );

		jQuery(document).ready(

			function() {

				jQuery("#ipv-ip-dialog").dialog( {
					dialogClass: 'ipv',
					autoOpen: false, 
					resizable: false, 
					title:'IP Lookup',
					position: { my: 'top', at: 'top', offset: '0 30' },
					width: 'auto',
					modal: 1
				} );

				jQuery( "#ipv_start_date" ).datepicker( 
					{ 	
						dateFormat: "yy-mm-dd",
						minDate: "-30",
						maxDate: "+0"
					}); 


				jQuery( "#ipv_end_date" ).datepicker(
					{
						dateFormat: "yy-mm-dd",
						minDate: "-30",
						maxDate: "+0"
					});

				ipv_reload_table(false);

				// set up tooltips
				jQuery(".ipv-title-cluetip").cluetip( {
					splitTitle: '|',
					cluetipClass: 'default',
					activation: 'click',
					showTitle: true,
					sticky: true,
					closePosition: 'title'
				});

				// now remove the titles so they don't show on hover
				jQuery(".ipv-title-cluetip").removeAttr('title');

				// attach realtime IP address validation handler
				jQuery("#ip").bind(
					"propertychange keyup input paste",
					function(event) { ipv_validate_and_set_ip_button(
						jQuery( this ).val() ); }
				);

				ipv_validate_and_set_ip_button(
					jQuery( "#ip" ).val() );


			}

		);

		var ip_recs;

		function ipv_add_ip_records(ip)
		{
			/* called by ajax-generated script when "get more" clicked */

			ip_recs += 10;

			jQuery.post( ipv_ajax_home + "/ipv_lookup_ip.php",
				{ 	ipv_auth_token: ipv_auth_token, 
					ip: ip, 
					is_followup: true, 
					max_recs: ip_recs
				},
				function( data ) {
					jQuery( "#ipv-ip-dialog" ).dialog(
						'option', 'title', 'IP Lookup - ' + ip );
					jQuery( "#ipv-ip-dialog" ).html(data);
					jQuery( "#ipv-ip-dialog" ).dialog('open');
				}
			);
				
		}

		function ipv_lookup_ip(ip)
		{
			ip_recs = 10;

			jQuery.post( ipv_ajax_home + "/ipv_lookup_ip.php",
				{ipv_auth_token: ipv_auth_token, ip: ip, max_recs: ip_recs},
				function( data ) {
					jQuery( "#ipv-ip-dialog" ).dialog(
						'option', 'title', 'IP Lookup - ' + ip );
					jQuery( "#ipv-ip-dialog" ).html(data);
					jQuery( "#ipv-ip-dialog" ).dialog('open');
				}
			);
				
		}

    </script>

<div class="ipcc ipvmain">

	<div class="frame-header">
		<img src= "${GLOBALS['ipv_image_url']}/hdr-text/ip_lookup.png">
	</div>

	<div id="ipv-ipcc-lookup-text">
		<h2>
			Look up a specific IP address to see its history on your site.
			<img style="padding-left:10px;vertical-align:text-top" src=
                "${GLOBALS['ipv_image_url']}/tooltip_question.png" alt="?"
			class="ipv-title-cluetip"
			title="IP Lookup||Enter a specific IP address to see details about all requests from that IP in the last 30 days.  The history includes both blocked and allowed requests.  | Note that data is only available for IP Addresses that have actually attempted to access your website."
			>
		</h2>
		<input type="text" id="ip" size=100 />
		<input type="button" class="ipv-secondary"
			id="ipv_lookup_ip" onclick="ipv_lookup_ip(
			jQuery( '#ip' ).val() )" value="Lookup" />
	</div>

</div>

<div class="ipcc ipvmain">
	<div class="frame-header">
        <img src= "${GLOBALS['ipv_image_url']}/hdr-text/ips_blocked.png">
	</div>
	<div class="ipcc-filter-row">
		<div class="ipcc-filter-label">	
			Filter results by:
		</div>
		<div class="filter-container">
            <div class="filter-item">
                <div>Start Date</div>
                <input id="ipv_start_date" onchange="ipv_reload_table(false)"
                    type="text" style="width:100px"
                    value=$thirty_days_ago
                >
            </div>
            <div class="filter-item">
                <div>End Date</div>
                <input id="ipv_end_date" onchange="ipv_reload_table(false)"
                    type="text" style="width:100px"
                    value=$today
                >
            </div>
            <div class="filter-item">
                <div>Country</div>
                <select id="ipv_country" onchange="ipv_reload_table(false)"
                    style="width:100px">
                    <option value="all">All</option>
                    $avail_country_options
                </select>
            </div>
            <div class="filter-item">
                <div>Disposition</div>
                <select id="ipv_disposition" onchange="ipv_reload_table(false)"
                    style="width:100px">
                    <option value="all">All</option>
                    $avail_disp_options
                </select>
            </div>
            <div class="filter-item">
                <div>Category</div>
                <select id="ipv_category" onchange="ipv_reload_table(false)"
                    style="width:100px">
                    <option value="all">All</option>
                    $avail_cat_options
                </select>
            </div>
        </div>
		<div style="float:left;margin:25px 0 10px 10px;">
			<input type="checkbox" id="ipv_appeal_only" 
				onchange="ipv_reload_table(false)" 
				$appeal_checked
			/>
			Appeals Only
		</div>

		<input type="button" class="ipv-secondary" 
			style="float:right;margin:16px 10px 0 0px"
			value="Clear Filters" onclick="ipv_reset_filters()"	/>
	</div>

	<div style="clear:left">
		<!-- table of blocked requests to be managed by the table sorter -->
		<table class="widefat">
		<thead>
		<tr>
			<th class="first">IP Address</th>
			<th>Timestamp</th>
			<th>Country</th>
			<th>Organization</th>
			<th>IPQ</th>
			<th>Disposition</th>
			<th>Category</th>
			<th>Appeal</th>
			<th>White/Blacklist</th>
		</tr>
		</thead>
		
		<tbody id="ipv_request_table"></tbody>

		</table>

		<input type="button" class="ipv-secondary ipv-ipcc"
			value="Show more results" onclick="ipv_reload_table(true)"	
			style="width:100%" />

	</div>

</div>	

</div>

EOB;

return $out;
