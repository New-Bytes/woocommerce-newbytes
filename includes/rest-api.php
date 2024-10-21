<?php


// Función de callback para el endpoint de sincronización
function nb_sync_catalog(WP_REST_Request $request)
{
    // Llamar a la función de sincronización del catálogo
    nb_callback();

    // Devolver una respuesta exitosa
    return new WP_REST_Response('Sincronización completada', 200);
}


// Registra el endpoint REST personalizado
add_action('rest_api_init', function () {
    register_rest_route('nb/v1', '/sync', array(
        'methods' => 'POST',
        'callback' => 'nb_sync_catalog',
        'permission_callback' => '__return_true',
    ));
});
