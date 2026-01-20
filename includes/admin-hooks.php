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
    add_management_page('NewBytes Logs', 'NewBytes Logs', 'manage_options', 'nb-logs', 'nb_logs_page');
}

/**
 * Encolar estilos CSS solo en las páginas del plugin
 * Evita conflictos con otros plugins
 */
function nb_enqueue_admin_styles($hook) {
    // Solo cargar en nuestras páginas
    if ($hook !== 'settings_page_nb' && $hook !== 'tools_page_nb-logs') {
        return;
    }

    // Nuevo diseño Vercel-inspired
    wp_enqueue_style(
        'nb-admin-styles-v2',
        plugin_dir_url(__FILE__) . '../assets/admin-styles-v2.css',
        array(),
        VERSION_NB . '.2'
    );
}
add_action('admin_enqueue_scripts', 'nb_enqueue_admin_styles');

function nb_register_settings()
{
    register_setting('nb_options', 'nb_user');
    register_setting('nb_options', 'nb_password');
    register_setting('nb_options', 'nb_token');
    register_setting('nb_options', 'nb_prefix');
    register_setting('nb_options', 'nb_sync_no_iva');
    register_setting('nb_options', 'nb_sync_usd');
    register_setting('nb_options', 'nb_description');
    register_setting('nb_options', 'nb_sync_interval');
}

function nb_activation()
{
    nb_update_cron_schedule();
    
    // Crear directorios necesarios con permisos correctos
    nb_create_plugin_directories();
}

/**
 * Crea los directorios necesarios para el plugin con permisos de escritura
 */
function nb_create_plugin_directories()
{
    $plugin_dir = plugin_dir_path(__FILE__) . '../';
    
    $directories = array(
        $plugin_dir . 'nb-products/',
        $plugin_dir . 'logs-sync-nb/',
        $plugin_dir . 'nb-descriptions/'
    );
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            // Crear directorio con permisos 0755
            if (!mkdir($dir, 0755, true)) {
                wp_mkdir_p($dir);
            }
        }
        
        // Asegurar permisos de escritura
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0755);
            if (!is_writable($dir)) {
                @chmod($dir, 0775);
            }
        }
        
        // Crear archivo index.php de protección
        $index_file = $dir . 'index.php';
        if (!file_exists($index_file) && is_writable($dir)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }
    
    // Crear .htaccess en logs-sync-nb para protección adicional
    $htaccess_file = $plugin_dir . 'logs-sync-nb/.htaccess';
    if (!file_exists($htaccess_file) && is_writable($plugin_dir . 'logs-sync-nb/')) {
        file_put_contents($htaccess_file, "Deny from all\n");
    }
}

function nb_deactivation()
{
    // Limpiar agresivamente todos los eventos programados
    $timestamp = wp_next_scheduled('nb_cron_sync_event');
    
    // Método 1: Desprogramar el próximo evento
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'nb_cron_sync_event');
    }
    
    // Método 2: Limpiar TODOS los eventos de este hook (por si hay múltiples)
    wp_clear_scheduled_hook('nb_cron_sync_event');
    
    // Método 3: Verificar y limpiar cualquier evento residual en el cron array
    $cron_array = _get_cron_array();
    if (is_array($cron_array)) {
        foreach ($cron_array as $timestamp => $cron) {
            if (isset($cron['nb_cron_sync_event'])) {
                unset($cron_array[$timestamp]['nb_cron_sync_event']);
                if (empty($cron_array[$timestamp])) {
                    unset($cron_array[$timestamp]);
                }
            }
        }
        _set_cron_array($cron_array);
    }
    
    // Log de desactivación
    error_log('[NewBytes] Plugin desactivado - Todos los cron events eliminados: ' . date('Y-m-d H:i:s'));
}

/**
 * Función de desinstalación completa
 * Se ejecuta cuando el plugin es eliminado completamente desde WordPress
 * Limpia TODAS las opciones, cron events y datos del plugin
 */
function nb_uninstall()
{
    // 1. Limpiar todos los cron events (triple verificación)
    wp_clear_scheduled_hook('nb_cron_sync_event');
    
    $cron_array = _get_cron_array();
    if (is_array($cron_array)) {
        foreach ($cron_array as $timestamp => $cron) {
            if (isset($cron['nb_cron_sync_event'])) {
                unset($cron_array[$timestamp]['nb_cron_sync_event']);
                if (empty($cron_array[$timestamp])) {
                    unset($cron_array[$timestamp]);
                }
            }
        }
        _set_cron_array($cron_array);
    }
    
    // 2. Eliminar todas las opciones del plugin
    delete_option('nb_user');
    delete_option('nb_password');
    delete_option('nb_token');
    delete_option('nb_prefix');
    delete_option('nb_sync_no_iva');
    delete_option('nb_sync_usd');
    delete_option('nb_description');
    delete_option('nb_sync_interval');
    delete_option('nb_last_update');
    
    // 3. Limpiar transients relacionados (si existen)
    delete_transient('nb_api_token');
    delete_transient('nb_sync_status');
    
    // 4. Limpiar logs (opcional - comentado por si el usuario quiere mantener historial)
    // $logs_dir = plugin_dir_path(__FILE__) . '../logs-sync-nb/';
    // if (is_dir($logs_dir)) {
    //     $files = glob($logs_dir . '*');
    //     foreach ($files as $file) {
    //         if (is_file($file)) {
    //             unlink($file);
    //         }
    //     }
    //     rmdir($logs_dir);
    // }
    
    // 5. Log de desinstalación
    error_log('[NewBytes] Plugin DESINSTALADO completamente - Todas las opciones y cron events eliminados: ' . date('Y-m-d H:i:s'));
    
    // 6. Nota: Los productos de WooCommerce NO se eliminan automáticamente
    // El usuario debe eliminarlos manualmente si lo desea
}

// Los hooks se registran en el archivo principal woocommerce-newbytes.php
// para evitar duplicados y problemas de plugin_basename
