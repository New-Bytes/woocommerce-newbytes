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
        // VERIFICACIÓN DE SEGURIDAD: Verificar que el plugin esté activo
        // Usar get_option en lugar de is_plugin_active() que puede fallar en contextos AJAX
        $active_plugins = get_option('active_plugins', array());
        $plugin_found = false;
        
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'woocommerce-newbytes') !== false && strpos($plugin, '.php') !== false) {
                $plugin_found = true;
                break;
            }
        }
        
        if (!$plugin_found) {
            error_log('[NewBytes] BLOQUEADO: Plugin no está activo - ' . date('Y-m-d H:i:s'));
            return array(
                'success' => false,
                'error' => 'Plugin desactivado. Sincronización bloqueada.',
                'blocked' => true
            );
        }
        
        // Verificar que las credenciales estén configuradas
        $nb_user = get_option('nb_user');
        $nb_password = get_option('nb_password');
        if (empty($nb_user) || empty($nb_password)) {
            error_log('[NewBytes] BLOQUEADO: Credenciales no configuradas - ' . date('Y-m-d H:i:s'));
            return array(
                'success' => false,
                'error' => 'Credenciales no configuradas.',
                'blocked' => true
            );
        }
        
        error_log('[NewBytes] Sincronización iniciada - ' . date('Y-m-d H:i:s'), 3, __DIR__ . '/debug-newbytes.txt');

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

        // Crear log JSON con los datos de la API antes de procesar
        $sync_type = $syncDescription ? 'description' : 'auto';
        if (wp_doing_cron()) {
            $sync_type = 'auto';
        } elseif (isset($_POST['update_all']) || (isset($_POST['action']) && $_POST['action'] === 'nb_update_description_products')) {
            $sync_type = 'manual';
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
                            'timeout'  => 60, // Aumentado a 60 segundos para descripciones largas
                            'blocking' => true,
                        );

                        nb_log('Solicitando descripción para producto', 'debug', array('sku' => $sku, 'product_id' => $row['id']));
                        
                        $description_response = wp_remote_get($description_url, $description_args);

                        if (!is_wp_error($description_response)) {
                            $status_code = wp_remote_retrieve_response_code($description_response);
                            $description_body = wp_remote_retrieve_body($description_response);
                            
                            if ($status_code === 200) {
                                $description_json = json_decode($description_body, true);

                                if (json_last_error() === JSON_ERROR_NONE && isset($description_json['description'])) {
                                    $json_description = $description_json['description'];

                                    // Obtener descripción adicional desde la opción
                                    $additional_description = get_option('nb_description', '');

                                    $full_description = trim($additional_description . ' ' . $json_description);

                                    $product->set_description($full_description);
                                    nb_log('Descripción sincronizada exitosamente', 'info', array('sku' => $sku));
                                } else {
                                    $error_msg = 'Error al decodificar JSON de descripción para SKU ' . $sku . ': ' . json_last_error_msg();
                                    nb_log($error_msg, 'warning', array('body_preview' => substr($description_body, 0, 100)));
                                    error_log('[NewBytes] ' . $error_msg);
                                }
                            } else {
                                $error_msg = 'Error HTTP al obtener descripción para SKU ' . $sku . ' (HTTP ' . $status_code . ')';
                                nb_log($error_msg, 'warning', array('status_code' => $status_code, 'body' => substr($description_body, 0, 200)));
                                error_log('[NewBytes] ' . $error_msg);
                            }
                        } else {
                            $error_msg = 'Error en la solicitud de descripción para SKU ' . $sku . ': ' . $description_response->get_error_message();
                            nb_log($error_msg, 'error', array('error_code' => $description_response->get_error_code()));
                            error_log('[NewBytes] ' . $error_msg);
                        }
                    }

                    // Manejo de categoría del usuario o categoría original de la API
                    $category_to_use = !empty($row['categoryDescriptionUser']) ? $row['categoryDescriptionUser'] : $row['category'];

                    // Solo proceder si hay una categoría para usar
                    if (!empty($category_to_use)) {
                        // Verificar si ya tenemos esta categoría en caché
                        if (!isset($categories_cache[$category_to_use])) {
                            // Verificar si la categoría ya existe
                            $category_term = term_exists($category_to_use, 'product_cat');

                            // Si la categoría no existe, crearla
                            if (!$category_term) {
                                $category_term = wp_insert_term($category_to_use, 'product_cat');

                                // Verificar si hubo un error al crear la categoría
                                if (is_wp_error($category_term)) {
                                    error_log('Error al crear la categoría "' . $category_to_use . '": ' . $category_term->get_error_message());
                                    $categories_cache[$category_to_use] = null;
                                } else {
                                    $categories_cache[$category_to_use] = $category_term['term_id'];
                                }
                            } else {
                                // La categoría existe, guardar su ID en caché
                                $categories_cache[$category_to_use] = $category_term['term_id'];
                            }
                        }

                        // Asignar la categoría al producto si tenemos un ID válido
                        if (!empty($categories_cache[$category_to_use])) {
                            $product->set_category_ids(array($categories_cache[$category_to_use]));
                        } else {
                            error_log('No se pudo asignar la categoría "' . $category_to_use . '" al producto con SKU ' . $sku);
                        }
                    } else {
                        error_log('El producto con SKU ' . $sku . ' no tiene categoría definida');
                    }
                    $product->set_regular_price($price);
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($row['amountStock']);
                    $product->set_stock_status($row['amountStock'] > 0 ? 'instock' : 'outofstock');
                    $product->set_weight($row['weightAverage'] / 1000); // gr a kg
                    $product->set_width($row['widthAverage'] / 10);     // mm a cm
                    $product->set_length($row['lengthAverage'] / 10);   // mm a cm
                    $product->set_height($row['highAverage'] / 10);     // mm a cm
                    // Agregar descripción adicional desde la opción
                    if (!$syncDescription) {
                        $additional_description = get_option('nb_description', '');
                        if (!empty($additional_description)) {
                            $product->set_description($additional_description);
                        }
                    }

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

        // Calcular tiempo de ejecución
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time);

        // Actualizar la fecha de última actualización
        update_option('nb_last_update', current_time('mysql'));

        // Preparar estadísticas finales
        $final_stats = array(
            'created' => $created_count,
            'updated' => $updated_count,
            'deleted' => isset($delete_result['deleted']) ? $delete_result['deleted'] : 0
        );

        // Crear log JSON con los datos completos y estadísticas
        NB_Logs_Manager::create_log($json, $final_stats, $sync_type);

        // Restaurar límites originales
        ini_set('max_execution_time', $original_max_execution_time);
        ini_set('memory_limit', $original_memory_limit);

        return array(
            'success' => true,
            'message' => 'Sincronización completada',
            'stats' => $final_stats
        );
    } catch (Exception $e) {
        error_log('Error en nb_callback: ' . $e->getMessage());
        return 'Error: ' . $e->getMessage();
    } finally {
        // Registrar tiempo de ejecución en el log
        error_log('Tiempo de ejecución de nb_callback: ' . $execution_time . ' segundos', 3, __DIR__ . '/debug-newbytes.txt');
    }
}


add_filter('cron_schedules', 'nb_cron_interval');
add_action('update_option_nb_sync_interval', 'nb_update_cron_schedule', 10, 2);
add_action('nb_cron_sync_event', 'nb_callback');
