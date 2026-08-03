<?php

/**
 * Diagnóstico de salud del cron de sincronización automática.
 * Explica al cliente, sin acceso a servidor, por qué la sincronización
 * automática puede no estar corriendo (WP-Cron depende de visitas HTTP).
 */

function nb_get_cron_health()
{
    $interval_seconds = intval(get_option('nb_sync_interval', 3600));
    $next_scheduled = wp_next_scheduled('nb_cron_sync_event');
    $last_auto_sync = get_option('nb_last_auto_sync');
    $last_manual_sync = get_option('nb_last_manual_sync');
    $nb_user = get_option('nb_user');
    $nb_password = get_option('nb_password');

    $active_plugins = get_option('active_plugins', array());
    $plugin_active = false;
    foreach ($active_plugins as $plugin) {
        if (strpos($plugin, 'woocommerce-newbytes') !== false && strpos($plugin, '.php') !== false) {
            $plugin_active = true;
            break;
        }
    }

    $grace_period = max(900, $interval_seconds * 2);
    $overdue_by_seconds = null;
    $is_overdue = false;
    if ($next_scheduled && time() > $next_scheduled + $grace_period) {
        $is_overdue = true;
        $overdue_by_seconds = time() - $next_scheduled;
    }

    $health = array(
        'severity'              => 'ok',
        'message'                => 'La sincronización automática está funcionando correctamente.',
        'next_scheduled'         => $next_scheduled,
        'next_scheduled_human'   => $next_scheduled ? date_i18n('d/m/Y H:i', $next_scheduled) : null,
        'is_overdue'             => $is_overdue,
        'overdue_by_seconds'     => $overdue_by_seconds,
        'disable_wp_cron'        => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        'alternate_wp_cron'      => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON,
        'interval_seconds'       => $interval_seconds,
        'last_auto_sync'         => $last_auto_sync ?: null,
        'last_manual_sync'       => $last_manual_sync ?: null,
        'plugin_active'          => $plugin_active,
        'credentials_configured' => !empty($nb_user) && !empty($nb_password),
    );

    if (!$health['credentials_configured']) {
        $health['severity'] = 'error';
        $health['message'] = 'No hay credenciales configuradas. La sincronización automática no puede ejecutarse hasta completar el usuario y la contraseña en la pestaña Credenciales.';
    } elseif ($next_scheduled === false) {
        $health['severity'] = 'error';
        $health['message'] = 'No hay ninguna sincronización automática programada. Volvé a cargar esta página; si el aviso persiste, contactá a soporte.';
    } elseif ($health['disable_wp_cron']) {
        $health['severity'] = 'warning';
        $health['message'] = 'El cron automático de WordPress está desactivado en este sitio (DISABLE_WP_CRON). Para que la sincronización corra sola, el hosting debe configurar una tarea cron de servidor que visite wp-cron.php de forma periódica.';
    } elseif ($is_overdue) {
        $ciclos_perdidos = $interval_seconds > 0 ? floor($overdue_by_seconds / $interval_seconds) : 0;
        $health['severity'] = ($overdue_by_seconds > $interval_seconds * 3) ? 'error' : 'warning';
        $health['message'] = sprintf(
            'La sincronización automática debería haberse ejecutado hace %s y no corrió (se perdieron aproximadamente %d ciclo(s)). Esto suele pasar cuando el sitio recibe poco tráfico o el hosting no dispara wp-cron.php. Pedile a tu hosting que configure un cron de servidor real.',
            human_time_diff($next_scheduled, time()),
            $ciclos_perdidos
        );
    }

    return $health;
}

function nb_build_diagnostic_text($health)
{
    $severity_labels = array(
        'ok'      => 'OK',
        'warning' => 'ADVERTENCIA',
        'error'   => 'ERROR',
    );

    $lines = array();
    $lines[] = '=== Diagnóstico NewBytes Conector ===';
    $lines[] = 'Estado: ' . $severity_labels[$health['severity']];
    $lines[] = 'Mensaje: ' . $health['message'];
    $lines[] = 'Próxima sync programada: ' . ($health['next_scheduled_human'] ?: 'no programada');
    $lines[] = 'Última sync automática: ' . ($health['last_auto_sync'] ? human_time_diff(strtotime($health['last_auto_sync'])) . ' atrás' : 'Nunca');
    $lines[] = 'Última sync manual: ' . ($health['last_manual_sync'] ? human_time_diff(strtotime($health['last_manual_sync'])) . ' atrás' : 'Nunca');
    $lines[] = 'Intervalo configurado: cada ' . round($health['interval_seconds'] / 3600, 1) . ' hora(s)';
    $lines[] = 'DISABLE_WP_CRON activo: ' . ($health['disable_wp_cron'] ? 'sí' : 'no');
    $lines[] = 'Fecha del reporte: ' . date_i18n('d/m/Y H:i');

    return implode("\n", $lines);
}

function nb_ajax_refresh_cron_health()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nb_sync_nonce')) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Sin permisos'));
    }

    try {
        $health = nb_get_cron_health();
        $health['diagnostic_text'] = nb_build_diagnostic_text($health);
        wp_send_json_success($health);
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_nb_refresh_cron_health', 'nb_ajax_refresh_cron_health');
