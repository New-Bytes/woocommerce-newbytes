<?php

function nb_cron_interval($schedules)
{
    // Obtén el intervalo seleccionado por el usuario
    $user_interval = intval(get_option('nb_sync_interval', 3600)); // Valor por defecto: 1 hora

    // Convertimos a minutos.
    $user_interval_in_min = $user_interval / 60;

    // Añadir el intervalo personalizado basado en la selección del usuario
    $schedules['custom_user_interval'] = array(
        'interval' => $user_interval,
        'display'  => __("NewBytes: Intervalo personalizado para cada {$user_interval_in_min} minutos")
    );

    return $schedules;
}

function nb_update_cron_schedule($old_value = null, $value = null)
{
    // Desprogramar el evento existente
    $timestamp = wp_next_scheduled('nb_cron_sync_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'nb_cron_sync_event');
    }

    // Programar un nuevo evento con el intervalo actualizado
    wp_schedule_event(time(), 'custom_user_interval', 'nb_cron_sync_event');
}

function nb_callback($syncDescription = false)
{
    try {
        error_log('nb_callback ejecutado a las: ' . date('Y-m-d H:i:s'), 3, __DIR__ . '/debug-newbytes.txt');

        // Guardar límites originales
        $original_max_execution_time = ini_get('max_execution_time');
        $original_memory_limit = ini_get('memory_limit');

        // Establecer nuevos límites
        ini_set('max_execution_time', '1800'); // 30 minutos
        ini_set('memory_limit', '2048M'); // 2 GB

        $start_time = microtime(true); // Tiempo de inicio

        $token = nb_get_token();
        if (!$token) {
            output_response(array('error' => 'No fue posible obtener el token.'));
            return;
        }

        $url = API_URL_NB . '/';
        $args = array(
            'headers'  => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'timeout'  => 30,
            'blocking' => true,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            output_response(array('error' => 'Error en la solicitud de productos: ' . $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            output_response(array('error' => 'Error al decodificar JSON de la solicitud de productos: ' . json_last_error_msg()));
            return;
        }

        // Obtener todos los SKUs existentes de WooCommerce con el prefijo especificado
        $prefix = get_option('nb_prefix');
        $existing_skus = array();

        foreach ($json as $row) {
            $existing_skus[] = $prefix . $row['sku'];
        }

        // Llamar a la función para eliminar productos que no están en la respuesta
        $delete_result = nb_delete_products_by_prefix($existing_skus, $prefix);

        // Continuar con el procesamiento de productos
        $updated_count = 0;
        $created_count = 0;
        $categories_cache = array();

        $sync_no_iva = get_option('nb_sync_no_iva');
        $sync_usd = get_option('nb_sync_usd');

        foreach ($json as $row) {
            $id = null;
            $sku = $prefix . $row['sku'];

            // Verificar si el SKU ya existe
            $existing_product_id = wc_get_product_id_by_sku($sku);

            if ($existing_product_id) {
                // Actualizar producto existente
                $id = $existing_product_id;
                $updated_count++;
            } elseif ($row['amountStock'] > 0 && !empty($row['sku'])) {
                // Crear nuevo producto si no existe y tiene stock
                $product_data = array(
                    'post_title'   => $row['title'],
                    'post_type'    => 'product',
                    'post_status'  => 'publish',
                );
                $id = wp_insert_post($product_data);
                $created_count++;
            }

            // Si hay un ID (producto existente o nuevo)
            if ($id) {
                try {
                    // Manejo de precios basado en las opciones de sincronización
                    if ($sync_usd) {
                        $price = $sync_no_iva ? $row['price']['value'] : $row['price']['finalPriceWithUtility'];
                    } else {
                        $price = $sync_no_iva
                            ? $row['price']['value'] * $row['cotizacion']
                            : $row['price']['finalPriceWithUtility'] * $row['cotizacion'];
                    }

                    $product = wc_get_product($id);
                    $product->set_sku($sku);

                    // Sincronizar descripción, si está activado
                    if ($syncDescription) {
                        $description_url = API_URL_NB . '/autoGeneratedDescription/' . (int)$row['id'];
                        $description_args = array(
                            'headers'  => array(
                                'Authorization' => 'Bearer ' . $token,
                                'Content-Type'  => 'application/json'
                            ),
                            'timeout'  => 30,
                            'blocking' => true,
                        );

                        $description_response = wp_remote_get($description_url, $description_args);

                        if (!is_wp_error($description_response)) {
                            $description_body = wp_remote_retrieve_body($description_response);
                            $description_json = json_decode($description_body, true);

                            if (json_last_error() === JSON_ERROR_NONE && isset($description_json['description'])) {
                                $product->set_description($description_json['description']);
                            } else {
                                error_log('Error en la respuesta de la descripción para el producto con SKU ' . $sku);
                            }
                        } else {
                            error_log('Error en la solicitud de descripción para el producto con SKU ' . $sku . ': ' . $description_response->get_error_message());
                        }
                    }

                    // Manejo de categoría del usuario o categoría original de la API
                    $category_to_use = !empty($row['categoryDescriptionUser']) ? $row['categoryDescriptionUser'] : $row['category'];

                    if (!isset($categories_cache[$category_to_use])) {
                        $category_term = term_exists($category_to_use, 'product_cat');
                        if (!$category_term && !is_numeric($category_to_use)) {
                            if ($category_to_use != '') {
                                $category_term = wp_insert_term($category_to_use, 'product_cat');
                            }
                        }
                        $categories_cache[$category_to_use] = $category_term ? $category_term['term_id'] : null;
                    } else {
                        $category_term = $categories_cache[$category_to_use];
                    }

                    if ($category_term) {
                        $product->set_category_ids(array($category_term));
                    }

                    $product->set_regular_price($price);
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($row['amountStock']);
                    $product->set_stock_status($row['amountStock'] > 0 ? 'instock' : 'outofstock');
                    $product->set_weight($row['weightAverage'] / 1000); // gr a kg
                    $product->set_width($row['widthAverage'] / 10);     // mm a cm
                    $product->set_length($row['lengthAverage'] / 10);   // mm a cm
                    $product->set_height($row['highAverage'] / 10);     // mm a cm
                    $product->save();

                    # Optimización de imagen destacada
                    if ((is_plugin_active('featured-image-from-url/featured-image-from-url.php') || is_plugin_active('fifu-premium/fifu-premium.php'))) {
                        $image = !empty($row['mainImageExp']) ? $row['mainImageExp'] : (isset($row['mainImage']) ? $row['mainImage'] : null);

                        # Si hay una imagen válida, establecerla como imagen destacada
                        if (!empty($image)) {
                            fifu_dev_set_image($id, $image);
                        }
                    }

                    error_log('nb_callback ejecutado correctamente a las: ' . date('Y-m-d H:i:s'), 3, __DIR__ . '/debug-newbytes.txt');
                } catch (Exception $e) {
                    error_log('Error al procesar el producto con SKU ' . $sku . ': ' . $e->getMessage());
                    continue; // Saltar este producto y continuar con los siguientes
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error en nb_callback: ' . $e->getMessage());
    } finally {
        // Restaurar límites originales
        ini_set('max_execution_time', $original_max_execution_time);
        ini_set('memory_limit', $original_memory_limit);

        // Calcular tiempo de ejecución
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time);
        error_log('Tiempo de ejecución de nb_callback: ' . $execution_time . ' segundos', 3, __DIR__ . '/debug-newbytes.txt');
    }
}


add_filter('cron_schedules', 'nb_cron_interval');
add_action('update_option_nb_sync_interval', 'nb_update_cron_schedule', 10, 2);
add_action('nb_cron_sync_event', 'nb_callback');
