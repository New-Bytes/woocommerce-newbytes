<?php


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
