<?php
/*
 * Plugin Name: WPML Translation Check
 * Plugin URI: http://www.debelop.com/wpml-translation-check
 * Description: Detects the language of your posts, allowing to check the status of your translations at a glance.
 * Author: Debelop
 * Author URI: http://debelop.com
 * Version: 1.1.2
 * Plugin Slug: wpml-translation-check
 */


include( 'inc/functions.php' );


function dtc_setup_options()
{
    if (version_compare(get_bloginfo('version'), '3.1', '<')) {
        wp_die("You must update WordPress to use this plugin!");
    }
    if (!defined('ICL_SITEPRESS_VERSION')) {
        wp_die("You must have the WPML plugin installed and active in order to use this plugin!");
    }
    if (get_option('dtc_options') === false) {
        $options = array(
            'api_key' => 'TEST',
            'types' => array('post', 'page'),
            'detect_default_lang' => false,
        );
        add_option('dtc_options', $options);
    } else {
        $options = get_option('dtc_options');
        if (!isset($options['detect_default_lang'])) {
            $options['detect_default_lang'] = false;
            update_option('dtc_options', $options);
        }
    }
}

register_activation_hook(__FILE__, 'dtc_setup_options');


// Action & Filter Hooks
add_action('admin_menu', 'dtc_add_admin_menu');
add_action('admin_init', 'dtc_init_register_settings');
add_action('admin_enqueue_scripts', 'dtc_enqueue');
add_action('wp_ajax_send_texts', 'dtc_send_texts');
add_action('admin_notices', 'dtc_admin_notice' );
