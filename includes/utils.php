<?php

/**
 * Sistema de logging mejorado para NewBytes
 * Previene warnings de PHP y proporciona mejor debugging
 */

// Suprimir warnings específicos en producción (opcional)
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    error_reporting(E_ERROR | E_PARSE);
}

/**
 * Función de logging centralizada con niveles
 * @param string $message Mensaje a registrar
 * @param string $level Nivel: 'info', 'warning', 'error', 'debug'
 * @param array $context Contexto adicional
 */
function nb_log($message, $level = 'info', $context = array()) {
    $log_file = plugin_dir_path(__FILE__) . 'debug-newbytes.txt';
    $timestamp = date('Y-m-d H:i:s');
    $level_upper = strtoupper($level);
    
    $log_message = "[{$timestamp}] [{$level_upper}] {$message}";
    
    if (!empty($context)) {
        $log_message .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    
    $log_message .= PHP_EOL;
    
    error_log($log_message, 3, $log_file);
    
    // También registrar en el log de WordPress si es un error crítico
    if ($level === 'error') {
        error_log('[NewBytes] ' . $message);
    }
}

/**
 * Verificar estado de autenticación
 * @return bool
 */
function nb_check_auth_status() {
    $user = get_option('nb_user', '');
    $password = get_option('nb_password', '');
    
    if (empty($user) || empty($password)) {
        return false;
    }
    
    // Intentar obtener token para verificar credenciales
    $token = nb_get_token();
    return !empty($token);
}

function nb_get_token()
{
    try {
        $user = get_option('nb_user', '');
        $password = get_option('nb_password', '');
        
        // Validación previa
        if (empty($user) || empty($password)) {
            nb_log('Intento de obtener token sin credenciales configuradas', 'warning');
            return null;
        }
        
        // Siempre solicitar un nuevo token
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'user' => $user,
                'password' => $password,
                'mode' => 'wp-extension',
                'domain' => home_url()
            )),
            'timeout' => '5',
            'blocking' => true,
        );

        nb_log('Solicitando token de autenticación', 'debug', array('user' => $user));
        
        $response = wp_remote_post(API_URL_NB . '/auth/login', $args);

        if (is_wp_error($response)) {
            $error_msg = 'Error en la solicitud de token: ' . $response->get_error_message();
            nb_log($error_msg, 'error');
            nb_show_error_message($error_msg);
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            nb_log('Error HTTP en autenticación', 'error', array('status_code' => $status_code, 'body' => $body));
            nb_show_error_message('Error de autenticación (HTTP ' . $status_code . ')');
            return null;
        }
        
        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Error al decodificar JSON de la solicitud de token: ' . json_last_error_msg();
            nb_log($error_msg, 'error', array('body' => substr($body, 0, 200)));
            nb_show_error_message($error_msg);
            return null;
        }

        if (isset($json['token'])) {
            nb_log('Token obtenido exitosamente', 'info');
            return $json['token'];
        }

        $error_msg = 'Token no encontrado en la respuesta';
        nb_log($error_msg, 'error', array('response' => $json));
        nb_show_error_message($error_msg);
        return null;
    } catch (Exception $e) {
        nb_log('Excepción en nb_get_token: ' . $e->getMessage(), 'error', array(
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ));
        echo '<div class="notice notice-error"><p>Error crítico: ' . esc_html($e->getMessage()) . '</p></div>';
        return null;
    }
}

function output_response($data)
{
    echo json_encode($data);
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
