<?php

/**
 * Manejadores AJAX para sincronización con progreso
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Preparar sincronización - Genera JSON y devuelve info de productos
 */
function nb_ajax_prepare_sync()
{
    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nb_sync_nonce')) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }

    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Sin permisos'));
    }

    try {
        // Generar JSON desde la API
        $result = NB_Product_Manager::generate_products_json();

        if (!$result['success']) {
            wp_send_json_error(array('message' => $result['error']));
        }

        // Leer el JSON generado para contar productos con stock
        $read_result = NB_Product_Manager::read_latest_products_json();

        if (!$read_result['success']) {
            wp_send_json_error(array('message' => $read_result['error']));
        }

        $products = $read_result['data'];
        $total_products = count($products);
        
        // Contar productos con stock > 0
        $products_with_stock = 0;
        foreach ($products as $product) {
            if (isset($product['amountStock']) && $product['amountStock'] > 0) {
                $products_with_stock++;
            }
        }

        // Calcular tiempo estimado (0.1 segundos por producto con stock)
        $estimated_seconds = $products_with_stock * 0.1;
        $estimated_time = nb_format_estimated_time($estimated_seconds);

        wp_send_json_success(array(
            'total_products' => $total_products,
            'products_with_stock' => $products_with_stock,
            'estimated_seconds' => $estimated_seconds,
            'estimated_time' => $estimated_time,
            'json_file' => $result['filename']
        ));

    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}

/**
 * AJAX: Procesar lote de productos
 */
function nb_ajax_process_batch()
{
    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nb_sync_nonce')) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }

    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Sin permisos'));
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
    $sync_description = isset($_POST['sync_description']) && $_POST['sync_description'] === 'true';

    try {
        // Aumentar límites para el procesamiento
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        // Leer el JSON de productos
        $read_result = NB_Product_Manager::read_latest_products_json();

        if (!$read_result['success']) {
            wp_send_json_error(array('message' => $read_result['error']));
        }

        $all_products = $read_result['data'];
        $total_products = count($all_products);

        // Si es el primer lote, eliminar productos que ya no existen
        if ($offset === 0) {
            $prefix = get_option('nb_prefix');
            $existing_skus = array();
            foreach ($all_products as $row) {
                $existing_skus[] = $prefix . $row['sku'];
            }
            nb_delete_products_by_prefix($existing_skus, $prefix);
        }

        // Obtener el lote actual
        $batch = array_slice($all_products, $offset, $batch_size);
        
        if (empty($batch)) {
            // No hay más productos, finalizar
            wp_send_json_success(array(
                'completed' => true,
                'processed' => $offset,
                'total' => $total_products
            ));
        }

        // Procesar el lote
        $result = nb_process_product_batch($batch, $sync_description);

        $new_offset = $offset + count($batch);
        $is_completed = $new_offset >= $total_products;

        // Si es el último lote, crear el log y actualizar fecha
        if ($is_completed) {
            update_option('nb_last_update', current_time('mysql'));
            
            // Obtener estadísticas totales de la sesión
            $stats = get_transient('nb_sync_stats');
            if (!$stats) {
                $stats = array('created' => 0, 'updated' => 0, 'deleted' => 0);
            }
            
            // Sumar estadísticas del lote actual
            $stats['created'] += $result['created'];
            $stats['updated'] += $result['updated'];
            
            // Crear log
            NB_Logs_Manager::create_log($all_products, $stats, 'manual');
            
            // Limpiar transient
            delete_transient('nb_sync_stats');
        } else {
            // Guardar estadísticas parciales
            $stats = get_transient('nb_sync_stats');
            if (!$stats) {
                $stats = array('created' => 0, 'updated' => 0, 'deleted' => 0);
            }
            $stats['created'] += $result['created'];
            $stats['updated'] += $result['updated'];
            set_transient('nb_sync_stats', $stats, 3600);
        }

        wp_send_json_success(array(
            'completed' => $is_completed,
            'processed' => $new_offset,
            'total' => $total_products,
            'batch_created' => $result['created'],
            'batch_updated' => $result['updated'],
            'stats' => $is_completed ? $stats : null
        ));

    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}

/**
 * Procesar un lote de productos
 */
function nb_process_product_batch($batch, $sync_description = false)
{
    global $wpdb;
    
    $prefix = get_option('nb_prefix');
    $sync_no_iva = get_option('nb_sync_no_iva');
    $sync_usd = get_option('nb_sync_usd');
    
    $created_count = 0;
    $updated_count = 0;
    $categories_cache = array();
    
    // Obtener token si se sincronizan descripciones
    $token = null;
    if ($sync_description) {
        $token = nb_get_token();
    }

    // Desactivar hooks pesados
    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);

    foreach ($batch as $row) {
        $id = null;
        $sku = $prefix . $row['sku'];

        // Verificar si el SKU ya existe
        $existing_product_id = wc_get_product_id_by_sku($sku);

        if ($existing_product_id) {
            $id = $existing_product_id;
            $updated_count++;
        } elseif ($row['amountStock'] > 0 && !empty($row['sku'])) {
            $product_data = array(
                'post_title'   => $row['title'],
                'post_type'    => 'product',
                'post_status'  => 'publish',
            );
            $id = wp_insert_post($product_data, false, false);
            $created_count++;
        }

        if ($id) {
            try {
                // Calcular precio
                if ($sync_usd) {
                    $price = $sync_no_iva ? $row['price']['value'] : $row['price']['finalPriceWithUtility'];
                } else {
                    $price = $sync_no_iva
                        ? $row['price']['value'] * $row['cotizacion']
                        : $row['price']['finalPriceWithUtility'] * $row['cotizacion'];
                }

                clean_post_cache($id);
                $product = wc_get_product($id);
                
                if (!$product) {
                    continue;
                }
                
                $product->set_sku($sku);

                // Sincronizar descripción si está activado
                if ($sync_description && $token) {
                    $description_url = API_URL_NB . '/autoGeneratedDescription/' . (int)$row['id'];
                    $description_args = array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $token,
                            'Content-Type' => 'application/json'
                        ),
                        'timeout' => 60,
                    );

                    $description_response = wp_remote_get($description_url, $description_args);

                    if (!is_wp_error($description_response)) {
                        $status_code = wp_remote_retrieve_response_code($description_response);
                        if ($status_code === 200) {
                            $description_json = json_decode(wp_remote_retrieve_body($description_response), true);
                            if (isset($description_json['description'])) {
                                $additional_description = get_option('nb_description', '');
                                $full_description = trim($additional_description . ' ' . $description_json['description']);
                                $product->set_description($full_description);
                            }
                        }
                    }
                }

                // Categoría
                $category_to_use = !empty($row['categoryDescriptionUser']) ? $row['categoryDescriptionUser'] : $row['category'];
                if (!empty($category_to_use)) {
                    if (!isset($categories_cache[$category_to_use])) {
                        $category_term = term_exists($category_to_use, 'product_cat');
                        if (!$category_term) {
                            $category_term = wp_insert_term($category_to_use, 'product_cat');
                        }
                        $categories_cache[$category_to_use] = is_wp_error($category_term) ? null : $category_term['term_id'];
                    }
                    if (!empty($categories_cache[$category_to_use])) {
                        $product->set_category_ids(array($categories_cache[$category_to_use]));
                    }
                }

                $product->set_regular_price($price);
                $product->set_manage_stock(true);
                $product->set_stock_quantity($row['amountStock']);
                $product->set_stock_status($row['amountStock'] > 0 ? 'instock' : 'outofstock');
                $product->set_weight($row['weightAverage'] / 1000);
                $product->set_width($row['widthAverage'] / 10);
                $product->set_length($row['lengthAverage'] / 10);
                $product->set_height($row['highAverage'] / 10);

                if (!$sync_description) {
                    $additional_description = get_option('nb_description', '');
                    if (!empty($additional_description)) {
                        $product->set_description($additional_description);
                    }
                }

                $product->save();

                // Imagen con FIFU
                if ((is_plugin_active('featured-image-from-url/featured-image-from-url.php') || is_plugin_active('fifu-premium/fifu-premium.php'))) {
                    $image = !empty($row['mainImageExp']) ? $row['mainImageExp'] : (isset($row['mainImage']) ? $row['mainImage'] : null);
                    if (!empty($image)) {
                        fifu_dev_set_image($id, $image);
                    }
                }

            } catch (Exception $e) {
                error_log('[NewBytes] Error procesando SKU ' . $sku . ': ' . $e->getMessage());
                continue;
            }
        }
    }

    // Restaurar hooks
    wp_defer_term_counting(false);
    wp_defer_comment_counting(false);

    return array(
        'created' => $created_count,
        'updated' => $updated_count
    );
}

/**
 * Formatear tiempo estimado
 */
function nb_format_estimated_time($seconds)
{
    if ($seconds < 60) {
        return 'menos de 1 minuto';
    } elseif ($seconds < 3600) {
        $minutes = ceil($seconds / 60);
        return $minutes . ' minuto' . ($minutes > 1 ? 's' : '');
    } else {
        $hours = floor($seconds / 3600);
        $minutes = ceil(($seconds % 3600) / 60);
        return $hours . ' hora' . ($hours > 1 ? 's' : '') . ' y ' . $minutes . ' minuto' . ($minutes > 1 ? 's' : '');
    }
}

// Registrar acciones AJAX
add_action('wp_ajax_nb_prepare_sync', 'nb_ajax_prepare_sync');
add_action('wp_ajax_nb_process_batch', 'nb_ajax_process_batch');

/**
 * Modal de sincronización con progreso
 */
function nb_modal_sync_progress()
{
    ?>
    <!-- Modal de confirmación de sincronización -->
    <div id="nb-modal-sync-confirm" class="nb-modal nb-hidden" style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);">
        <div class="nb-modal-content" style="background: white; border-radius: 12px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <div style="text-align: center; margin-bottom: 20px;">
                <svg style="width: 48px; height: 48px; color: #3b82f6; margin: 0 auto 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <h3 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">Confirmar Sincronización</h3>
            </div>
            
            <div id="nb-sync-info" style="background: #f3f4f6; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; text-align: center;">
                    <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb;">
                        <p style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin: 0;" id="nb-sync-total">-</p>
                        <p style="font-size: 0.75rem; color: #6b7280; margin: 4px 0 0;">Total productos</p>
                    </div>
                    <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb;">
                        <p style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin: 0;" id="nb-sync-with-stock">-</p>
                        <p style="font-size: 0.75rem; color: #6b7280; margin: 4px 0 0;">Con stock</p>
                    </div>
                </div>
                <div style="margin-top: 12px; text-align: center; background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb;">
                    <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Tiempo estimado:</p>
                    <p style="font-size: 1.125rem; font-weight: 600; color: #3b82f6; margin: 4px 0 0;" id="nb-sync-time">-</p>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="nb-btn-cancel-sync" style="padding: 10px 20px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Cancelar
                </button>
                <button type="button" id="nb-btn-confirm-sync" style="padding: 10px 20px; border: none; background: #3b82f6; color: white; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Confirmar Sincronización
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal de progreso -->
    <div id="nb-modal-sync-progress" class="nb-modal nb-hidden" style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);">
        <div class="nb-modal-content" style="background: white; border-radius: 12px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <div style="text-align: center; margin-bottom: 20px;">
                <div id="nb-progress-icon-loading" style="margin: 0 auto 12px;">
                    <svg style="width: 48px; height: 48px; color: #3b82f6; animation: spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </div>
                <div id="nb-progress-icon-success" class="nb-hidden" style="margin: 0 auto 12px;">
                    <svg style="width: 48px; height: 48px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;" id="nb-progress-title">Sincronizando productos...</h3>
            </div>
            
            <!-- Barra de progreso -->
            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 0.875rem; color: #6b7280;" id="nb-progress-text">0 / 0 productos</span>
                    <span style="font-size: 0.875rem; font-weight: 600; color: #3b82f6;" id="nb-progress-percent">0%</span>
                </div>
                <div style="background: #e5e7eb; border-radius: 9999px; height: 12px; overflow: hidden;">
                    <div id="nb-progress-bar" style="background: linear-gradient(90deg, #3b82f6, #8b5cf6); height: 100%; width: 0%; transition: width 0.3s ease; border-radius: 9999px;"></div>
                </div>
            </div>
            
            <!-- Estadísticas en tiempo real -->
            <div id="nb-progress-stats" style="background: #f3f4f6; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; text-align: center;">
                    <div style="background: white; padding: 8px; border-radius: 6px;">
                        <p style="font-size: 1.25rem; font-weight: 700; color: #10b981; margin: 0;" id="nb-stat-created">0</p>
                        <p style="font-size: 0.7rem; color: #6b7280; margin: 2px 0 0;">Creados</p>
                    </div>
                    <div style="background: white; padding: 8px; border-radius: 6px;">
                        <p style="font-size: 1.25rem; font-weight: 700; color: #3b82f6; margin: 0;" id="nb-stat-updated">0</p>
                        <p style="font-size: 0.7rem; color: #6b7280; margin: 2px 0 0;">Actualizados</p>
                    </div>
                </div>
            </div>
            
            <!-- Botón cerrar (solo visible al completar) -->
            <div id="nb-progress-close-container" class="nb-hidden" style="text-align: center;">
                <button type="button" id="nb-btn-close-progress" style="padding: 10px 24px; border: none; background: #10b981; color: white; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
    
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .nb-hidden { display: none !important; }
    </style>
    <?php
}

/**
 * JavaScript para sincronización con progreso
 */
function nb_js_sync_progress()
{
    ?>
    <script>
    jQuery(document).ready(function($) {
        var syncData = {};
        var totalCreated = 0;
        var totalUpdated = 0;
        
        // Botón preparar sincronización
        $('#btn-prepare-sync').on('click', function() {
            var $btn = $(this);
            $('#btn-prepare-sync-text').hide();
            $('#btn-prepare-sync-spinner').removeClass('nb-hidden').show();
            $btn.prop('disabled', true);
            
            // Llamar AJAX para preparar sincronización
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'nb_prepare_sync',
                    nonce: $('#nb_sync_nonce').val()
                },
                success: function(response) {
                    $('#btn-prepare-sync-text').show();
                    $('#btn-prepare-sync-spinner').hide();
                    $btn.prop('disabled', false);
                    
                    if (response.success) {
                        syncData = response.data;
                        
                        // Mostrar info en modal
                        $('#nb-sync-total').text(syncData.total_products);
                        $('#nb-sync-with-stock').text(syncData.products_with_stock);
                        $('#nb-sync-time').text('~' + syncData.estimated_time);
                        
                        // Mostrar modal de confirmación
                        $('#nb-modal-sync-confirm').removeClass('nb-hidden');
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    $('#btn-prepare-sync-text').show();
                    $('#btn-prepare-sync-spinner').hide();
                    $btn.prop('disabled', false);
                    alert('Error de conexión al preparar la sincronización.');
                }
            });
        });
        
        // Cancelar sincronización
        $('#nb-btn-cancel-sync').on('click', function() {
            $('#nb-modal-sync-confirm').addClass('nb-hidden');
        });
        
        // Confirmar sincronización
        $('#nb-btn-confirm-sync').on('click', function() {
            $('#nb-modal-sync-confirm').addClass('nb-hidden');
            
            // Resetear estadísticas
            totalCreated = 0;
            totalUpdated = 0;
            
            // Mostrar modal de progreso
            $('#nb-progress-title').text('Sincronizando productos...');
            $('#nb-progress-icon-loading').removeClass('nb-hidden');
            $('#nb-progress-icon-success').addClass('nb-hidden');
            $('#nb-progress-close-container').addClass('nb-hidden');
            $('#nb-progress-bar').css('width', '0%');
            $('#nb-progress-percent').text('0%');
            $('#nb-progress-text').text('0 / ' + syncData.total_products + ' productos');
            $('#nb-stat-created').text('0');
            $('#nb-stat-updated').text('0');
            $('#nb-modal-sync-progress').removeClass('nb-hidden');
            
            // Iniciar procesamiento por lotes
            processBatch(0);
        });
        
        // Procesar lote
        function processBatch(offset) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'nb_process_batch',
                    nonce: $('#nb_sync_nonce').val(),
                    offset: offset,
                    batch_size: 50,
                    sync_description: 'false'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Actualizar estadísticas
                        totalCreated += data.batch_created || 0;
                        totalUpdated += data.batch_updated || 0;
                        
                        // Actualizar UI
                        var percent = Math.round((data.processed / data.total) * 100);
                        $('#nb-progress-bar').css('width', percent + '%');
                        $('#nb-progress-percent').text(percent + '%');
                        $('#nb-progress-text').text(data.processed + ' / ' + data.total + ' productos');
                        $('#nb-stat-created').text(totalCreated);
                        $('#nb-stat-updated').text(totalUpdated);
                        
                        if (data.completed) {
                            // Sincronización completada
                            $('#nb-progress-title').text('¡Sincronización completada!');
                            $('#nb-progress-icon-loading').addClass('nb-hidden');
                            $('#nb-progress-icon-success').removeClass('nb-hidden');
                            $('#nb-progress-close-container').removeClass('nb-hidden');
                            
                            // Actualizar estadísticas finales si están disponibles
                            if (data.stats) {
                                $('#nb-stat-created').text(data.stats.created);
                                $('#nb-stat-updated').text(data.stats.updated);
                            }
                        } else {
                            // Continuar con el siguiente lote
                            processBatch(data.processed);
                        }
                    } else {
                        alert('Error: ' + response.data.message);
                        $('#nb-modal-sync-progress').addClass('nb-hidden');
                    }
                },
                error: function() {
                    alert('Error de conexión durante la sincronización.');
                    $('#nb-modal-sync-progress').addClass('nb-hidden');
                }
            });
        }
        
        // Cerrar modal de progreso
        $('#nb-btn-close-progress').on('click', function() {
            $('#nb-modal-sync-progress').addClass('nb-hidden');
            location.reload(); // Recargar para ver cambios
        });
    });
    </script>
    <?php
}
