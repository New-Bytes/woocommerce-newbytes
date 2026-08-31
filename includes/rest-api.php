<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Endpoint REST para disparar una sincronización del catálogo.
 *
 * @see .spec/specs/SPEC-0004-rest-endpoint-auth.md
 */
function nb_sync_catalog(WP_REST_Request $request)
{
    if (nb_sync_is_locked()) {
        return new WP_REST_Response(array('message' => 'Sincronización ya en curso'), 409);
    }

    $result = nb_callback();

    $status = !empty($result['success']) ? 200 : (!empty($result['locked']) ? 409 : 500);

    return new WP_REST_Response($result, $status);
}

add_action('rest_api_init', function () {
    register_rest_route('nb/v1', '/sync', array(
        'methods'             => 'POST',
        'callback'            => 'nb_sync_catalog',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ));
});
