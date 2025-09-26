<?php

function nb_options_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Add Tailwind CSS
    echo '<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>';

    // Check authentication status
    $is_authenticated = nb_check_auth_status();

    $plugin_url = plugin_dir_url(__FILE__);
    $icon_url = $plugin_url . '../assets/icon-128x128.png';

    $latest_commit = get_latest_version_nb();
    $show_new_version_button = ($latest_commit > VERSION_NB);

    echo '<div class="wrap bg-gray-100 p-6 flex justify-center items-center h-full">';
    echo '<div class="bg-white p-8 rounded-lg shadow-md text-center max-w-2xl w-full">';

    echo '<section class="w-full text-left">';
    if ($show_new_version_button) {
        echo '<form method="post" class="mt-5">';
        echo '<button type="button" id="update-connector-btn" class="inline-flex items-center px-5 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-amber-400 hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-400 transition-colors">Actualizar Conector NB</button>';
        echo '</form>';
    } else {
        echo '<form method="post" class="mt-5">';
        echo '<button type="button" class="inline-flex items-center px-5 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-300 cursor-not-allowed focus:outline-none" disabled>Actualizado: ' . VERSION_NB . '</button>';
        echo '</form>';
    }
    echo '</section>';


    // Contenedor principal con ancho completo y padding lateral
    echo '<div class="w-full mb-5 px-4">';

    // Logo centrado
    echo '<img src="' . esc_url($icon_url) . '" alt="Logo" class="w-32 h-32 mx-auto mb-3">';
    // Centered title
    echo '<h1 class="text-2xl font-bold text-gray-800 text-center">Conector New Bytes</h1>';

    // Barra superior con el indicador de autenticación alineado a la derecha
    echo '<div class="flex justify-center mb-4">';
    // Authentication status indicator - right aligned
    if ($is_authenticated) {
        echo '<div class="flex items-center px-3 py-1 bg-green-100 text-green-800 rounded-md border border-green-300">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">';
        echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />';
        echo '</svg>';
        echo '<span class="font-medium">Autenticado</span>';
        echo '</div>';
    } else {
        echo '<div class="flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-md border border-gray-300">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">';
        echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />';
        echo '</svg>';
        echo '<span class="font-medium">No autenticado</span>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    // Sección de Información de Cron
    echo '<div class="mt-8 w-full">';

    // Contenedor de información de cron
    echo '<div class="mt-8 mb-8 p-5 bg-gray-50 border border-gray-100 rounded-lg shadow-sm">';

    // Encabezado de la sección con botón para mostrar/ocultar detalles
    echo '<div class="flex items-center justify-between mb-4 border-b border-gray-200 pb-3">';
    echo '<h2 class="text-base font-medium text-gray-600">Estado de Sincronización</h2>';
    echo '<button id="toggle-cron-info" class="flex items-center px-3 py-1 text-sm text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-300 transition-colors duration-200">';
    echo '<span id="button-text">Ocultar detalles</span>';
    echo '<svg id="button-icon-up" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">';
    echo '<path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />';
    echo '</svg>';
    echo '<svg id="button-icon-down" xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">';
    echo '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />';
    echo '</svg>';
    echo '</button>';
    echo '</div>';

    // Contenido de la información de Cron
    echo '<div id="cron-info-details" class="px-4 py-3 bg-white">';

    // Recopilar información de diagnóstico
    global $wpdb;

    // Información del servidor y base de datos
    $server_time = time();
    $server_time_formatted = date('Y-m-d H:i:s', $server_time);
    $server_timezone = date_default_timezone_get();

    // Obtener zona horaria de WordPress (con compatibilidad)
    $wp_timezone = function_exists('wp_timezone') ? wp_timezone() : null;
    $wp_timezone_string = function_exists('wp_timezone_string') ? wp_timezone_string() : get_option('timezone_string', 'UTC');
    $wp_time_formatted = function_exists('wp_date') ? wp_date('Y-m-d H:i:s', $server_time) : date('Y-m-d H:i:s', $server_time);

    // Obtener hora de la base de datos
    $db_time = ($wpdb && is_object($wpdb)) ? $wpdb->get_var("SELECT NOW()") : 'N/A';

    // Obtener información del cron
    $cron_array = function_exists('_get_cron_array') ? _get_cron_array() : array();
    $interval = intval(get_option('nb_sync_interval', 3600));
    $interval_minutes = $interval / 60;

    // Buscar el próximo evento de sincronización
    $next_sync = wp_next_scheduled('nb_cron_sync_event');
    if (!$next_sync) {
        $next_sync = time() + $interval; // Fallback si no hay evento programado
    }
    $now = time();
    $time_diff = $next_sync - $now;

    // Si el tiempo programado ya pasó, ajustamos el tiempo para mostrar la barra de progreso correctamente
    if ($time_diff <= 0) {
        // Calculamos cuándo debería ser la próxima sincronización
        $cycles_passed = ceil(abs($time_diff) / $interval);
        $next_expected_sync = $next_sync + ($interval * $cycles_passed);
        $time_diff = $next_expected_sync - $now;
    }

    // Calcular horas, minutos y segundos
    $hours = floor($time_diff / 3600);
    $minutes = floor(($time_diff % 3600) / 60);
    $seconds = $time_diff % 60;

    // Formatear el tiempo de la próxima sincronización
    $next_sync_formatted = date('Y-m-d H:i:s', $next_sync);
    $next_sync_local = wp_date('Y-m-d H:i:s', $next_sync);

    // Calcular porcentaje de tiempo transcurrido desde la última sincronización
    $percent_elapsed = 100 - (($time_diff / $interval) * 100);
    $percent_elapsed = max(0, min(100, $percent_elapsed)); // Asegurar que esté entre 0 y 100

    // Tabla de información de tiempo
    echo '<div class="mb-4">';
    echo '<h3 class="text-sm font-medium text-gray-600 mb-3 text-left">Información de tiempo</h3>';
    echo '<div class="overflow-x-auto">';
    echo '<div class="grid grid-cols-1 gap-2">';

    echo '<div class="flex justify-between items-center py-1.5 border-b border-gray-100">';
    echo '<span class="text-sm text-gray-600">Hora del servidor (UTC):</span>';
    echo '<span class="text-sm font-medium text-gray-800">' . esc_html($server_time_formatted) . '</span>';
    echo '</div>';

    echo '<div class="flex justify-between items-center py-1.5 border-b border-gray-100">';
    echo '<span class="text-sm text-gray-600">Hora local (WordPress):</span>';
    echo '<span class="text-sm font-medium text-gray-800">' . esc_html($wp_time_formatted) . ' <span class="text-gray-500">(' . esc_html($wp_timezone_string) . ')</span></span>';
    echo '</div>';

    echo '<div class="flex justify-between items-center py-1.5 border-b border-gray-100">';
    echo '<span class="text-sm text-gray-600">Hora de la base de datos:</span>';
    echo '<span class="text-sm font-medium text-gray-800">' . esc_html($db_time) . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Tabla de configuración de sincronización
    echo '<div class="mb-4">';
    echo '<h3 class="text-sm font-medium text-gray-600 mb-3 text-left">Configuración de sincronización</h3>';
    echo '<div class="overflow-x-auto">';
    echo '<div class="grid grid-cols-1 gap-2">';

    echo '<div class="flex justify-between items-center py-1.5 border-b border-gray-100">';
    echo '<span class="text-sm text-gray-600">Intervalo de sincronización:</span>';
    echo '<span class="text-sm font-medium text-gray-800">' . esc_html($interval_minutes) . ' minutos <span class="text-gray-500">(' . esc_html($interval) . ' segundos)</span></span>';
    echo '</div>';

    echo '<div class="flex justify-between items-center py-1.5 border-b border-gray-100">';
    echo '<span class="text-sm text-gray-600">Próxima sincronización (UTC):</span>';
    echo '<span class="text-sm font-medium text-gray-800">' . esc_html($next_sync_formatted) . '</span>';
    echo '</div>';

    echo '<div class="flex justify-between items-center py-1.5 border-b border-gray-100">';
    echo '<span class="text-sm text-gray-600">Próxima sincronización (local):</span>';
    echo '<span class="text-sm font-medium text-gray-800">' . esc_html($next_sync_local) . ' <span class="text-gray-500">(timestamp: ' . esc_html($next_sync) . ')</span></span>';
    echo '</div>';

    echo '<div class="flex justify-between items-center py-1.5 border-b border-gray-100">';
    echo '<span class="text-sm text-gray-600">Tiempo restante:</span>';
    echo '<span class="text-sm font-medium text-gray-800">';
    if ($hours > 0) {
        echo esc_html($hours) . ' horas, ';
    }
    echo esc_html($minutes) . ' minutos, ' . esc_html($seconds) . ' segundos</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Barra de progreso
    echo '<div class="mb-4 mt-3">';
    echo '<div class="flex items-center justify-between text-xs text-gray-500 mb-1">';
    echo '<span>0%</span>';
    echo '<span>' . esc_attr($percent_elapsed) . '%</span>';
    echo '<span>100%</span>';
    echo '</div>';
    echo '<div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">';
    echo '<div class="bg-blue-500 h-2.5 rounded-full transition-all duration-300" style="width: ' . esc_attr($percent_elapsed) . '%"></div>';
    echo '</div>';
    echo '</div>';

    // Estado del evento de sincronización
    echo '<div class="mb-4">';
    echo '<h3 class="text-sm font-medium text-gray-600 mb-3 text-left">Estado del evento de sincronización</h3>';
    echo '<div class="overflow-x-auto">';
    echo '<div class="grid grid-cols-1 gap-2">';

    echo '<div class="flex justify-between items-center py-1.5 border-b border-gray-100">';
    echo '<span class="text-sm text-gray-600">nb_cron_sync_event:</span>';
    echo '<span class="text-sm font-medium text-gray-800">' . esc_html($next_sync_formatted) . ' <span class="text-gray-500">(custom_user_interval)</span></span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '</div>'; // Fin de cron-info-details
    echo '</div>'; // Fin del borde
    echo '</div>'; // Fin de la sección de Cron

    // JavaScript para controlar el botón de mostrar/ocultar información de cron y otros comportamientos
    echo '<script>
        jQuery(document).ready(function($) {
            // Función para mostrar/ocultar la información de cron
            $("#toggle-cron-info").on("click", function() {
                $("#cron-info-details").toggleClass("hidden");
                if ($("#cron-info-details").hasClass("hidden")) {
                    $("#button-text").text("Mostrar detalles");
                    $("#button-icon-down").removeClass("hidden");
                    $("#button-icon-up").addClass("hidden");
                } else {
                    $("#button-text").text("Ocultar detalles");
                    $("#button-icon-down").addClass("hidden");
                    $("#button-icon-up").removeClass("hidden");
                }
            });
            
            // Manejar el botón de actualizar todo
            $("#update-all-btn").on("click", function() {
                $("#update-all-text").addClass("hidden");
                $("#update-all-spinner").removeClass("hidden");
            });
            
            // Manejar el botón de actualizar conector
            $("#update-connector-btn").on("click", function() {
                $(this).addClass("opacity-75 cursor-not-allowed").prop("disabled", true);
                $(this).html("<svg class=\"animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\"><circle class=\"opacity-25\" cx=\"12\" cy=\"12\" r=\"10\" stroke=\"currentColor\" stroke-width=\"4\"></circle><path class=\"opacity-75\" fill=\"currentColor\" d=\"M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z\"></path></svg>Actualizando...");
                
                // Llamada AJAX para actualizar el conector
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        "action": "nb_update_connector"
                    },
                    success: function(response) {
                        location.reload();
                    },
                    error: function(xhr, status, error) {
                        alert("Error al actualizar el conector: " + error);
                        $("#update-connector-btn").removeClass("opacity-75 cursor-not-allowed").prop("disabled", false);
                        $("#update-connector-btn").text("Actualizar Conector NB");
                    }
                });
            });
        });
    </script>';
    echo '<p class="text-center text-gray-600 text-sm mt-4 mb-2">Gracias por utilizar nuestro conector de productos exclusivo de NewBytes.</p>';
    echo '<p class="text-center text-gray-600 text-sm mb-4">Si no tienes credenciales, puedes visitar la <a href="https://developers.nb.com.ar/" target="_blank" class="text-blue-600 hover:text-blue-800 transition-colors">documentación oficial de NewBytes</a>.</p>';
    if (!is_plugin_active('featured-image-from-url/featured-image-from-url.php')) {
        echo '<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">';
        echo '<div class="flex">';
        echo '<div class="flex-shrink-0">';
        echo '<svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
        echo '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />';
        echo '</svg>';
        echo '</div>';
        echo '<div class="ml-3">';
        echo '<p class="text-sm text-yellow-700">Para el funcionamiento de las imágenes se requiere la instalación del plugin: ';
        echo '<a href="' . wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=featured-image-from-url'), 'install-plugin_featured-image-from-url') . '" class="font-medium underline text-yellow-700 hover:text-yellow-600">FIFU (Featured Image From URL)</a>';
        echo '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '<form method="post" action="options.php" class="w-full max-w-md mx-auto mt-6">';
    settings_fields('nb_options');
    do_settings_sections('nb_options');
    echo '<div class="space-y-6">';
    // Add User
    echo '<div class="mb-6">';
    echo '<label for="nb_user" class="block text-sm font-medium text-gray-600 text-center mb-2">Usuario <span class="text-red-500">*</span></label>';
    echo '<input type="text" name="nb_user" id="nb_user" value="' . esc_attr(get_option('nb_user')) . '" required class="mt-1 block w-full px-4 py-2.5 bg-white border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent transition-all duration-200 sm:text-sm" />';
    echo '</div>';
    // Add Password
    echo '<div class="mb-6">';
    echo '<label for="nb_password" class="block text-sm font-medium text-gray-600 text-center mb-2">Contraseña <span class="text-red-500">*</span></label>';
    echo '<input type="password" name="nb_password" id="nb_password" value="' . esc_attr(get_option('nb_password')) . '" required class="mt-1 block w-full px-4 py-2.5 bg-white border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent transition-all duration-200 sm:text-sm" />';
    echo '</div>';
    // Add Prefix SKU
    echo '<div class="mb-6">';
    echo '<label for="nb_prefix" class="block text-sm font-medium text-gray-600 text-center mb-2">Prefijo SKU <span class="text-red-500">*</span></label>';
    echo '<input type="text" name="nb_prefix" id="nb_prefix" value="' . esc_attr(get_option('nb_prefix')) . '" required placeholder="Ejemplo: NB_" class="mt-1 block w-full px-4 py-2.5 bg-white border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent transition-all duration-200 sm:text-sm" />';
    echo '<p class="text-xs text-gray-500 mt-2 text-center">Se colocará este prefijo al comienzo de cada SKU para que puedas filtrar tus productos.</p>';
    echo '</div>';
    // Add description
    echo '<div class="mb-6">';
    echo '<label for="nb_description" class="block text-sm font-medium text-gray-600 text-center mb-2">Descripción corta</label>';
    echo '<textarea name="nb_description" id="nb_description" class="mt-1 block w-full px-4 py-2.5 bg-white border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent transition-all duration-200 sm:text-sm" rows="3">' . esc_attr(get_option('nb_description')) . '</textarea>';
    echo '<p class="text-xs text-gray-500 mt-2 text-center">Se agregará esta descripción en todos los productos.</p>';
    echo '</div>';

    echo '<div class="mb-6">';
    echo '<label class="block text-sm font-medium text-gray-600 text-center mb-2">Última actualización</label>';
    echo '<div id="last_update" class="text-sm font-medium text-gray-800 bg-gray-50 px-4 py-2.5 border border-gray-100 rounded-lg shadow-sm text-center">' . esc_attr(get_option('nb_last_update') != '' ? date('d/m/Y H:i', strtotime(get_option('nb_last_update') . '-3 hours')) : '--') . '</div>';
    echo '</div>';
    echo '<div class="mb-6">';
    echo '<label for="nb_sync_interval" class="block text-sm font-medium text-gray-600 text-center mb-2">Intervalo de sincronización automática</label>';
    echo '<div class="relative">';
    echo '<select name="nb_sync_interval" id="nb_sync_interval" class="mt-1 block w-full px-4 py-2.5 bg-white border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent transition-all duration-200 sm:text-sm appearance-none">';
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
    echo '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />';
    echo '</svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-500 mt-2 text-center">Selecciona el intervalo en el que deseas que se sincronice automáticamente.</p>';
    echo '</div>';
    // Add Sync No IVA
    echo '<div class="mb-6 text-center">';
    echo '<div class="flex justify-center">';
    echo '<div class="flex items-center bg-gray-50 px-4 py-2.5 rounded-lg border border-gray-100 shadow-sm">';
    echo '<input type="checkbox" name="nb_sync_no_iva" id="nb_sync_no_iva" value="1" ' . checked(1, get_option('nb_sync_no_iva'), false) . ' class="focus:ring-blue-400 h-5 w-5 text-blue-500 border-gray-200 rounded transition-colors" />';
    echo '<label for="nb_sync_no_iva" class="ml-2 block text-sm font-medium text-gray-700">Sincronizar sin IVA</label>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-500 mt-2 text-center">Selecciona esta opción si deseas sincronizar los productos sin IVA.</p>';
    echo '</div>';

    // Add Sync USD
    echo '<div class="mb-6 text-center">';
    echo '<div class="flex justify-center">';
    echo '<div class="flex items-center bg-gray-50 px-4 py-2.5 rounded-lg border border-gray-100 shadow-sm">';
    echo '<input type="checkbox" name="nb_sync_usd" id="nb_sync_usd" value="1" ' . checked(1, get_option('nb_sync_usd'), false) . ' class="focus:ring-blue-400 h-5 w-5 text-blue-500 border-gray-200 rounded transition-colors" />';
    echo '<label for="nb_sync_usd" class="ml-2 block text-sm font-medium text-gray-700">Sincronizar en USD</label>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-500 mt-2 text-center">Selecciona esta opción si deseas sincronizar los productos en USD.</p>';
    echo '</div>';
    echo '<div class="mt-8">';
    echo '<button type="submit" class="w-full inline-flex justify-center py-3 px-4 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all duration-200">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">';
    echo '</svg>';
    echo 'Guardar cambios';
    echo '</button>';
    echo '</div>';
    echo '</form>';

    // Sección de Herramientas y Acciones
    echo '<div class="mt-8 border-t border-gray-200 pt-6">';
    echo '<div class="text-center mb-6">';
    echo '<h3 class="text-lg font-medium text-gray-900 mb-2">Herramientas y Acciones</h3>';
    echo '<p class="text-sm text-gray-600">Gestiona tus productos y consulta el historial de sincronizaciones</p>';
    echo '</div>';

    // Primera fila: Botón de logs (destacado)
    echo '<div class="mb-6 text-center">';
    echo '<a href="' . admin_url('tools.php?page=nb-logs') . '" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white hover:text-white text-sm font-medium rounded-lg shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 transition-all duration-200 no-underline">';
    echo '<svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>';
    echo '</svg>';
    echo '<span class="text-white">Ver Registro de Sincronizaciones</span>';
    echo '</a>';
    echo '<p class="text-xs text-gray-500 mt-2">Consulta el historial detallado de todas las sincronizaciones realizadas</p>';
    echo '</div>';

    // Segunda fila: Botón de actualizar todo (prominente)
    echo '<div class="mb-6 text-center">';
    echo '<form method="post" class="inline-block">';
    echo '<input type="hidden" name="update_all"/>';
    echo '<button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200" id="update-all-btn">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-white" viewBox="0 0 20 20" fill="currentColor">';
    echo '<path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />';
    echo '</svg>';
    echo '<span id="update-all-text">Resincronizar Todos los Productos</span>';
    echo '<span id="update-all-spinner" class="hidden ml-2">';
    echo '<div class="flex items-center">';
    echo '<svg class="animate-spin h-4 w-4 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">';
    echo '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>';
    echo '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>';
    echo '</svg>';
    echo '<span>Sincronizando artículos...</span>';
    echo '</div>';
    echo '</span>';
    echo '</button>';
    echo '</form>';
    echo '<p class="text-xs text-gray-500 mt-2">Si cambiaste los markups o realizaste algún ajuste, resincroniza todos los productos</p>';
    echo '</div>';

    // Tercera fila: Botones secundarios (agrupados)
    echo '<div class="flex flex-wrap justify-center gap-3">';
    btn_update_description_products();
    btn_delete_products();
    echo '</div>';
    echo '</div>'; // Cierre del div mt-8 border-t

    if (isset($_POST['update_all'])) {
        $response = nb_callback();
        if (isset($response['success']) && $response['success']) {
            echo '<div class="bg-white border-l-4 border-green-500 rounded-lg shadow-md p-5 my-6">
                    <div class="flex items-center mb-4">
                        <svg class="h-6 w-6 text-green-500 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-800">Sincronización completada</h3>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 mb-2">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                                <div class="flex justify-center mb-2">
                                    <svg class="h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                </div>
                                <p class="text-lg font-semibold text-gray-800">' . $response['stats']['created'] . '</p>
                                <p class="text-xs text-gray-500">Productos agregados</p>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                                <div class="flex justify-center mb-2">
                                    <svg class="h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <p class="text-lg font-semibold text-gray-800">' . $response['stats']['updated'] . '</p>
                                <p class="text-xs text-gray-500">Productos actualizados</p>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                                <div class="flex justify-center mb-2">
                                    <svg class="h-6 w-6 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <p class="text-lg font-semibold text-gray-800">' . (isset($response['stats']['deleted']) ? $response['stats']['deleted'] : '0') . '</p>
                                <p class="text-xs text-gray-500">Errores</p>
                            </div>
                        </div>
                    </div>
                </div>';
            echo '<p class="text-xs text-gray-500 mt-2 text-right">Última actualización: ';
            nb_show_last_update();
            echo '</p>';
        } else {
            echo '<div class="bg-white border-l-4 border-red-500 rounded-lg shadow-md p-5 my-6">
                    <div class="flex items-center mb-4">
                        <svg class="h-6 w-6 text-red-500 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-800">Error en la sincronización</h3>
                    </div>
                    <p class="text-gray-600">' . (isset($response['error']) ? esc_html($response['error']) : 'Error desconocido durante la sincronización.') . '</p>
                </div>';
        }
    }

    // Agregar los modales al DOM
    modal_confirm_delete_products();
    modal_confirm_update_();
    modal_success_confirm_update();
    modal_fail_confirm_update();

    // Agregar el manejador de JavaScript
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

/**
 * Guarda el token de autenticación y establece su fecha de expiración
 * 
 * @param string $token El token de autenticación
 * @param int $expiry_time Tiempo de expiración en segundos (por defecto 24 horas)
 * @return bool True si se guardó correctamente, False si no
 */
function nb_save_token($token, $expiry_time = 86400)
{
    if (empty($token)) {
        return false;
    }

    // Guardar el token
    $token_saved = update_option('nb_token', $token);

    // Calcular y guardar la fecha de expiración (tiempo actual + tiempo de expiración)
    $expiry = time() + $expiry_time;
    $expiry_saved = update_option('nb_token_expiry', $expiry);

    return $token_saved && $expiry_saved;
}

/**
 * Verifica el estado de autenticación con la API de NewBytes
 * 
 * @return bool True si está autenticado, False si no
 */
function nb_check_auth_status()
{
    // Verificar si existen las credenciales
    $user = get_option('nb_user');
    $password = get_option('nb_password');

    if (empty($user) || empty($password)) {
        return false;
    }

    // Verificar si hay un token almacenado y válido
    $token = get_option('nb_token');
    $token_expiry = get_option('nb_token_expiry');

    if (!empty($token) && !empty($token_expiry)) {
        // Si el token no ha expirado, consideramos que está autenticado
        if (time() < $token_expiry) {
            return true;
        }
    }

    // Si llegamos aquí, intentamos obtener un nuevo token
    $token = nb_get_token();

    // Si obtuvimos un token válido, lo guardamos con su fecha de expiración
    if (!empty($token)) {
        nb_save_token($token);
        return true;
    }

    return false;
}
