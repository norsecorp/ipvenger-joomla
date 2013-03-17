<?php

// no direct access
defined('_JEXEC') or die;

class plgSystemIPVenger extends JPlugin
{
    function onAfterInitialise()
	{

		$comp_dir = JPATH_ADMINISTRATOR .  '/components/com_ipvenger';

		$validate_include = 
			$comp_dir . "/core-includes/ipv_validate.php";

		if ( file_exists ( $validate_include ) ) {
			require_once( $validate_include );
			ipv_gatekeeper();
		}
		

	}
}

?>
