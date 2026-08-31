<?php
if (!defined('ABSPATH')) {
    exit;
}

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

/**
 * Sincronización completa del catálogo NewBytes contra WooCommerce.
 *
 * Se dispara por WP-Cron (nb_cron_sync_event), por REST (nb_sync_catalog) y por
 * el AJAX de descripciones (con $syncDescription = true).
 *
 * @see .spec/specs/SPEC-0001-unified-product-mapper.md
 * @see .spec/specs/SPEC-0005-cron-is-plugin-active.md
 * @see .spec/specs/SPEC-0006-remove-unsafe-global-mutations.md
 * @see .spec/specs/SPEC-0007-sync-concurrency-lock.md
 *
 * @param bool $syncDescription Si además trae la descripción de cada producto desde la API.
 * @return array{success:bool,message?:string,error?:string,stats?:array,blocked?:bool,locked?:bool}
 */
function nb_callback($syncDescription = false)
{
    // Verificar que el plugin esté activo (get_option en lugar de is_plugin_active,
    // que no está disponible en contexto cron).
    $active_plugins = get_option('active_plugins', array());
    $plugin_found   = false;
    foreach ($active_plugins as $plugin) {
        if (strpos($plugin, 'woocommerce-newbytes') !== false && strpos($plugin, '.php') !== false) {
            $plugin_found = true;
            break;
        }
    }
    if (!$plugin_found) {
        nb_log('BLOQUEADO: el plugin no figura como activo', 'error');
        return array('success' => false, 'error' => 'Plugin desactivado. Sincronización bloqueada.', 'blocked' => true);
    }

    // Verificar credenciales
    $nb_user     = get_option('nb_user');
    $nb_password = get_option('nb_password');
    if (empty($nb_user) || empty($nb_password)) {
        nb_log('BLOQUEADO: credenciales no configuradas', 'error');
        return array('success' => false, 'error' => 'Credenciales no configuradas.', 'blocked' => true);
    }

    // Lock de concurrencia: una sola sincronización a la vez.
    if (!nb_sync_acquire_lock()) {
        nb_log('Sincronización solicitada pero ya hay una en curso', 'warning');
        return array('success' => false, 'error' => 'Ya hay una sincronización en curso.', 'locked' => true);
    }

    $original_max_execution_time = ini_get('max_execution_time');
    $original_memory_limit       = ini_get('memory_limit');

    try {
        // Límites amplios para catálogos grandes
        ini_set('max_execution_time', '1800'); // 30 minutos
        ini_set('memory_limit', '2048M');       // 2 GB

        // Evitar timeout de proxy/servidor sólo para requests HTTP (no cron)
        if (!wp_doing_cron() && !defined('DOING_CRON')) {
            ignore_user_abort(true);
            set_time_limit(1800);
        }

        $start_time = microtime(true);
        nb_log('Sincronización iniciada', 'info', array('sync_description' => (bool) $syncDescription));

        // PASO 1: generar JSON desde la API y guardarlo en nb-products/
        nb_log('Paso 1: generando JSON de productos desde la API...', 'info');
        $generate_result = NB_Product_Manager::generate_products_json();
        if (!$generate_result['success']) {
            nb_log('Error al generar JSON: ' . $generate_result['error'], 'error');
            return array('success' => false, 'error' => $generate_result['error']);
        }
        nb_log('JSON generado: ' . $generate_result['filename'], 'info', array(
            'total_products' => $generate_result['total_products'],
        ));

        // PASO 2: leer el JSON local más reciente
        nb_log('Paso 2: leyendo JSON local para sincronización...', 'info');
        $read_result = NB_Product_Manager::read_latest_products_json();
        if (!$read_result['success']) {
            nb_log('Error al leer JSON local: ' . $read_result['error'], 'error');
            return array('success' => false, 'error' => $read_result['error']);
        }

        $json = $read_result['data'];
        if (!is_array($json)) {
            nb_log('El JSON de productos no es una lista válida', 'error');
            return array('success' => false, 'error' => 'Respuesta de catálogo inválida.');
        }
        nb_log('JSON local cargado', 'info', array(
            'file'     => $read_result['file_info']['filename'],
            'products' => count($json),
        ));

        // Tipo de sincronización (para el log JSON)
        $sync_type = $syncDescription ? 'description' : 'auto';
        if (wp_doing_cron()) {
            $sync_type = 'auto';
        } elseif (isset($_POST['update_all']) || (isset($_POST['action']) && $_POST['action'] === 'nb_update_description_products')) {
            $sync_type = 'manual';
        }

        $prefix = get_option('nb_prefix');

        // SKUs presentes en el feed -> se usan para eliminar los que ya no existen
        $existing_skus = array();
        foreach ($json as $row) {
            if (!empty($row['sku'])) {
                $existing_skus[] = $prefix . $row['sku'];
            }
        }
        $delete_result = nb_delete_products_by_prefix($existing_skus, $prefix);

        $updated_count   = 0;
        $created_count   = 0;
        $priceless_count = 0;

        $map_options = array(
            'prefix'      => $prefix,
            'sync_no_iva' => (bool) get_option('nb_sync_no_iva'),
            'sync_usd'    => (bool) get_option('nb_sync_usd'),
            // Si venimos a sincronizar descripciones, el caller setea la descripción
            // completa (adicional + API); que el mapper no la pise.
            'skip_additional_description' => (bool) $syncDescription,
        );

        $token = null;
        if ($syncDescription) {
            $token = nb_get_token();
            if (!$token) {
                nb_log('No se pudo obtener token para sincronizar descripciones', 'warning');
            }
        }

        // Conteo diferido de términos/comentarios durante el loop (se restaura en el finally).
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);
        NB_Product_Mapper::reset_category_cache();
        NB_Product_Mapper::reset_tax_class_cache();

        try {
            foreach ($json as $row) {
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
                    continue; // no existe y no tiene stock -> no se crea
                }

                try {
                    clean_post_cache($id);
                    $product = wc_get_product($id);
                    if (!$product) {
                        nb_log('No se pudo obtener el producto WC ' . $id . ' (SKU ' . $sku . ')', 'error');
                        continue;
                    }

                    // Descripción desde la API (una request por producto)
                    if ($syncDescription && $token && isset($row['id'])) {
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

        $execution_time = microtime(true) - $start_time;

        // Fecha de última actualización
        $current_mysql_time = current_time('mysql');
        update_option('nb_last_update', $current_mysql_time);
        if ($sync_type === 'auto') {
            update_option('nb_last_auto_sync', $current_mysql_time);
        } else {
            update_option('nb_last_manual_sync', $current_mysql_time);
        }

        $final_stats = array(
            'created'   => $created_count,
            'updated'   => $updated_count,
            'deleted'   => isset($delete_result['deleted']) ? $delete_result['deleted'] : 0,
            'priceless' => $priceless_count,
        );

        NB_Logs_Manager::create_log($json, $final_stats, $sync_type);

        nb_log('Sincronización completada en ' . round($execution_time, 1) . ' s', 'info', $final_stats);

        return array('success' => true, 'message' => 'Sincronización completada', 'stats' => $final_stats);
    } catch (Exception $e) {
        nb_log('Error en nb_callback: ' . $e->getMessage(), 'error');
        return array('success' => false, 'error' => 'Error: ' . $e->getMessage());
    } finally {
        ini_set('max_execution_time', $original_max_execution_time);
        ini_set('memory_limit', $original_memory_limit);
        nb_sync_release_lock();
    }
}

add_filter('cron_schedules', 'nb_cron_interval');
add_action('update_option_nb_sync_interval', 'nb_update_cron_schedule', 10, 2);
add_action('nb_cron_sync_event', 'nb_callback');
