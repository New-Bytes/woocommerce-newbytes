<?php

/**
 * Crea o actualiza un único producto en WooCommerce desde datos de la API
 * 
 * @param array $row Datos del producto desde la API
 * @param array $options Opciones de sincronización (opcional)
 * @return array Resultado con 'success', 'product_id', 'action' (created/updated/skipped) o 'error'
 */
function nb_create_single_product($row, $options = array())
{
    try {
        // Opciones por defecto
        $defaults = array(
            'prefix' => get_option('nb_prefix', 'NB_'),
            'sync_no_iva' => get_option('nb_sync_no_iva', false),
            'sync_usd' => get_option('nb_sync_usd', false),
            'sync_description' => false,
            'set_image' => true,
            'post_status' => 'publish'
        );
        $options = array_merge($defaults, $options);
        
        // Validar datos mínimos requeridos
        if (empty($row['sku'])) {
            return array('success' => false, 'error' => 'SKU vacío');
        }
        
        if (!isset($row['price']) || !isset($row['price']['finalPriceWithUtility'])) {
            return array('success' => false, 'error' => 'Datos de precio inválidos');
        }
        
        $sku = $options['prefix'] . $row['sku'];
        $action = 'created';
        $id = null;
        
        // Verificar si el SKU ya existe
        $existing_product_id = wc_get_product_id_by_sku($sku);
        
        if ($existing_product_id) {
            // Actualizar producto existente
            $id = $existing_product_id;
            $action = 'updated';
        } elseif ($row['amountStock'] > 0) {
            // Crear nuevo producto si tiene stock
            $product_data = array(
                'post_title'   => $row['title'],
                'post_type'    => 'product',
                'post_status'  => $options['post_status'],
            );
            $id = wp_insert_post($product_data, false, false);
            
            if (is_wp_error($id)) {
                return array('success' => false, 'error' => 'Error al crear post: ' . $id->get_error_message());
            }
        } else {
            // Sin stock, no crear
            return array('success' => true, 'action' => 'skipped', 'reason' => 'Sin stock');
        }
        
        if (!$id) {
            return array('success' => false, 'error' => 'No se pudo obtener ID del producto');
        }
        
        // Calcular precio basado en opciones
        if ($options['sync_usd']) {
            $price = $options['sync_no_iva'] ? $row['price']['value'] : $row['price']['finalPriceWithUtility'];
        } else {
            $cotizacion = isset($row['cotizacion']) ? $row['cotizacion'] : 1;
            $price = $options['sync_no_iva']
                ? $row['price']['value'] * $cotizacion
                : $row['price']['finalPriceWithUtility'] * $cotizacion;
        }
        
        // Limpiar cache y obtener producto
        clean_post_cache($id);
        $product = wc_get_product($id);
        
        if (!$product) {
            return array('success' => false, 'error' => 'No se pudo obtener el producto WC con ID ' . $id);
        }
        
        // Configurar producto
        $product->set_sku($sku);
        $product->set_regular_price($price);
        $product->set_manage_stock(true);
        $product->set_stock_quantity($row['amountStock']);
        $product->set_stock_status($row['amountStock'] > 0 ? 'instock' : 'outofstock');
        
        // Dimensiones (convertir de mm/g a cm/kg)
        if (isset($row['weightAverage'])) {
            $product->set_weight($row['weightAverage'] / 1000);
        }
        if (isset($row['widthAverage'])) {
            $product->set_width($row['widthAverage'] / 10);
        }
        if (isset($row['lengthAverage'])) {
            $product->set_length($row['lengthAverage'] / 10);
        }
        if (isset($row['highAverage'])) {
            $product->set_height($row['highAverage'] / 10);
        }
        
        // Descripción adicional
        $additional_description = get_option('nb_description', '');
        if (!empty($additional_description)) {
            $product->set_description($additional_description);
        }
        
        // Categoría
        $category_to_use = !empty($row['categoryDescriptionUser']) ? $row['categoryDescriptionUser'] : (isset($row['category']) ? $row['category'] : null);
        if (!empty($category_to_use)) {
            $category_term = term_exists($category_to_use, 'product_cat');
            if (!$category_term) {
                $category_term = wp_insert_term($category_to_use, 'product_cat');
            }
            if (!is_wp_error($category_term)) {
                $term_id = is_array($category_term) ? $category_term['term_id'] : $category_term;
                $product->set_category_ids(array($term_id));
            }
        }
        
        $product->save();
        
        // Imagen destacada con FIFU
        if ($options['set_image']) {
            if (is_plugin_active('featured-image-from-url/featured-image-from-url.php') || 
                is_plugin_active('fifu-premium/fifu-premium.php')) {
                $image = !empty($row['mainImageExp']) ? $row['mainImageExp'] : (isset($row['mainImage']) ? $row['mainImage'] : null);
                if (!empty($image) && function_exists('fifu_dev_set_image')) {
                    fifu_dev_set_image($id, $image);
                }
            }
        }
        
        return array(
            'success' => true,
            'product_id' => $id,
            'sku' => $sku,
            'action' => $action,
            'price' => $price
        );
        
    } catch (Exception $e) {
        return array('success' => false, 'error' => 'Excepción: ' . $e->getMessage());
    }
}

/**
 * Elimina un producto por su ID
 * 
 * @param int $product_id ID del producto a eliminar
 * @param bool $force_delete Eliminar permanentemente (true) o mover a papelera (false)
 * @return array Resultado con 'success' o 'error'
 */
function nb_delete_single_product($product_id, $force_delete = true)
{
    try {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return array('success' => false, 'error' => 'Producto no encontrado');
        }
        
        $sku = $product->get_sku();
        $result = $product->delete($force_delete);
        
        if ($result) {
            return array('success' => true, 'deleted_id' => $product_id, 'deleted_sku' => $sku);
        } else {
            return array('success' => false, 'error' => 'No se pudo eliminar el producto');
        }
    } catch (Exception $e) {
        return array('success' => false, 'error' => 'Excepción: ' . $e->getMessage());
    }
}

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
