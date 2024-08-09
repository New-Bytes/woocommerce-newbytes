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
            'mode' => 'wp-extension',
            'domain' =>  home_url()
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

    nb_show_error_message('Token no encontrado en la respuesta: ' . json_encode($json));
    return null;
}
function output_response($data)
{
    echo json_encode($data);
}

function nb_callback($update_all = false)
{
    try {
        // Guardar límites originales
        $original_max_execution_time = ini_get('max_execution_time');
        $original_memory_limit = ini_get('memory_limit');

        // Establecer nuevos límites
        ini_set('max_execution_time', '1800'); // 30 minutos
        ini_set('memory_limit', '2048M'); // 2 GB

        $start_time = microtime(true); // Tiempo de inicio

        $token = get_option('nb_token') ? get_option('nb_token') : nb_get_token();
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
        $args = array(
            'limit'    => -1,
            'return'   => 'ids',
            'paginate' => false,
            'meta_query' => array(
                array(
                    'key'     => '_sku',
                    'value'   => '^' . $prefix,
                    'compare' => 'REGEXP'
                )
            )
        );

        $existing_products = wc_get_products($args);
        $existing_skus = array();

        foreach ($existing_products as $product_id) {
            $product = wc_get_product($product_id);
            $existing_skus[$product->get_sku()] = $product_id;
        }

        // Procesamiento de productos
        $updated_count = 0;
        $created_count = 0;
        $categories_cache = array();

        foreach ($json as $row) {
            $id = null;

            // Manejo de categoría
            if (!isset($categories_cache[$row['category']])) {
                $category_term = term_exists($row['category'], 'product_cat');
                if (!$category_term && !is_numeric($row['category'])) {
                    if ($row['category'] != '') {
                        $category_term = wp_insert_term($row['category'], 'product_cat');
                    }
                }
                $categories_cache[$row['category']] = $category_term ? $category_term['term_id'] : null;
            } else {
                $category_term = $categories_cache[$row['category']];
            }

            $sku = $prefix . $row['sku'];

            // Validar si el SKU ya existe
            if (isset($existing_skus[$sku])) {
                $id = $existing_skus[$sku];
                $updated_count++;
            } elseif ($row['amountStock'] > 0) {
                $product_data = array(
                    'post_title'   => $row['title'],
                    'post_type'    => 'product',
                    'post_status'  => 'publish',
                );
                $id = wp_insert_post($product_data);
                $created_count++;
            }

            if ($id) {
                try {
                    $price = $row['price']['finalPrice'] * $row['cotizacion'];

                    if (isset($row['price']['finalPriceWithUtility']) && $row['price']['finalPriceWithUtility'] > 0) {
                        $price = $row['price']['finalPriceWithUtility'] * $row['cotizacion'];
                    }

                    $product = wc_get_product($id);
                    $product->set_sku($sku);
                    $product->set_short_description(get_option('nb_description'));
                    if ($category_term) {
                        $product->set_category_ids(array($category_term));
                    }
                    $product->set_regular_price($price);
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($row['amountStock']);
                    $product->set_stock_status($row['amountStock'] > 0 ? 'instock' : 'outofstock');
                    $product->set_weight($row['weightAverage']);
                    $product->set_width($row['widthAverage']);
                    $product->set_length($row['lengthAverage']);
                    $product->set_height($row['highAverage']);
                    $product->save();

                    // Optimización de imagen destacada
                    if (!empty($row['mainImage']) && (is_plugin_active('featured-image-from-url/featured-image-from-url.php') || is_plugin_active('fifu-premium/fifu-premium.php'))) {
                        fifu_dev_set_image($id, $row['mainImage']);
                    }
                } catch (Exception $e) {
                    // Manejar errores relacionados con SKU duplicados o inválidos
                    error_log('Error al procesar el producto con SKU ' . $sku . ': ' . $e->getMessage());
                    continue; // Saltar este producto y continuar con los siguientes
                }
            }
        }

        update_option('nb_last_update', date("Y-m-d H:i"));

        $end_time = microtime(true); // Tiempo de finalización
        $sync_duration = $end_time - $start_time;

        $hours = floor($sync_duration / 3600);
        $minutes = floor(($sync_duration % 3600) / 60);
        $seconds = $sync_duration % 60;

        $response_data = array(
            'updated' => $updated_count,
            'created' => $created_count,
            'sync_duration' => array(
                'hours' => $hours,
                'minutes' => $minutes,
                'seconds' => number_format($seconds, 2)
            )
        );

        output_response($response_data);

        // Restaurar límites originales
        ini_set('max_execution_time', $original_max_execution_time);
        ini_set('memory_limit', $original_memory_limit);
    } catch (Exception $e) {
        // Captura la excepción y muestra el mensaje de error
        echo 'Error: ' . $e->getMessage();

        // Opcional: puedes registrar el error en un archivo de log
        error_log('Excepción capturada: ' . $e->getMessage() . ' en ' . $e->getFile() . ' en la línea ' . $e->getLine());

        // Opcional: puedes proporcionar más información si es necesario
        echo ' Archivo: ' . $e->getFile() . ' Línea: ' . $e->getLine();
    }
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

    $latest_commit = get_latest_commit();
    $current_version = latest_version_of_nb();
    $show_new_version_button = ($latest_commit !== $current_version);

    // if ($show_new_version_button) {
    //     echo '<form method="post" style="margin-top: 20px;">';
    //     echo '<button type="button" id="update-connector-btn" style="
    //         min-width: 130px;
    //         height: 40px;
    //         color: #fff;
    //         padding: 5px 10px;
    //         font-weight: bold;
    //         cursor: pointer;
    //         border-radius: 5px;
    //         border: none;
    //         background-color: #FFC300;
    //     ">Actualizar Conector NB</button>';
    //     echo '</form>';
    // } else {
    //     echo '<form method="post" style="margin-top: 20px;">';
    //     echo '<button type="button" style="
    //         min-width: 130px;
    //         height: 40px;
    //         color: #fff;
    //         padding: 5px 10px;
    //         font-weight: bold;
    //         cursor: not-allowed;
    //         border-radius: 5px;
    //         border: none;
    //         background-color: #e0e0e0;
    //     " disabled>Actualizado</button>';
    //     echo '</form>';
    // }


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

    if (isset($_POST['update_all'])) {
        echo '<p><details><summary><strong>Respuesta del conector NB</strong></summary>';
        echo '<ul>' . nb_callback(true) . '</ul>';
        echo '</details></p>';
        nb_show_last_update();
    }

    echo '<div id="update-connector-modal" style="
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
        z-index: 9999;
    ">
        <div style="
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            position: relative;
        ">
            <h2>Actualizar Conector NB</h2>
            <p>Hay una nueva versión disponible para descargar.</p>
            <p><strong>Paso 1:</strong> Descarga el archivo <strong>.zip</strong> con la última versión de <strong> Conector NB</strong>.</p>
            <p><strong>Paso 2:</strong> Borra la extensión actual de <strong>NewBytes</strong> en tu wordpress.</p>
            <p><strong>Paso 3:</strong> Desde plugins instala la ultima versión del <strong>Conector NB</strong>.</p>
            <a href="https://github.com/New-Bytes/woocommerce-newbytes/archive/refs/heads/main.zip" download style="
                display: inline-block;
                background-color: #FFC300;
                color: #fff;
                padding: 10px 20px;
                border-radius: 5px;
                text-decoration: none;
                font-weight: bold;
            ">Descargar .zip</a>
            <button id="close-modal-btn" style="
                display: block;
                margin-top: 10px;
                background-color: #e0e0e0;
                color: #333;
                border: none;
                padding: 10px;
                border-radius: 5px;
                cursor: pointer;
            ">Cerrar</button>
        </div>
    </div>';

    echo '<script>
    document.getElementById("update-connector-btn").addEventListener("click", function() {
        document.getElementById("update-connector-modal").style.display = "flex";
    });

    document.getElementById("close-modal-btn").addEventListener("click", function() {
        document.getElementById("update-connector-modal").style.display = "none";
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

function get_latest_commit()
{
    $response = wp_remote_get('https://api.github.com/repos/New-Bytes/woocommerce-newbytes/commits');

    if (is_wp_error($response)) {
        return 'Error fetching commit data';
    }

    $body = wp_remote_retrieve_body($response);
    $commits = json_decode($body, true);

    if (isset($commits[0]['sha'])) {
        return $commits[0]['sha'];
    } else {
        return 'No commits found';
    }
}

function latest_version_of_nb()
{
    return '8290e38b786a75dd27cd1f9cdea7e49c90983ed5';
}


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'nb_plugin_action_links');
add_action('admin_menu', 'nb_menu');
add_action('admin_init', 'nb_register_settings');
add_action('nb_cron_hook', 'nb_callback');
add_filter('cron_schedules', 'nb_cron_interval');
register_activation_hook(__FILE__, 'nb_activation');
register_deactivation_hook(__FILE__, 'nb_deactivation');


// Registra el endpoint REST personalizado
add_action('rest_api_init', function () {
    register_rest_route('nb/v1', '/sync', array(
        'methods' => 'POST',
        'callback' => 'nb_sync_catalog',
        'permission_callback' => '__return_true',
    ));
});


// Función de callback para el endpoint de sincronización
function nb_sync_catalog(WP_REST_Request $request)
{
    // Llamar a la función de sincronización del catálogo
    nb_callback();

    // Devolver una respuesta exitosa
    return new WP_REST_Response('Sincronización completada', 200);
}
