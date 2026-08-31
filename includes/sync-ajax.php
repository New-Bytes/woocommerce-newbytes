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

    // No pisar una sincronización en curso (p. ej. el cron)
    if (nb_sync_is_locked()) {
        wp_send_json_error(array('message' => 'Ya hay una sincronización en curso. Esperá a que termine.'));
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

    // Lock de concurrencia: el primer lote lo toma, los siguientes lo renuevan.
    if ($offset === 0) {
        if (!nb_sync_acquire_lock()) {
            wp_send_json_error(array('message' => 'Ya hay una sincronización en curso.'));
        }
    } else {
        nb_sync_refresh_lock();
    }

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
                if (!empty($row['sku'])) {
                    $existing_skus[] = $prefix . $row['sku'];
                }
            }
            nb_delete_products_by_prefix($existing_skus, $prefix);
        }

        // Obtener el lote actual
        $batch = array_slice($all_products, $offset, $batch_size);

        if (empty($batch)) {
            // No hay más productos, finalizar
            nb_sync_release_lock();
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
                $stats = array('created' => 0, 'updated' => 0, 'deleted' => 0, 'priceless' => 0);
            }

            // Sumar estadísticas del lote actual
            $stats['created']   += $result['created'];
            $stats['updated']   += $result['updated'];
            $stats['priceless']  = (isset($stats['priceless']) ? $stats['priceless'] : 0) + $result['priceless'];

            // Crear log
            NB_Logs_Manager::create_log($all_products, $stats, 'manual');

            // Limpiar transient y liberar el lock
            delete_transient('nb_sync_stats');
            nb_sync_release_lock();
        } else {
            // Guardar estadísticas parciales
            $stats = get_transient('nb_sync_stats');
            if (!$stats) {
                $stats = array('created' => 0, 'updated' => 0, 'deleted' => 0, 'priceless' => 0);
            }
            $stats['created']   += $result['created'];
            $stats['updated']   += $result['updated'];
            $stats['priceless']  = (isset($stats['priceless']) ? $stats['priceless'] : 0) + $result['priceless'];
            set_transient('nb_sync_stats', $stats, 3600);
        }

        wp_send_json_success(array(
            'completed' => $is_completed,
            'processed' => $new_offset,
            'total' => $total_products,
            'batch_created' => $result['created'],
            'batch_updated' => $result['updated'],
            'batch_priceless' => $result['priceless'],
            'stats' => $is_completed ? $stats : null
        ));

    } catch (Exception $e) {
        nb_sync_release_lock();
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}

/**
 * Procesa un lote de productos usando el mapper unificado.
 *
 * @see .spec/specs/SPEC-0001-unified-product-mapper.md
 *
 * @param array $batch            Filas de producto de la API.
 * @param bool  $sync_description Si además trae la descripción de cada producto.
 * @return array{created:int,updated:int,priceless:int}
 */
function nb_process_product_batch($batch, $sync_description = false)
{
    $prefix = get_option('nb_prefix');

    $created_count   = 0;
    $updated_count   = 0;
    $priceless_count = 0;

    $map_options = array(
        'prefix'      => $prefix,
        'sync_no_iva' => (bool) get_option('nb_sync_no_iva'),
        'sync_usd'    => (bool) get_option('nb_sync_usd'),
        'skip_additional_description' => (bool) $sync_description,
    );

    $token = null;
    if ($sync_description) {
        $token = nb_get_token();
    }

    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);

    try {
        foreach ($batch as $row) {
            if (empty($row['sku'])) {
                continue;
            }

            $sku = $prefix . $row['sku'];
            $id  = null;

            $existing_product_id = wc_get_product_id_by_sku($sku);
            if ($existing_product_id) {
                $id = $existing_product_id;
                $updated_count++;
            } elseif (isset($row['amountStock']) && $row['amountStock'] > 0) {
                $id = wp_insert_post(array(
                    'post_title'  => isset($row['title']) ? $row['title'] : $sku,
                    'post_type'   => 'product',
                    'post_status' => 'publish',
                ), false, false);

                if (is_wp_error($id) || !$id) {
                    nb_log('No se pudo crear el post para SKU ' . $sku, 'error');
                    continue;
                }
                $created_count++;
            }

            if (!$id) {
                continue;
            }

            try {
                clean_post_cache($id);
                $product = wc_get_product($id);
                if (!$product) {
                    nb_log('No se pudo obtener el producto WC ' . $id . ' (SKU ' . $sku . ')', 'error');
                    continue;
                }

                if ($sync_description && $token && isset($row['id'])) {
                    $desc = nb_fetch_product_description((int) $row['id'], $token);
                    if ($desc !== null) {
                        $additional = (string) get_option('nb_description', '');
                        $product->set_description(trim($additional . ' ' . $desc));
                    }
                }

                $map = NB_Product_Mapper::apply_core($product, $row, $map_options);
                if (!$map['ok']) {
                    nb_log('Fila inválida, se omite', 'warning', array('sku' => $sku));
                    continue;
                }
                if (in_array('price', $map['skipped'], true)) {
                    $priceless_count++;
                    nb_log('Precio omitido para ' . $sku, 'warning', array(
                        'row_id' => isset($row['id']) ? $row['id'] : null,
                    ));
                }

                $product->save();

                nb_set_fifu_image($id, $row);
            } catch (Exception $e) {
                nb_log('Error procesando SKU ' . $sku . ': ' . $e->getMessage(), 'error');
                continue;
            }
        }
    } finally {
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);
    }

    return array(
        'created'   => $created_count,
        'updated'   => $updated_count,
        'priceless' => $priceless_count,
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
