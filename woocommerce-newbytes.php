<?php
/*
Plugin Name: Conector NewBytes
Description: Sincroniza los productos del catálogo de NewBytes con WooCommerce.
Author: NewBytes
Author URI: https://nb.com.ar
Version: 0.1.9
*/

// remover usar prod
define('API_URL_NB', 'https://api.nb.com.ar/v1');
define('VERSION_NB', '0.1.9');

// Incluye los archivos necesarios
require_once plugin_dir_path(__FILE__) . 'includes/admin-hooks.php';
require_once plugin_dir_path(__FILE__) . 'includes/cron-hooks.php';
require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/utils.php';
require_once plugin_dir_path(__FILE__) . 'includes/product-sync.php';
require_once plugin_dir_path(__FILE__) . 'includes/product-delete.php';
require_once plugin_dir_path(__FILE__) . 'includes/modals.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/logs-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/logs-page.php';

// Hooks de activación y desactivación

add_action('wp_ajax_nb_update_description_products', 'nb_update_description_products');
add_action('wp_ajax_nb_delete_products', 'nb_delete_products');
add_action('wp_ajax_nb_get_log_data', 'nb_get_log_data');
add_action('wp_ajax_nb_download_log', 'nb_download_log');
add_action('wp_ajax_nb_cleanup_logs', 'nb_cleanup_logs');
add_action('admin_post_nb_delete_products', 'nb_delete_products');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'nb_plugin_action_links');
add_action('admin_menu', 'nb_menu');
add_action('admin_init', 'nb_register_settings');

register_activation_hook(__FILE__, 'nb_activation');
register_deactivation_hook(__FILE__, 'nb_deactivation');
register_uninstall_hook(__FILE__, 'nb_uninstall');
