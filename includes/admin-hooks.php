<?php

function nb_plugin_action_links($links)
{
    $settings = '<a href="' . get_admin_url(null, 'options-general.php?page=nb') . '">Ajustes</a>';
    array_unshift($links, $settings);
    return $links;
}

function nb_menu()
{
    add_options_page('Conector NB', 'Conector NB', 'manage_options', 'nb', 'nb_options_page');
}

function nb_register_settings()
{
    register_setting('nb_options', 'nb_user');
    register_setting('nb_options', 'nb_password');
    register_setting('nb_options', 'nb_token');
    register_setting('nb_options', 'nb_prefix');
    register_setting('nb_options', 'nb_description');
    register_setting('nb_options', 'nb_sync_interval');
}

function nb_activation()
{
    nb_update_cron_schedule();
}

function nb_deactivation()
{
    $timestamp = wp_next_scheduled('nb_cron_sync_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'nb_cron_sync_event');
    }
}

add_action('admin_menu', 'nb_menu');
add_action('admin_init', 'nb_register_settings');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'nb_plugin_action_links');
