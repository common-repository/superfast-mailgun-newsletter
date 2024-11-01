<?php

/*
 Plugin Name: Superfast Mailgun for the Newsletter plugin
 Plugin URI: http://howfrankdidit.com/superfast-newsletters-with-mailgun
 Description: Integrates Newsletter with Mailgun API service.
 Version: 1.2.5
 Author: franciscus
 Author URI: http://howfrankdidit.com
 Disclaimer: Use at your own risk. No warranty expressed or implied is provided.
(c) Copyright 2021 Frank Meijer
*/
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( function_exists( 'sfmailgun_fs' ) ) {
    sfmailgun_fs()->set_basename( false, __FILE__ );
} else {
    // DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
    
    if ( !function_exists( 'sfmailgun_fs' ) ) {
        // Create a helper function for easy SDK access.
        function sfmailgun_fs()
        {
            global  $sfmailgun_fs ;
            
            if ( !isset( $sfmailgun_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                $sfmailgun_fs = fs_dynamic_init( array(
                    'id'             => '6065',
                    'slug'           => 'superfast-mailgun-newsletter',
                    'premium_slug'   => 'superfast-mailgun-newsletter-pro',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_d4b4790aa6595b5e978b26de4fb67',
                    'is_premium'     => false,
                    'premium_suffix' => 'Pro',
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'menu'           => array(
                    'slug'    => 'newsletter_sfmailgun_index',
                    'account' => true,
                    'contact' => false,
                    'support' => false,
                    'parent'  => array(
                    'slug' => 'newsletter_main_index',
                ),
                ),
                    'is_live'        => true,
                ) );
            }
            
            return $sfmailgun_fs;
        }
        
        // Init Freemius.
        sfmailgun_fs();
        // Signal that SDK was initiated.
        do_action( 'sfmailgun_fs_loaded' );
    }
    
    // Poll hook name
    define( 'SFMAILGUN_POLL_HOOK', 'sfmailgun_poll' );
    add_action( 'newsletter_loaded', function ( $version ) {
        
        if ( $version < '7.8.0' ) {
            add_action( 'admin_notices', function () {
                echo  '<div class="notice notice-error"><p>Newsletter plugin upgrade required for Superfast Mailgun Addon.</p></div>' ;
            } );
        } else {
            include __DIR__ . '/plugin.php';
            new SuperfastMailgunNewsletter( '1.2.5' );
        }
    
    } );
    register_deactivation_hook( __FILE__, function () {
        wp_clear_scheduled_hook( SFMAILGUN_POLL_HOOK );
    } );
}
