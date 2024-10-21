<?php


function nb_delete_products()
{
    global $wpdb;

    try {
        $original_max_execution_time = ini_get('max_execution_time');
        $original_memory_limit       = ini_get('memory_limit');

        ini_set('max_execution_time', '1800'); // 30 minutos
        ini_set('memory_limit', '2048M'); // 2 GB

        $start_time = microtime(true); // Tiempo de inicio del proceso

        $prefix = get_option('nb_prefix');
        if (!$prefix) {
            wp_send_json_error('No se encontró el prefijo del SKU.');
            return;
        }

        # Eliminar productos con el prefijo especificado
        $deleted_count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->posts} 
                 WHERE post_type = 'product' 
                 AND post_status = 'publish' 
                 AND ID IN (
                     SELECT post_id FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_sku' 
                     AND meta_value REGEXP %s
                 )",
                '^' . $prefix
            )
        );

        update_option('nb_last_update', date("Y-m-d H:i"));

        $end_time      = microtime(true); // Tiempo de finalización del proceso
        $sync_duration = $end_time - $start_time;

        $hours   = floor($sync_duration / 3600);
        $minutes = floor(($sync_duration % 3600) / 60);
        $seconds = $sync_duration % 60;

        $response_data = array(
            'deleted'       => $deleted_count,
            'sync_duration' => array(
                'hours'   => $hours,
                'minutes' => $minutes,
                'seconds' => number_format($seconds, 2)
            )
        );

        wp_send_json_success($response_data);

        ini_set('max_execution_time', $original_max_execution_time);
        ini_set('memory_limit', $original_memory_limit);
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}
