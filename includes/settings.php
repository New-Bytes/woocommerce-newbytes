<?php

function nb_options_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $plugin_url = plugin_dir_url(__FILE__);
    $icon_url = $plugin_url . '../assets/icon-128x128.png';

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
    // Add User
    echo '<tr>';
    echo '<th scope="row">Usuario *</th>';
    echo '<td><input type="text" name="nb_user" id="nb_user" value="' . esc_attr(get_option('nb_user')) . '" required /></td>';
    echo '</tr>';
    // Add Password
    echo '<tr>';
    echo '<th scope="row">Contraseña *</th>';
    echo '<td><input type="password" name="nb_password" id="nb_password" value="' . esc_attr(get_option('nb_password')) . '" required /></td>';
    echo '</tr>';
    // Add Prefix SKU
    echo '<tr>';
    echo '<th scope="row">Prefijo SKU *</th>';
    echo '<td><input type="text" name="nb_prefix" id="nb_prefix" value="' . esc_attr(get_option('nb_prefix')) . '" required placeholder="Ejemplo: NB_" />';
    echo '<p class="description">Se colocará este prefijo al comienzo de cada SKU para que puedas filtrar tus productos.</p></td>';
    echo '</tr>';
    // Add description
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
    // Add Sync No IVA
    echo '<tr>';
    echo '<th scope="row">Sincronizar sin IVA</th>';
    echo '<td><input type="checkbox" name="nb_sync_no_iva" id="nb_sync_no_iva" value="1" ' . checked(1, get_option('nb_sync_no_iva'), false) . ' />';
    echo '<p class="description">Selecciona esta opción si deseas sincronizar los productos sin IVA.</p></td>';
    echo '</tr>';
    // Add Sync USD
    echo '<tr>';
    echo '<th scope="row">Sincronizar en USD</th>';
    echo '<td><input type="checkbox" name="nb_sync_usd" id="nb_sync_usd" value="1" ' . checked(1, get_option('nb_sync_usd'), false) . ' />';
    echo '<p class="description">Selecciona esta opción si deseas sincronizar los productos en USD.</p></td>';
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
    echo '<i class="fas fa-spinner fa-spin" style="font-size: 20px;"></i> Sincronizando artículos...';
    echo '</span>';
    echo '</button>';
    echo '</form>';

    if (isset($_POST['update_all'])) {
        echo '<p><details><summary><strong>Respuesta del conector NB</strong></summary>';
        echo '<ul>' . nb_callback() . '</ul>';
        echo '</details></p>';
        nb_show_last_update();
    }

    btn_update_description_products();
    modal_confirm_update_();

    btn_delete_products();
    modal_confirm_delete_products();
    js_handler_modals();

    echo '<script>
        jQuery(document).ready(function($) {
            $("#update-connector-btn").on("click", function() {
                if (confirm("¿Estás seguro de que deseas actualizar el conector NB?")) {
                    var $btn = $(this);
                    $btn.prop("disabled", true);
                    $btn.html("<i class=\'fas fa-spinner fa-spin\'></i> Actualizando...");
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "nb_update_connector"
                        },
                        success: function(response) {
                            alert(response);
                            location.reload();
                        },
                        error: function() {
                            alert("Error al actualizar el conector NB.");
                            $btn.prop("disabled", false);
                            $btn.html("Actualizar Conector NB");
                        }
                    });
                }
            });

            $("#update-all-btn").on("click", function() {
                $("#update-all-text").hide();
                $("#update-all-spinner").show();
            });
        });
    </script>';
}

add_action('wp_ajax_nb_update_connector', 'nb_update_connector');
function nb_update_connector()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $zip_url = 'https://github.com/New-Bytes/woocommerce-newbytes/archive/refs/heads/main.zip';
    $upload_dir = wp_upload_dir();
    $zip_file = $upload_dir['path'] . '/woocommerce-newbytes-main.zip';

    // Descargar el archivo .zip
    $response = wp_remote_get($zip_url, array('timeout' => 300));
    if (is_wp_error($response)) {
        wp_die('Error downloading the update.');
    }

    $zip_data = wp_remote_retrieve_body($response);
    if (empty($zip_data)) {
        wp_die('Empty response from the update server.');
    }

    // Guardar el archivo .zip en el directorio de uploads
    if (!file_put_contents($zip_file, $zip_data)) {
        wp_die('Error saving the update file.');
    }

    // Descomprimir el archivo .zip
    if (!class_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    WP_Filesystem();
    $unzip_result = unzip_file($zip_file, WP_PLUGIN_DIR);

    if (is_wp_error($unzip_result)) {
        wp_die('Error unzipping the update file.');
    }

    // Borrar el archivo .zip descargado
    unlink($zip_file);

    echo 'Conector NB actualizado correctamente.';
    wp_die();
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
