<?php

// no direct access
defined('_JEXEC') or die;

    /* set/get session variables via Joomla!, else we lose anonymous */
    /* session data */

    function ipv_session_get( $name ) {
        $session = &JFactory::getSession();
        return $session->get( $name );
    }

    function ipv_session_set( $name, $value ) {
        $session = &JFactory::getSession();
        $session->set( $name, $value );
    }

    function ipv_session_isset( $name ) {
        $session = &JFactory::getSession();
        return $session->has( $name );
    }

    function ipv_session_unset( $name ) {
        $session = &JFactory::getSession();
        $session->clear( $name );
    }

?>
