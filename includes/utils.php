<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utilidades: logging y autenticación contra la API NewBytes.
 *
 * @see .spec/specs/SPEC-0012-logging-hardening.md
 */

// Suprimir warnings en producción (comportamiento heredado).
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    error_reporting(E_ERROR | E_PARSE);
}

/**
 * Logging centralizado del plugin.
 *
 * Escribe en wp-content/uploads/nb-logs/nb-debug.log (fuera del webroot,
 * protegido). Rota a nb-debug.log.1 al superar 5 MB.
 *
 * @param string $message Mensaje.
 * @param string $level   'info' | 'warning' | 'error' | 'debug'.
 * @param array  $context Contexto adicional (se serializa a JSON).
 */
function nb_log($message, $level = 'info', $context = array())
{
    $log_file = nb_log_dir() . 'nb-debug.log';

    if (is_file($log_file) && filesize($log_file) > 5 * 1024 * 1024) {
        @rename($log_file, $log_file . '.1');
    }

    $line = '[' . gmdate('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . wp_json_encode($context);
    }
    $line .= PHP_EOL;

    @error_log($line, 3, $log_file);

    if ($level === 'error') {
        error_log('[NewBytes] ' . $message);
    }
}

/**
 * Guarda el token de autenticación y su fecha de expiración.
 *
 * @param string $token       Token.
 * @param int    $expiry_time Segundos hasta expiración (24 h por defecto).
 * @return bool
 */
function nb_save_token($token, $expiry_time = 86400)
{
    if (empty($token)) {
        return false;
    }

    $token_saved  = update_option('nb_token', $token);
    $expiry_saved = update_option('nb_token_expiry', time() + $expiry_time);

    return $token_saved && $expiry_saved;
}

/**
 * ¿Hay credenciales válidas / token vigente?
 *
 * @return bool
 */
function nb_check_auth_status()
{
    $user     = get_option('nb_user', '');
    $password = get_option('nb_password', '');

    if (empty($user) || empty($password)) {
        return false;
    }

    $token        = get_option('nb_token');
    $token_expiry = get_option('nb_token_expiry');

    if (!empty($token) && !empty($token_expiry) && time() < $token_expiry) {
        return true;
    }

    $token = nb_get_token();
    if (!empty($token)) {
        nb_save_token($token);
        return true;
    }

    return false;
}

/**
 * Pide un token nuevo a la API. Nunca imprime HTML (se usa también en cron/REST).
 *
 * @return string|null
 */
function nb_get_token()
{
    try {
        $user     = get_option('nb_user', '');
        $password = get_option('nb_password', '');

        if (empty($user) || empty($password)) {
            nb_log('Intento de obtener token sin credenciales configuradas', 'warning');
            return null;
        }

        $args = array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array(
                'user'     => $user,
                'password' => $password,
                'mode'     => 'wp-extension',
                'domain'   => home_url(),
            )),
            'timeout'  => 10,
            'blocking' => true,
        );

        $response = wp_remote_post(API_URL_NB . '/auth/login', $args);

        if (is_wp_error($response)) {
            nb_log('Error en la solicitud de token: ' . $response->get_error_message(), 'error');
            return null;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            nb_log('Error HTTP en autenticación', 'error', array('status_code' => $status_code));
            return null;
        }

        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            nb_log('JSON inválido en la respuesta de token: ' . json_last_error_msg(), 'error');
            return null;
        }

        if (isset($json['token'])) {
            nb_log('Token obtenido correctamente', 'info');
            return $json['token'];
        }

        nb_log('Token no encontrado en la respuesta de la API', 'error');
        return null;
    } catch (Exception $e) {
        nb_log('Excepción en nb_get_token: ' . $e->getMessage(), 'error', array(
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ));
        return null;
    }
}
