<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Crea o actualiza un único producto en WooCommerce desde datos de la API.
 *
 * Delega el mapeo de campos en NB_Product_Mapper (única fuente de verdad).
 *
 * @see .spec/specs/SPEC-0001-unified-product-mapper.md
 *
 * @param array $row     Datos del producto desde la API.
 * @param array $options  Opciones de sincronización (opcional).
 * @return array Resultado con 'success', 'product_id', 'sku', 'action' o 'error'.
 */
function nb_create_single_product($row, $options = array())
{
    try {
        $defaults = array(
            'prefix'          => get_option('nb_prefix', 'NB_'),
            'sync_no_iva'     => (bool) get_option('nb_sync_no_iva', false),
            'sync_usd'        => (bool) get_option('nb_sync_usd', false),
            'sync_description' => false,
            'set_image'       => true,
            'post_status'     => 'publish',
        );
        $options = array_merge($defaults, $options);

        if (empty($row['sku'])) {
            return array('success' => false, 'error' => 'SKU vacío');
        }
        if (!isset($row['price'])) {
            return array('success' => false, 'error' => 'Datos de precio inválidos');
        }

        $sku    = $options['prefix'] . $row['sku'];
        $action = 'created';
        $id     = null;

        $existing_product_id = wc_get_product_id_by_sku($sku);
        if ($existing_product_id) {
            $id     = $existing_product_id;
            $action = 'updated';
        } elseif (isset($row['amountStock']) && $row['amountStock'] > 0) {
            $id = wp_insert_post(array(
                'post_title'  => isset($row['title']) ? $row['title'] : $sku,
                'post_type'   => 'product',
                'post_status' => $options['post_status'],
            ), false, false);

            if (is_wp_error($id)) {
                return array('success' => false, 'error' => 'Error al crear post: ' . $id->get_error_message());
            }
        } else {
            return array('success' => true, 'action' => 'skipped', 'reason' => 'Sin stock');
        }

        if (!$id) {
            return array('success' => false, 'error' => 'No se pudo obtener ID del producto');
        }

        clean_post_cache($id);
        $product = wc_get_product($id);
        if (!$product) {
            return array('success' => false, 'error' => 'No se pudo obtener el producto WC con ID ' . $id);
        }

        $map = NB_Product_Mapper::apply_core($product, $row, array(
            'prefix'      => $options['prefix'],
            'sync_no_iva' => $options['sync_no_iva'],
            'sync_usd'    => $options['sync_usd'],
        ));
        if (!$map['ok']) {
            return array('success' => false, 'error' => 'Fila inválida para SKU ' . $sku);
        }

        $product->save();

        if (!empty($options['set_image'])) {
            nb_set_fifu_image($id, $row);
        }

        return array(
            'success'    => true,
            'product_id' => $id,
            'sku'        => $sku,
            'action'     => $action,
            'price'      => $product->get_regular_price(),
            'skipped'    => $map['skipped'],
        );
    } catch (Exception $e) {
        return array('success' => false, 'error' => 'Excepción: ' . $e->getMessage());
    }
}

/**
 * Elimina un producto por su ID.
 *
 * @param int  $product_id   ID del producto a eliminar.
 * @param bool $force_delete Eliminar permanentemente (true) o mover a papelera (false).
 * @return array Resultado con 'success' o 'error'.
 */
function nb_delete_single_product($product_id, $force_delete = true)
{
    try {
        $product = wc_get_product($product_id);
        if (!$product) {
            return array('success' => false, 'error' => 'Producto no encontrado');
        }

        $sku    = $product->get_sku();
        $result = $product->delete($force_delete);

        if ($result) {
            return array('success' => true, 'deleted_id' => $product_id, 'deleted_sku' => $sku);
        }

        return array('success' => false, 'error' => 'No se pudo eliminar el producto');
    } catch (Exception $e) {
        return array('success' => false, 'error' => 'Excepción: ' . $e->getMessage());
    }
}

/**
 * Elimina los productos NB (por prefijo) que ya no están en el feed.
 *
 * @param string[] $existing_skus SKUs (con prefijo) presentes en el feed actual.
 * @param string   $prefix        Prefijo de SKU.
 * @return array{deleted:int,sync_duration?:array,error?:string}
 */
function nb_delete_products_by_prefix($existing_skus, $prefix)
{
    global $wpdb;

    try {
        $start_time    = microtime(true);
        $deleted_count = 0;

        if (empty($prefix)) {
            nb_log('nb_delete_products_by_prefix: prefijo vacío', 'error');
            return array('error' => 'Prefijo vacío', 'deleted' => 0);
        }

        $keep = array_flip($existing_skus);

        $query = $wpdb->prepare(
            "SELECT p.ID, pm.meta_value AS sku
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
               AND p.post_status = 'publish'
               AND pm.meta_key = '_sku'
               AND pm.meta_value LIKE %s",
            $wpdb->esc_like($prefix) . '%'
        );

        $products = $wpdb->get_results($query);

        if (!empty($products)) {
            foreach ($products as $product) {
                if (isset($keep[$product->sku])) {
                    continue;
                }
                if (wp_delete_post($product->ID, true)) {
                    $deleted_count++;
                } else {
                    nb_log('No se pudo eliminar el producto ID ' . $product->ID . ' (SKU ' . $product->sku . ')', 'warning');
                }
            }
        }

        $sync_duration = microtime(true) - $start_time;
        $hours   = floor($sync_duration / 3600);
        $minutes = floor(($sync_duration - ($hours * 3600)) / 60);
        $seconds = $sync_duration - ($hours * 3600) - ($minutes * 60);

        if ($deleted_count > 0) {
            nb_log('Productos eliminados por no estar en el feed: ' . $deleted_count, 'info');
        }

        return array(
            'deleted'       => $deleted_count,
            'sync_duration' => array(
                'hours'   => $hours,
                'minutes' => $minutes,
                'seconds' => number_format($seconds, 2),
            ),
        );
    } catch (Exception $e) {
        nb_log('Error al eliminar productos por prefijo: ' . $e->getMessage(), 'error');
        return array('error' => $e->getMessage(), 'deleted' => 0);
    }
}

function nb_update_description_products()
{
    // Verifica el nonce para seguridad
    check_ajax_referer('nb_update_description_all', 'nb_update_description_all_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }

    // Llama al callback con la bandera $syncDescription en true
    $result = nb_callback(true);

    if (isset($result['success']) && $result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error(isset($result['error']) ? $result['error'] : 'Error desconocido durante la sincronización.');
    }
}
