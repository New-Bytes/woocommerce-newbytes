<?php

function nb_delete_products_by_prefix($existing_skus, $prefix)
{
    global $wpdb;

    try {
        $start_time = microtime(true); // Tiempo de inicio del proceso
        $deleted_count = 0;

        // Verificar que el prefijo no esté vacío
        if (empty($prefix)) {
            error_log("Error: Prefijo vacío en nb_delete_products_by_prefix");
            return array('error' => 'Prefijo vacío', 'deleted' => 0);
        }

        // Obtener todos los productos con el prefijo especificado
        $query = $wpdb->prepare(
            "SELECT p.ID, pm.meta_value as sku 
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_sku'
             AND pm.meta_value LIKE %s",
            $prefix . '%'
        );

        $products = $wpdb->get_results($query);

        if (!empty($products)) {
            error_log("Encontrados " . count($products) . " productos con prefijo $prefix");

            foreach ($products as $product) {
                // Si el SKU no está en la lista de SKUs a mantener, eliminar el producto
                if (!in_array($product->sku, $existing_skus)) {
                    // Usar wp_delete_post para eliminar correctamente el producto y sus metadatos
                    $result = wp_delete_post($product->ID, true); // true para forzar la eliminación

                    if ($result) {
                        $deleted_count++;
                        error_log("Producto eliminado: ID {$product->ID}, SKU {$product->sku}");
                    } else {
                        error_log("Error al eliminar producto: ID {$product->ID}, SKU {$product->sku}");
                    }
                }
            }
        } else {
            error_log("No se encontraron productos con el prefijo $prefix");
        }

        $end_time = microtime(true); // Tiempo de finalización del proceso
        $sync_duration = $end_time - $start_time;

        // Convertir a enteros para evitar advertencias de conversión implícita
        $hours = floor($sync_duration / 3600);
        $minutes = floor(($sync_duration - ($hours * 3600)) / 60);
        $seconds = $sync_duration - ($hours * 3600) - ($minutes * 60);

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
