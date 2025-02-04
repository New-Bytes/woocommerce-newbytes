<?php

function nb_delete_products_by_prefix($existing_skus, $prefix)
{
    global $wpdb;

    try {
        $start_time = microtime(true); // Tiempo de inicio del proceso

        // Escapar los SKUs existentes para la consulta
        $escaped_skus = array_map(function ($sku) use ($wpdb) {
            return $wpdb->prepare('%s', $sku);
        }, $existing_skus);
        $escaped_skus_list = implode(',', $escaped_skus);

        // Eliminar productos con el prefijo especificado que no están en la lista de SKUs existentes
        $deleted_count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->posts} 
                 WHERE post_type = 'product' 
                 AND post_status = 'publish' 
                 AND ID IN (
                     SELECT post_id FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_sku' 
                     AND meta_value REGEXP %s
                     AND meta_value NOT IN ({$escaped_skus_list})
                 )",
                '^' . $prefix
            )
        );

        $end_time = microtime(true); // Tiempo de finalización del proceso
        $sync_duration = $end_time - $start_time;

        $hours = floor($sync_duration / 3600);
        $minutes = floor(($sync_duration % 3600) / 60);
        $seconds = $sync_duration % 60;

        return array(
            'deleted' => $deleted_count,
            'sync_duration' => array(
                'hours' => $hours,
                'minutes' => $minutes,
                'seconds' => number_format($seconds, 2)
            )
        );
    } catch (Exception $e) {
        error_log('Error al eliminar productos: ' . $e->getMessage());
        return array('error' => $e->getMessage());
    }
}

function nb_update_description_products()
{
    // Verifica el nonce para seguridad
    check_ajax_referer('nb_update_description_all', 'nb_update_description_all_nonce');

    // Llama al callback con la bandera $syncDescription en true
    $result = nb_callback(true);

    if (isset($result['success']) && $result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error(isset($result['error']) ? $result['error'] : 'Error desconocido durante la sincronización.');
    }
}

add_action('wp_ajax_nb_update_description_products', 'nb_update_description_products');
