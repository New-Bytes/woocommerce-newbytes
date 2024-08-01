<?php
/*
Plugin Name: Conector NewBytes
Description: Sincroniza los productos del catálogo de NewBytes con WooCommerce.
Author: NewBytes
Author URI: https://nb.com.ar
Version: 0.0.1
*/

const API_URL_NB = 'https://api.nb.com.ar/v1';
const VERSION = '0.0.1';

function nb_plugin_action_links($links)
{
    $settings = '<a href="' . get_admin_url(null, 'options-general.php?page=nb') . '">Ajustes</a>';
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
            'mode' => 'wp-extension'
        )),
        'timeout' => '5',
        'blocking' => true,
    );

    $response = wp_remote_post(API_URL_NB . '/auth/login', $args);

    if (is_wp_error($response)) {
        nb_show_error_message('Error en la solicitud de token: ' . $response->get_error_message());
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        nb_show_error_message('Error al decodificar JSON de la solicitud de token: ' . json_last_error_msg());
        return null;
    }

    if (isset($json['token'])) {
        update_option('nb_token', $json['token']);
        return $json['token'];
    }

    nb_show_error_message('Token no encontrado en la respuesta: ' . $json);
    return null;
}

function nb_callback($update_all = false)
{
    $start_time = microtime(true); // Tiempo de inicio

    $token = get_option('nb_token') ? get_option('nb_token') : nb_get_token();
    if (!$token) {
        nb_show_error_message('No fue posible obtener el token.');
        return;
    }

    $url  = API_URL_NB . '/';
    $args = array(
        'headers'  => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json'
        ),
        'timeout'  => '30',
        'blocking' => true,
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        nb_show_error_message('Error en la solicitud de productos: ' . $response->get_error_message());
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        nb_show_error_message('Error al decodificar JSON de la solicitud de productos: ' . json_last_error_msg());
        return;
    }

    foreach ($json as $row) {
        $id = null;

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
            $price = $row['price']['finalPrice'] * $row['cotizacion'];

            if (isset($row['price']['finalPriceWithUtility']) && $row['price']['finalPriceWithUtility'] > 0) {
                $price = $row['price']['finalPriceWithUtility'] * $row['cotizacion'];
            }

            $product = wc_get_product($id);
            $product->set_sku(get_option('nb_prefix') . $row['sku']);
            $product->set_short_description(get_option('nb_description'));
            $product->set_category_ids(array($category_term['term_id']));
            $product->set_regular_price($price);
            $product->set_manage_stock(true);
            $product->set_stock_quantity($row['amountStock']);
            $product->set_stock_status($row['amountStock'] > 0 ? 'instock' : 'outofstock');
            $product->set_weight($row['weightAverage']);
            $product->set_width($row['widthAverage']);
            $product->set_length($row['lengthAverage']);
            $product->set_height($row['highAverage']);
            $product->save();

            if (is_plugin_active('featured-image-from-url/featured-image-from-url.php') || is_plugin_active('fifu-premium/fifu-premium.php')) {
                fifu_dev_set_image($id, $row['mainImage']);
            }
        }
    }
    update_option('nb_last_update', date("Y-m-d H:i"));

    $end_time = microtime(true); // Tiempo de finalización
    $sync_duration = $end_time - $start_time;

    $hours = floor($sync_duration / 3600);
    $minutes = floor(($sync_duration % 3600) / 60);
    $seconds = $sync_duration % 60;

    echo 'Sincronización completada en ' . $hours . ' horas, ' . $minutes . ' minutos y ' . number_format($seconds, 2) . ' segundos.';
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

    $plugin_url = plugin_dir_url(__FILE__);
    $icon_url = $plugin_url . 'assets/icon-128x128.png';

    echo '<div class="wrap" style="display: flex; justify-content: center; align-items: center; height: 100%;">';
    echo '<div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); text-align: center; max-width: 600px; width: 100%;">';
    echo '<img src="' . esc_url($icon_url) . '" alt="Logo" style="width: 128px; height: 128px; margin-bottom: 20px;">';
    echo '<h1 style="display: flex; align-items: center; justify-content: center; gap: 10px;">Conector NewBytes</h1>';
    echo '<p>Gracias por utilizar nuestro conector de productos exclusivo de NewBytes.</p>';
    echo '<p>Si no tienes credenciales, puedes visitar la <a href="https://developers.nb.com.ar/" target="_blank">documentación oficial de NewBytes</a>.</p>';
    if (!is_plugin_active('featured-image-from-url/featured-image-from-url.php')) {
        echo '<p><strong>Para el funcionamiento de las imágenes se requiere la instalación del plugin: ';
        echo '<a href="' . wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=featured-image-from-url'), 'install-plugin_featured-image-from-url') . '">FIFU (Featured Image From URL)</a>';
        echo '</strong></p>';
    }
    echo '<form method="post" action="options.php" style="display: inline-block; text-align: left;">';
    settings_fields('nb_options');
    do_settings_sections('nb_options');
    echo '<table class="form-table" role="presentation" style="margin: 0 auto;">';
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
    echo '<td id=last_update>' . esc_attr(get_option('nb_last_update') != '' ? date('d/m/Y H:i', strtotime(get_option('nb_last_update') . '-3 hours')) : '--') . '</td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    submit_button();
    echo '</form>';
    echo '<form method="post" style="margin-top: 20px;">';
    echo '<p>Si cambiaste los markups o realizaste algún ajuste, puedes resincronizar todos los productos:</p>';
    echo '<input type="hidden" name="update_all"/>';
    echo '<button type="submit" class="button button-secondary" id="update-all-btn">';
    echo '<span id="update-all-text">Actualizar todo</span>';
    echo '<span id="update-all-spinner" style="display: none;">';
    echo '<i class="fas fa-spinner fa-spin" style="font-size: 20px;"></i>';
    echo '</span>';
    echo '</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    if (isset($_POST['update_all'])) {
        echo '<p><details><summary><strong>Respuesta del conector NB</strong></summary>';
        echo '<ul>' . nb_callback(true) . '</ul>';
        echo '</details></p>';
        nb_show_last_update();
    }

    echo '<script>
    document.getElementById("update-all-btn").addEventListener("click", function() {
        document.getElementById("update-all-text").style.display = "none";
        document.getElementById("update-all-spinner").style.display = "inline-block";
    });
    </script>';
}

// Asegúrate de incluir FontAwesome en tu tema
function enqueue_fontawesome()
{
    wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
}
add_action('admin_enqueue_scripts', 'enqueue_fontawesome');

function nb_callback_full()
{
    nb_callback(true);
}

function nb_cron_interval($schedules)
{
    $schedules['every_hour'] = array(
        'interval'  => 3600,
        'display'   => 'Every hour'
    );
    return $schedules;
}

function nb_activation()
{
    wp_schedule_event(time(), 'every_hour', 'nb_cron_hook');
}

function nb_deactivation()
{
    wp_clear_scheduled_hook('nb_cron_hook');
}

function nb_show_error_message($error)
{
    echo '<p style="color: red;">' . $error . '</p>';
}

function nb_show_last_update()
{
    $last_update = esc_attr(get_option('nb_last_update') != '' ? date('d/m/Y H:i', strtotime(get_option('nb_last_update') . '-3 hours')) : '--');
    echo '<script>
    document.getElementById("last_update").innerText = "' . $last_update . '";
    </script>';
}


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'nb_plugin_action_links');
add_action('admin_menu', 'nb_menu');
add_action('admin_init', 'nb_register_settings');
add_action('nb_cron_hook', 'nb_callback');
add_filter('cron_schedules', 'nb_cron_interval');
register_activation_hook(__FILE__, 'nb_activation');
register_deactivation_hook(__FILE__, 'nb_deactivation');
