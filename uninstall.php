<?php

/*  Uninstall file for the Instapaper Liked Article Posts Plugin

    The only settings that we add when Instapaper Liked Article Posts
    is in use are under the option name 'ilap_options'. Not much to
    do for cleanup, but here it is. */

/*  Check to make sure this file has been called by WordPress and
    not through any kind of direct link. */
if( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit();

/*  Delete the ilap_options */
delete_option( 'ilap_options' );

?>
