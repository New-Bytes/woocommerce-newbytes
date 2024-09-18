<?php
/*
Plugin Name: Conector NewBytes
Description: Sincroniza los productos del catálogo de NewBytes con WooCommerce.
Author: NewBytes
Author URI: https://nb.com.ar
Version: 0.0.8
*/

const API_URL_NB = 'https://api.nb.com.ar/v1';
const VERSION_NB = '0.0.8';

function nb_plugin_action_links($links)
{
    $settings = '<a href="' . get_admin_url(null, 'options-general.php?page=nb') . '">Ajustes</a>';
    array_unshift($links, $settings);
    return $links;
}

function nb_get_token()
{
    try {
        // Siempre solicitar un nuevo token
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'user' => get_option('nb_user'),
                'password' => get_option('nb_password'),
                'mode' => 'wp-extension',
                'domain' => home_url()
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
            return $json['token'];
        }

        nb_show_error_message('Token no encontrado en la respuesta: ' . json_encode($json));
        return null;
    } catch (Exception $e) {
        echo $e->getMessage();
        return null;
    }
}

function output_response($data)
{
    echo json_encode($data);
}

function nb_callback($update_all = false)
{
    try {
        error_log('nb_callback ejecutado a las: ' . date('Y-m-d H:i:s'), 3, __DIR__ . '/debug-newbytes.txt');

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
                    $price = $row['price']['finalPrice'] * $row['cotizacion'];

                    if (isset($row['price']['finalPriceWithUtility']) && $row['price']['finalPriceWithUtility'] > 0) {
                        $price = $row['price']['finalPriceWithUtility'] * $row['cotizacion'];
                    }

                    $product = wc_get_product($id);
                    $product->set_sku($sku);
                    $product->set_short_description(get_option('nb_description'));

                    // Manejo de categoría del usuario o categoría original de la API
                    $category_to_use = !empty($row['categoryDescriptionUser']) ? $row['categoryDescriptionUser'] : $row['category'];

                    if (!isset($categories_cache[$category_to_use])) {
                        $category_term = term_exists($category_to_use, 'product_cat');
                        if (!$category_term && !is_numeric($category_to_use)) {
                            if ($category_to_use != '') {
                                $category_term = wp_insert_term($category_to_use, 'product_cat');
                            }
                        }
                        $categories_cache[$category_to_use] = $category_term ? $category_term['term_id'] : null;
                    } else {
                        $category_term = $categories_cache[$category_to_use];
                    }

                    if ($category_term) {
                        $product->set_category_ids(array($category_term));
                    }

                    $product->set_regular_price($price);
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($row['amountStock']);
                    $product->set_stock_status($row['amountStock'] > 0 ? 'instock' : 'outofstock');
                    $product->set_weight($row['weightAverage'] / 1000); # gr a kg
                    $product->set_width($row['widthAverage'] / 10);     # mm a cm
                    $product->set_length($row['lengthAverage'] / 10);   # mm a cm
                    $product->set_height($row['highAverage'] / 10);     # mm a cm
                    $product->save();

                    # Optimización de imagen destacada
                    if ((is_plugin_active('featured-image-from-url/featured-image-from-url.php') || is_plugin_active('fifu-premium/fifu-premium.php'))) {
                        # Verifica si 'mainImageExp' no está vacío, de lo contrario usar 'mainImage'
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

        update_option('nb_last_update', date("Y-m-d H:i"));

        $end_time = microtime(true); // Tiempo de finalización
        $sync_duration = $end_time - $start_time;

        $hours = floor($sync_duration / 3600);
        $minutes = floor(($sync_duration % 3600) / 60);
        $seconds = $sync_duration % 60;

        $response_data = array(
            'updated' => $updated_count,
            'created' => $created_count,
            'deleted' => $delete_result['deleted'],
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
        echo 'Error: ' . $e->getMessage();
        error_log('Excepción capturada: ' . $e->getMessage() . ' en ' . $e->getFile() . ' en la línea ' . $e->getLine());
    }
}

function nb_delete_products_by_prefix($existing_skus, $prefix)
{
    global $wpdb;

    try {
        $start_time = microtime(true); // Tiempo de inicio del proceso

        // Escapar los SKUs existentes para la consulta
        $escaped_skus = array_map(function ($sku) use ($wpdb) {
            return $wpdb->prepare('%s', $sku);
        }, $existing_skus);
        $escaped_skus_list = implode(',', $escaped_skus);

        // Eliminar productos con el prefijo especificado que no están en la lista de SKUs existentes
        $deleted_count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->posts} 
                 WHERE post_type = 'product' 
                 AND post_status = 'publish' 
                 AND ID IN (
                     SELECT post_id FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_sku' 
                     AND meta_value REGEXP %s
                     AND meta_value NOT IN ({$escaped_skus_list})
                 )",
                '^' . $prefix
            )
        );

        $end_time = microtime(true); // Tiempo de finalización del proceso
        $sync_duration = $end_time - $start_time;

        $hours = floor($sync_duration / 3600);
        $minutes = floor(($sync_duration % 3600) / 60);
        $seconds = $sync_duration % 60;

        return array(
            'deleted' => $deleted_count,
            'sync_duration' => array(
                'hours' => $hours,
                'minutes' => $minutes,
                'seconds' => number_format($seconds, 2)
            )
        );
    } catch (Exception $e) {
        error_log('Error al eliminar productos: ' . $e->getMessage());
        return array('error' => $e->getMessage());
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
    register_setting('nb_options', 'nb_sync_interval');
}

function nb_options_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $plugin_url = plugin_dir_url(__FILE__);
    $icon_url = $plugin_url . 'assets/icon-128x128.png';

    $latest_commit = get_latest_version_nb();
    $show_new_version_button = ($latest_commit !== VERSION_NB);

    if ($show_new_version_button) {
        echo '<form method="post" style="margin-top: 20px;">';
        echo '<button type="button" id="update-connector-btn" style="
            min-width: 130px;
            height: 40px;
            color: #fff;
            padding: 5px 10px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
            border: none;
            background-color: #FFC300;
        ">Actualizar Conector NB</button>';
        echo '</form>';
    } else {
        echo '<form method="post" style="margin-top: 20px;">';
        echo '<button type="button" style="
            min-width: 130px;
            height: 40px;
            color: #fff;
            padding: 5px 10px;
            font-weight: bold;
            cursor: not-allowed;
            border-radius: 5px;
            border: none;
            background-color: #e0e0e0;
        " disabled>Actualizado: ' . VERSION_NB . '</button>';
        echo '</form>';
    }

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
    echo '<tr>';
    echo '<th scope="row">Intervalo de sincronización automática</th>';
    echo '<td><select name="nb_sync_interval" id="nb_sync_interval">';
    $intervals = array(
        '3600'  => 'Cada 1 hora',
        '7200'  => 'Cada 2 horas',
        '10800' => 'Cada 3 horas',
        '14400' => 'Cada 4 horas',
        '18000' => 'Cada 5 horas',
        '21600' => 'Cada 6 horas',
        '25200' => 'Cada 7 horas',
        '28800' => 'Cada 8 horas',
        '32400' => 'Cada 9 horas',
        '36000' => 'Cada 10 horas',
        '39600' => 'Cada 11 horas',
        '43200' => 'Cada 12 horas'
    );

    $current_interval = get_option('nb_sync_interval', 3600); // Valor por defecto 1 hora
    foreach ($intervals as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($current_interval, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Selecciona el intervalo en el que deseas que se sincronice automáticamente.</p></td>';
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
                cursor: pointer">
                Cerrar</button>
            </div>
        </div>';

    btn_delete_products();
    modal_confirm_delete_products();
    js_handler_modals();
}

function nb_delete_products()
{
    global $wpdb;

    try {
        $original_max_execution_time = ini_get('max_execution_time');
        $original_memory_limit       = ini_get('memory_limit');

        ini_set('max_execution_time', '1800'); // 30 minutos
        ini_set('memory_limit', '2048M'); // 2 GB

        $start_time = microtime(true); // Tiempo de inicio del proceso

        $prefix = get_option('nb_prefix');
        if (!$prefix) {
            wp_send_json_error('No se encontró el prefijo del SKU.');
            return;
        }

        # Eliminar productos con el prefijo especificado
        $deleted_count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->posts} 
                 WHERE post_type = 'product' 
                 AND post_status = 'publish' 
                 AND ID IN (
                     SELECT post_id FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_sku' 
                     AND meta_value REGEXP %s
                 )",
                '^' . $prefix
            )
        );

        update_option('nb_last_update', date("Y-m-d H:i"));

        $end_time      = microtime(true); // Tiempo de finalización del proceso
        $sync_duration = $end_time - $start_time;

        $hours   = floor($sync_duration / 3600);
        $minutes = floor(($sync_duration % 3600) / 60);
        $seconds = $sync_duration % 60;

        $response_data = array(
            'deleted'       => $deleted_count,
            'sync_duration' => array(
                'hours'   => $hours,
                'minutes' => $minutes,
                'seconds' => number_format($seconds, 2)
            )
        );

        wp_send_json_success($response_data);

        ini_set('max_execution_time', $original_max_execution_time);
        ini_set('memory_limit', $original_memory_limit);
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}


function modal_confirm_delete_products()
{
    echo '<div id="delete-confirm-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 9999;">
        <div style="background: white; padding: 20px; border-radius: 10px; text-align: center; max-width: 400px; width: 100%;">
            <h2>Advertencia</h2>
            <p>Esta acción eliminará todos los productos de NewBytes.</p>
            <form id="confirm-delete-form" style="display: inline;">
                <input type="hidden" name="action" value="nb_delete_products" />
                <input type="hidden" name="delete_all" value="1" />';
    wp_nonce_field('nb_delete_all', 'nb_delete_all_nonce');
    echo '  <button type="button" id="confirm-delete-btn" class="button" style="
                    background-color: #f55a39;
                    min-width: 130px;
                    height: 40px;
                    color: #fff;
                    border: none;
                    padding: 5px 10px;
                    font-weight: bold;
                    border-radius: 5px;
                    cursor: pointer;">
                        Eliminar
                    </button>
                    <button type="button" id="cancel-delete" class="button"
                    style="
                    min-width: 130px;
                    height: 40px;
                    background-color: #e0e0e0;
                    color: #333;
                    border: none;
                    padding: 5px 10px;
                    font-weight: bold;
                    border-radius: 5px;
                    cursor: pointer;">
                        Cancelar
                    </button>
                </form>
            </div>
        </div>';
}

function btn_delete_products()
{
    echo '<button type="button" class="button button-secondary" id="delete-all-btn" style="margin-top: 20px; border: none; background-color: #f55a39; color: #fff;">
        Eliminar Productos
    </button>';
}

function js_handler_modals()
{
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Mostrar el modal de actualización cuando se haga clic en el botón "Actualizar Conector NB"
        var updateConnectorBtn = document.getElementById("update-connector-btn");
        var updateConnectorModal = document.getElementById("update-connector-modal");
        var closeModalBtn = document.getElementById("close-modal-btn");

        if (updateConnectorBtn && updateConnectorModal && closeModalBtn) {
            updateConnectorBtn.addEventListener("click", function() {
                updateConnectorModal.style.display = "flex";
            });

            closeModalBtn.addEventListener("click", function() {
                updateConnectorModal.style.display = "none";
            });

            // Ocultar el modal de actualización cuando se haga clic fuera del modal
            updateConnectorModal.addEventListener("click", function(event) {
                if (event.target === this) {
                    updateConnectorModal.style.display = "none";
                }
            });
        }

        // Mostrar el modal de confirmación cuando se haga clic en el botón "Eliminar Productos"
        var deleteAllBtn = document.getElementById("delete-all-btn");
        var deleteConfirmModal = document.getElementById("delete-confirm-modal");
        var cancelDeleteBtn = document.getElementById("cancel-delete");
        var confirmDeleteBtn = document.getElementById("confirm-delete-btn");
        var confirmDeleteForm = document.getElementById("confirm-delete-form");

        if (deleteAllBtn && deleteConfirmModal && cancelDeleteBtn && confirmDeleteBtn) {
            deleteAllBtn.addEventListener("click", function() {
                deleteConfirmModal.style.display = "flex";
            });

            cancelDeleteBtn.addEventListener("click", function() {
                deleteConfirmModal.style.display = "none";
            });

            // Ocultar el modal de confirmación cuando se haga clic fuera del modal
            deleteConfirmModal.addEventListener("click", function(event) {
                if (event.target === this) {
                    deleteConfirmModal.style.display = "none";
                }
            });

            confirmDeleteBtn.addEventListener("click", function() {
                var formData = new FormData(confirmDeleteForm);
                fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        alert("Productos eliminados exitosamente.");
                        deleteConfirmModal.style.display = "none";
                    } else {
                        alert("Error: " + data.data);
                    }
                }).catch(error => {
                    console.error("Error:", error);
                });
            });
        }
    });
    </script>';
}

function enqueue_fontawesome()
{
    wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
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

function nb_activation()
{
    nb_update_cron_schedule();
}

function nb_deactivation()
{
    $timestamp = wp_next_scheduled('nb_cron_sync_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'nb_cron_sync_event');
    }
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

function get_latest_version_nb()
{
    // URL del archivo PHP que contiene la versión
    $file_url = 'https://raw.githubusercontent.com/New-Bytes/woocommerce-newbytes/main/woocommerce-newbytes.php';

    // Obtener el contenido del archivo
    $response = wp_remote_get($file_url);

    if (is_wp_error($response)) {
        return 'Error fetching version data';
    }

    $body = wp_remote_retrieve_body($response);

    // Buscar la línea que contiene la versión
    preg_match('/Version:\s*(\S+)/', $body, $matches);

    if (isset($matches[1])) {
        return $matches[1];
    } else {
        return 'Version not found';
    }
}

add_action('admin_enqueue_scripts', 'enqueue_fontawesome');
add_action('wp_ajax_nb_delete_products', 'nb_delete_products');
add_action('admin_post_nb_delete_products', 'nb_delete_products');


add_action('update_option_nb_sync_interval', 'nb_update_cron_schedule', 10, 2);

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'nb_plugin_action_links');


add_filter('cron_schedules', 'nb_cron_interval');


add_action('admin_menu', 'nb_menu');
add_action('admin_init', 'nb_register_settings');

add_action('nb_cron_sync_event', 'nb_callback');

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
