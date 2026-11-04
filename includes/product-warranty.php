<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_product_meta_end', 'nb_render_product_warranty');

/**
 * Muestra la garantía sincronizada en el bloque de metadatos de la ficha de producto.
 *
 * @see .spec/specs/SPEC-0015-garantia-de-producto.md
 */
function nb_render_product_warranty()
{
    global $product;

    if (!$product instanceof WC_Product) {
        return;
    }

    $warranty = (string) $product->get_meta('_nb_warranty');
    if ($warranty === '') {
        return;
    }

    echo '<span class="nb-warranty">Garantía: <span class="nb-warranty__value">' . esc_html($warranty) . '</span></span>';
}
