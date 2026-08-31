<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elimina TODOS los productos NB (por prefijo de SKU).
 *
 * Handler de wp_ajax_nb_delete_products y admin_post_nb_delete_products.
 *
 * @see .spec/specs/SPEC-0008-csrf-nonce-input-hardening.md
 * @see .spec/specs/SPEC-0009-delete-products-safety.md
 */
function nb_delete_products()
{
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    check_ajax_referer('nb_delete_all', 'nb_delete_all_nonce');

    $original_max_execution_time = ini_get('max_execution_time');
    $original_memory_limit       = ini_get('memory_limit');

    ini_set('max_execution_time', '1800'); // 30 minutos
    ini_set('memory_limit', '2048M');

    try {
        $start_time = microtime(true);

        $prefix = get_option('nb_prefix');
        if (!$prefix) {
            wp_send_json_error('No se encontró el prefijo del SKU.');
        }

        // IDs de todos los productos NB (cualquier estado), vía SKU con prefijo.
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_type = 'product'
                   AND pm.meta_key = '_sku'
                   AND pm.meta_value LIKE %s",
                $wpdb->esc_like($prefix) . '%'
            )
        );

        $deleted_count = 0;
        if (!empty($ids)) {
            wp_defer_term_counting(true);
            foreach ($ids as $id) {
                // wp_delete_post dispara los hooks de WooCommerce -> limpia
                // wc_product_meta_lookup, term_relationships y postmeta asociada.
                if (wp_delete_post((int) $id, true)) {
                    $deleted_count++;
                }
            }
            wp_defer_term_counting(false);
        }

        // Red de seguridad: _sku huérfano con el prefijo (scopeado, con esc_like).
        $orphan_cleanup = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE pm FROM {$wpdb->postmeta} pm
                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.ID IS NULL
                   AND pm.meta_key = '_sku'
                   AND pm.meta_value LIKE %s",
                $wpdb->esc_like($prefix) . '%'
            )
        );

        update_option('nb_last_update', current_time('mysql'));

        $sync_duration = microtime(true) - $start_time;
        $hours   = floor($sync_duration / 3600);
        $minutes = floor(($sync_duration - ($hours * 3600)) / 60);
        $seconds = $sync_duration - ($hours * 3600) - ($minutes * 60);

        nb_log('Borrado masivo de productos NB: ' . $deleted_count . ' eliminados', 'info', array(
            'orphans_cleaned' => $orphan_cleanup,
        ));

        ini_set('max_execution_time', $original_max_execution_time);
        ini_set('memory_limit', $original_memory_limit);

        wp_send_json_success(array(
            'deleted'         => $deleted_count,
            'orphans_cleaned' => $orphan_cleanup,
            'sync_duration'   => array(
                'hours'   => $hours,
                'minutes' => $minutes,
                'seconds' => number_format($seconds, 2),
            ),
        ));
    } catch (Exception $e) {
        ini_set('max_execution_time', $original_max_execution_time);
        ini_set('memory_limit', $original_memory_limit);
        nb_log('Error en borrado masivo: ' . $e->getMessage(), 'error');
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}
