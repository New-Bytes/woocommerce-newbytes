<?php
/*
Plugin Name: Conector NewBytes
Description: Sincroniza los productos del catálogo de NewBytes con WooCommerce.
Author: NewBytes
Author URI: https://nb.com.ar
Version: 0.0.1
*/

const API_URL = 'https://api.nb.com.ar/v1';
const VERSION = '0.0.1';

function nb_plugin_action_links($links)
{
    $settings = '<a href="'. get_admin_url(null, 'options-general.php?page=nb') .'">Ajustes</a>';
    array_unshift($links, $settings);
    return $links;
}

function nb_get_token()
{
    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'user' => get_option('nb_user'),
            'password' => get_option('nb_password'),
            'mode' => 'api'
        )),
        'timeout' => '5',
        'blocking' => true,
    );

    $response = wp_remote_post(API_URL . '/auth/login', $args);

    if (is_wp_error($response)) {
        echo 'Error en la solicitud de token: ' . $response->get_error_message();
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'Error al decodificar JSON de la solicitud de token: ' . json_last_error_msg();
        return null;
    }

    if (isset($json['token'])) {
        update_option('nb_token', $json['token']);
        return $json['token'];
    }

    echo 'Token no encontrado en la respuesta: ';
    print_r($json);
    return null;
}

function nb_callback($update_all = false)
{
    $token = get_option('nb_token') ? get_option('nb_token') : nb_get_token();
    if (!$token) {
        echo 'No se pudo obtener el token.';
        return;
    }

    $url = API_URL . '/';
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => '5',
        'blocking' => true,
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        echo 'Error en la solicitud de productos: ' . $response->get_error_message();
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'Error al decodificar JSON de la solicitud de productos: ' . json_last_error_msg();
        return;
    }

    // echo '<pre>';
    // print_r($json);
    // echo '</pre>';

    foreach ($json as $row) {
        $id = null;
        $attributes = [];

        $category_term = term_exists($row['category'], 'product_cat');
        if ($category_term == null && !is_numeric($row['category'])) {
            if ($row['category'] != '') {
                $category_term = wp_insert_term($row['category'], 'product_cat');
            }
        }

        $id = wc_get_product_id_by_sku(get_option('nb_prefix') . $row['sku']);
        if ($id) {
            echo '<li>Actualizado: ' . esc_html($row['title']) . "</li>";
        } elseif ($row['amountStock'] > 0) {
            $product_data = array(
                'post_title'   => $row['title'],
                'post_type'    => 'product',
                'post_status'  => 'publish',
            );
            $id = wp_insert_post($product_data);
            echo '<li>Creado: ' . esc_html($row['title']) . "</li>";
        }

        if ($id) {
            $price = $row['price']['finalPriceWithUtility'] * $row['cotizacion'];
            $product = wc_get_product($id);
            $product->set_sku(get_option('nb_prefix') . $row['sku']);
            $product->set_short_description(get_option('nb_description'));
            $product->set_category_ids(array($category_term['term_id']));
            $product->set_regular_price($price);
            $product->set_manage_stock(true);
            $product->set_stock_quantity($row['amountStock']);
            $product->set_stock_status($row['amountStock'] > 0 ? 'instock' : 'outofstock');
            $product->save();

            if (is_plugin_active('featured-image-from-url/featured-image-from-url.php') || is_plugin_active('fifu-premium/fifu-premium.php')) {
                fifu_dev_set_image($id, $row['mainImage']);
            }
        }
    }
    update_option('nb_last_update', date("Y-m-d H:i", strtotime('-1 minute')));
}

function nb_menu()
{
    add_options_page('Conector NB', 'Conector NB', 'manage_options', 'nb', 'nb_options_page');
}

function nb_register_settings()
{
    register_setting('nb_options', 'nb_user');
    register_setting('nb_options', 'nb_password');
    register_setting('nb_options', 'nb_token');
    register_setting('nb_options', 'nb_prefix');
    register_setting('nb_options', 'nb_description');
}

function nb_options_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    echo '<div class="wrap">';
    echo '<h1 style="display:flex; align-items:center; gap:10px;">Conector NB</h1>';
    echo '<p>Gracias por utilizar nuestro conector de productos exclusivo de NewBytes.</p>';
    if (!is_plugin_active('featured-image-from-url/featured-image-from-url.php')) {
        echo '<p><strong>Para el funcionamiento de las imágenes se requiere la instalación del plugin: ';
        echo '<a href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=featured-image-from-url'), 'install-plugin_featured-image-from-url').'">FIFU (Featured Image From URL)</a>';
        echo '</strong></p>';
    }
    echo '<form method="post" action="options.php">';
    settings_fields('nb_options');
    do_settings_sections('nb_options');
    echo '<table class="form-table" role="presentation">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th scope="row">Usuario *</th>';
    echo '<td><input type="text" name="nb_user" id="nb_user" value="' . esc_attr(get_option('nb_user')) . '" required /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Contraseña *</th>';
    echo '<td><input type="password" name="nb_password" id="nb_password" value="' . esc_attr(get_option('nb_password')) . '" required /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Prefijo SKU *</th>';
    echo '<td><input type="text" name="nb_prefix" id="nb_prefix" value="' . esc_attr(get_option('nb_prefix')) . '" required placeholder="Ejemplo: NB_" />';
    echo '<p class="description">Se colocará este prefijo al comienzo de cada SKU para que puedas filtrar tus productos.</p></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Descripción corta</th>';
    echo '<td><textarea name="nb_description" id="nb_description">' . esc_attr(get_option('nb_description')) . '</textarea>';
    echo '<p class="description">Se agregará esta descripción en todos los productos.</p></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Última actualización</th>';
    echo '<td>'.esc_attr(get_option('nb_last_update') != '' ? date('d/m/Y H:i', strtotime(get_option('nb_last_update').'-3 hours')) : '--').'</td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    submit_button();
    echo '</form>';
    echo '<form method="post">';
    echo '<p>Si cambiaste los markups o algún ajuste, puedes resincronizar todos los productos:</p>';
    echo '<input type="hidden" name="update_all"/>';
    echo '<button type="submit" class="button button-secondary">Actualizar todo</button>';
    echo '</form>';
    echo '</div>';

    if(isset($_POST['update_all'])) {
        delete_option('nb_last_update');
        echo '<p><details><summary><strong>Ver productos creados y actualizados:</strong></summary>';
        echo '<ul>'.nb_callback(true).'</ul>';
        echo '</details></p>';
    }
}

function nb_callback_full()
{
    nb_callback(true);
}

function nb_cron_interval($schedules)
{
    $schedules['every_minute'] = array(
        'interval'  => 60,
        'display'   => 'Every minute'
    );
    return $schedules;
}

function nb_activation()
{
    wp_schedule_event(time(), 'every_minute', 'nb_cron_hook');
}

function nb_deactivation()
{
    wp_clear_scheduled_hook('nb_cron_hook');
}


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'nb_plugin_action_links');
add_action('admin_menu', 'nb_menu');
add_action('admin_init', 'nb_register_settings');
add_action('nb_cron_hook', 'nb_callback');
add_filter('cron_schedules', 'nb_cron_interval');
register_activation_hook(__FILE__, 'nb_activation');
register_deactivation_hook(__FILE__, 'nb_deactivation');
