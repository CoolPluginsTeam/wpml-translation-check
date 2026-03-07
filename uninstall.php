<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (get_option('dtc_options') != false) {
    delete_option('dtc_options');
}