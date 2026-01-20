<?php

/**
 * Página de configuración del Conector NewBytes
 * Diseño Vercel-inspired - Minimalista y profesional
 */

function nb_options_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $is_authenticated = nb_check_auth_status();
    $plugin_url = plugin_dir_url(__FILE__);
    $icon_url = $plugin_url . '../assets/icon-128x128.png';
    $latest_commit = get_latest_version_nb();
    $show_new_version_button = ($latest_commit > VERSION_NB);

    // Información de sincronización
    $interval = intval(get_option('nb_sync_interval', 3600));
    $next_sync = wp_next_scheduled('nb_cron_sync_event');
    if (!$next_sync) {
        $next_sync = time() + $interval;
    }
    $time_diff = max(0, $next_sync - time());
    $hours = floor($time_diff / 3600);
    $minutes = floor(($time_diff % 3600) / 60);
    $percent_elapsed = 100 - (($time_diff / $interval) * 100);
    $percent_elapsed = max(0, min(100, $percent_elapsed));

    echo '<div class="nb-wrap">';
    echo '<div class="nb-container">';

    // ============================================
    // Header
    // ============================================
    echo '<header class="nb-page-header">';
    echo '<div class="nb-page-header-left">';
    echo '<img src="' . esc_url($icon_url) . '" alt="NewBytes" class="nb-logo">';
    echo '<div>';
    echo '<h1 class="nb-page-title">Conector NewBytes</h1>';
    echo '<p class="nb-page-subtitle">Sincronización de productos con WooCommerce</p>';
    echo '</div>';
    echo '</div>';
    
    // Status badge y versión
    echo '<div class="nb-flex nb-items-center nb-gap-4">';
    if ($is_authenticated) {
        echo '<span class="nb-badge nb-badge-success"><span class="nb-badge-dot"></span>Conectado</span>';
    } else {
        echo '<span class="nb-badge nb-badge-error"><span class="nb-badge-dot"></span>Desconectado</span>';
    }
    
    if ($show_new_version_button) {
        echo '<button type="button" id="update-connector-btn" class="nb-btn nb-btn-sm nb-btn-secondary nb-version-badge-new">';
        echo '<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>';
        echo 'Actualizar a ' . esc_html($latest_commit);
        echo '</button>';
    } else {
        echo '<span class="nb-version-badge">v' . VERSION_NB . '</span>';
    }
    echo '</div>';
    echo '</header>';

    // ============================================
    // Alerta FIFU
    // ============================================
    if (!is_plugin_active('featured-image-from-url/featured-image-from-url.php') && !is_plugin_active('fifu-premium/fifu-premium.php')) {
        echo '<div class="nb-alert nb-alert-warning">';
        echo '<svg class="nb-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
        echo '<div class="nb-alert-content">';
        echo '<p class="nb-alert-title">Plugin requerido</p>';
        echo '<p class="nb-alert-text">Para las imágenes se requiere <a href="' . wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=featured-image-from-url'), 'install-plugin_featured-image-from-url') . '" style="text-decoration: underline; font-weight: 500;">FIFU (Featured Image From URL)</a></p>';
        echo '</div>';
        echo '</div>';
    }

    // ============================================
    // Estado de Sincronización (Collapsible)
    // ============================================
    echo '<div class="nb-card">';
    echo '<div class="nb-collapsible">';
    echo '<div class="nb-collapsible-header" onclick="this.parentElement.classList.toggle(\'is-open\'); this.nextElementSibling.classList.toggle(\'hidden\');">';
    echo '<span class="nb-collapsible-title">Estado de Sincronización</span>';
    echo '<span class="nb-collapsible-toggle">';
    echo '<span id="toggle-text">Mostrar detalles</span>';
    echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
    echo '</span>';
    echo '</div>';
    
    echo '<div class="nb-collapsible-content hidden">';
    
    // Próxima sincronización
    echo '<div class="nb-progress-wrapper">';
    echo '<div class="nb-progress-header">';
    echo '<span class="nb-progress-label">Próxima sincronización</span>';
    echo '<span class="nb-progress-value">';
    if ($hours > 0) {
        echo esc_html($hours) . 'h ' . esc_html($minutes) . 'm';
    } else {
        echo esc_html($minutes) . ' min';
    }
    echo '</span>';
    echo '</div>';
    echo '<div class="nb-progress-track">';
    echo '<div class="nb-progress-fill nb-progress-fill-gradient" style="width: ' . esc_attr($percent_elapsed) . '%"></div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="nb-divider"></div>';
    
    // Datos de tiempo
    global $wpdb;
    $wp_time_formatted = wp_date('Y-m-d H:i:s');
    $wp_timezone_string = wp_timezone_string();
    $db_time = $wpdb->get_var("SELECT NOW()");
    $next_sync_local = wp_date('d/m/Y H:i', $next_sync);
    
    echo '<div class="nb-data-row">';
    echo '<span class="nb-data-label">Hora WordPress</span>';
    echo '<span class="nb-data-value">' . esc_html($wp_time_formatted) . ' <span class="nb-data-value-muted">(' . esc_html($wp_timezone_string) . ')</span></span>';
    echo '</div>';
    
    echo '<div class="nb-data-row">';
    echo '<span class="nb-data-label">Hora Base de Datos</span>';
    echo '<span class="nb-data-value">' . esc_html($db_time) . '</span>';
    echo '</div>';
    
    echo '<div class="nb-data-row">';
    echo '<span class="nb-data-label">Intervalo configurado</span>';
    echo '<span class="nb-data-value">' . esc_html($interval / 60) . ' minutos</span>';
    echo '</div>';
    
    echo '<div class="nb-data-row">';
    echo '<span class="nb-data-label">Próxima ejecución</span>';
    echo '<span class="nb-data-value">' . esc_html($next_sync_local) . '</span>';
    echo '</div>';
    
    echo '</div>'; // collapsible-content
    echo '</div>'; // collapsible
    echo '</div>'; // card

    // ============================================
    // Formulario de Configuración
    // ============================================
    echo '<div class="nb-card">';
    echo '<div class="nb-card-header">';
    echo '<h2 class="nb-card-title">Configuración</h2>';
    echo '</div>';
    echo '<div class="nb-card-body">';
    
    echo '<form method="post" action="options.php" id="nb-settings-form">';
    settings_fields('nb_options');
    do_settings_sections('nb_options');
    
    // Usuario
    echo '<div class="nb-form-group">';
    echo '<label for="nb_user" class="nb-form-label nb-form-label-required">Usuario</label>';
    echo '<input type="text" name="nb_user" id="nb_user" value="' . esc_attr(get_option('nb_user')) . '" required class="nb-input" placeholder="Tu usuario de NewBytes" />';
    echo '</div>';
    
    // Contraseña
    echo '<div class="nb-form-group">';
    echo '<label for="nb_password" class="nb-form-label nb-form-label-required">Contraseña</label>';
    echo '<input type="password" name="nb_password" id="nb_password" value="' . esc_attr(get_option('nb_password')) . '" required class="nb-input" placeholder="••••••••" />';
    echo '</div>';
    
    // Prefijo SKU
    echo '<div class="nb-form-group">';
    echo '<label for="nb_prefix" class="nb-form-label nb-form-label-required">Prefijo SKU</label>';
    echo '<input type="text" name="nb_prefix" id="nb_prefix" value="' . esc_attr(get_option('nb_prefix')) . '" required class="nb-input" placeholder="NB_" />';
    echo '<p class="nb-form-hint">Se agregará al inicio de cada SKU para identificar productos NewBytes</p>';
    echo '</div>';
    
    // Descripción
    echo '<div class="nb-form-group">';
    echo '<label for="nb_description" class="nb-form-label">Descripción adicional</label>';
    echo '<textarea name="nb_description" id="nb_description" class="nb-input nb-textarea" placeholder="Texto que se agregará a todos los productos...">' . esc_textarea(get_option('nb_description')) . '</textarea>';
    echo '</div>';
    
    // Última actualización
    $last_update = get_option('nb_last_update');
    $last_update_formatted = $last_update ? date('d/m/Y H:i', strtotime($last_update . '-3 hours')) : 'Nunca';
    echo '<div class="nb-form-group">';
    echo '<label class="nb-form-label">Última sincronización</label>';
    echo '<div class="nb-input" style="background: var(--nb-bg-tertiary); cursor: default;" id="last_update">' . esc_html($last_update_formatted) . '</div>';
    echo '</div>';
    
    // Intervalo
    echo '<div class="nb-form-group">';
    echo '<label for="nb_sync_interval" class="nb-form-label">Intervalo de sincronización</label>';
    echo '<select name="nb_sync_interval" id="nb_sync_interval" class="nb-input nb-select">';
    $intervals = array(
        '3600'  => 'Cada 1 hora',
        '7200'  => 'Cada 2 horas',
        '10800' => 'Cada 3 horas',
        '14400' => 'Cada 4 horas',
        '21600' => 'Cada 6 horas',
        '28800' => 'Cada 8 horas',
        '43200' => 'Cada 12 horas'
    );
    $current_interval = get_option('nb_sync_interval', 3600);
    foreach ($intervals as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($current_interval, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    // Checkboxes
    echo '<div class="nb-form-group">';
    echo '<label class="nb-checkbox-group">';
    echo '<input type="checkbox" name="nb_sync_no_iva" id="nb_sync_no_iva" value="1" ' . checked(1, get_option('nb_sync_no_iva'), false) . ' class="nb-checkbox" />';
    echo '<span class="nb-checkbox-label">Sincronizar precios sin IVA</span>';
    echo '</label>';
    echo '</div>';
    
    echo '<div class="nb-form-group">';
    echo '<label class="nb-checkbox-group">';
    echo '<input type="checkbox" name="nb_sync_usd" id="nb_sync_usd" value="1" ' . checked(1, get_option('nb_sync_usd'), false) . ' class="nb-checkbox" />';
    echo '<span class="nb-checkbox-label">Sincronizar precios en USD</span>';
    echo '</label>';
    echo '</div>';
    
    echo '</div>'; // card-body
    
    echo '<div class="nb-card-footer">';
    echo '<button type="submit" class="nb-btn nb-btn-primary nb-w-full">';
    echo '<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo 'Guardar cambios';
    echo '</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>'; // card

    // ============================================
    // Acciones
    // ============================================
    echo '<div class="nb-actions-section">';
    echo '<h3 class="nb-actions-title">Herramientas</h3>';
    echo '<p class="nb-actions-subtitle">Gestiona la sincronización y los productos</p>';
    
    echo '<div class="nb-actions-grid">';
    
    // Sincronizar productos
    echo '<div class="nb-action-card">';
    echo '<div class="nb-action-info">';
    echo '<div class="nb-action-icon">';
    echo '<svg class="nb-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';
    echo '</div>';
    echo '<div class="nb-action-text">';
    echo '<h4>Sincronizar productos</h4>';
    echo '<p>Actualiza todos los productos desde la API</p>';
    echo '</div>';
    echo '</div>';
    echo '<button type="button" id="btn-prepare-sync" class="nb-btn nb-btn-primary">';
    echo '<span id="btn-prepare-sync-text">Sincronizar</span>';
    echo '<span id="btn-prepare-sync-spinner" class="nb-hidden"><span class="nb-spinner"></span></span>';
    echo '</button>';
    echo '</div>';
    
    // Ver logs
    echo '<div class="nb-action-card">';
    echo '<div class="nb-action-info">';
    echo '<div class="nb-action-icon">';
    echo '<svg class="nb-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
    echo '</div>';
    echo '<div class="nb-action-text">';
    echo '<h4>Historial de sincronizaciones</h4>';
    echo '<p>Consulta los logs detallados</p>';
    echo '</div>';
    echo '</div>';
    echo '<a href="' . admin_url('tools.php?page=nb-logs') . '" class="nb-btn nb-btn-secondary">Ver logs</a>';
    echo '</div>';
    
    // Sincronizar descripciones
    echo '<div class="nb-action-card">';
    echo '<div class="nb-action-info">';
    echo '<div class="nb-action-icon">';
    echo '<svg class="nb-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
    echo '</div>';
    echo '<div class="nb-action-text">';
    echo '<h4>Sincronizar descripciones</h4>';
    echo '<p>Actualiza las descripciones desde la API</p>';
    echo '</div>';
    echo '</div>';
    echo '<button type="button" id="update-description-btn" class="nb-btn nb-btn-secondary">Actualizar</button>';
    echo '</div>';
    
    // Eliminar productos
    echo '<div class="nb-action-card">';
    echo '<div class="nb-action-info">';
    echo '<div class="nb-action-icon" style="background: var(--nb-error-bg); color: var(--nb-error);">';
    echo '<svg class="nb-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
    echo '</div>';
    echo '<div class="nb-action-text">';
    echo '<h4>Eliminar productos</h4>';
    echo '<p>Elimina todos los productos NewBytes</p>';
    echo '</div>';
    echo '</div>';
    echo '<button type="button" id="delete-all-btn" class="nb-btn nb-btn-danger">Eliminar</button>';
    echo '</div>';
    
    echo '</div>'; // actions-grid
    echo '</div>'; // actions-section

    // Footer
    echo '<div class="nb-text-center nb-mt-6" style="color: var(--nb-text-tertiary); font-size: 13px;">';
    echo '<p>¿Necesitas ayuda? Visita la <a href="https://developers.nb.com.ar/" target="_blank" style="color: var(--nb-text-secondary); text-decoration: underline;">documentación oficial</a></p>';
    echo '</div>';

    // Nonce para AJAX
    echo '<input type="hidden" id="nb_sync_nonce" value="' . wp_create_nonce('nb_sync_nonce') . '" />';

    echo '</div>'; // container
    echo '</div>'; // wrap

    // Modales
    nb_render_modals_v2();
    
    // JavaScript
    nb_render_scripts_v2();
}

/**
 * Renderiza los modales con el nuevo diseño
 */
function nb_render_modals_v2()
{
    // Modal: Confirmar eliminación
    echo '<div id="delete-confirm-modal" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-warning">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title">Eliminar productos</h3>';
    echo '<p class="nb-modal-text">Esta acción eliminará todos los productos de NewBytes. Esta acción no se puede deshacer.</p>';
    echo '</div>';
    echo '<form id="confirm-delete-form">';
    echo '<input type="hidden" name="action" value="nb_delete_products" />';
    echo '<input type="hidden" name="delete_all" value="1" />';
    wp_nonce_field('nb_delete_all', 'nb_delete_all_nonce');
    echo '<div class="nb-modal-footer">';
    echo '<button type="button" id="cancel-delete" class="nb-btn nb-btn-secondary">Cancelar</button>';
    echo '<button type="button" id="confirm-delete-btn" class="nb-btn nb-btn-danger">Eliminar todo</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // Modal: Confirmar actualización de descripciones
    echo '<div id="update-description-confirm-modal" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-warning">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title">Actualizar descripciones</h3>';
    echo '<p class="nb-modal-text">Se reemplazarán todas las descripciones de los productos NewBytes con las de la API.</p>';
    echo '</div>';
    echo '<form id="confirm-update-description-form">';
    echo '<input type="hidden" name="action" value="nb_update_description_products" />';
    echo '<input type="hidden" name="update_description_all" value="1" />';
    wp_nonce_field('nb_update_description_all', 'nb_update_description_all_nonce');
    echo '<div class="nb-modal-footer">';
    echo '<button type="button" id="cancel-update-description" class="nb-btn nb-btn-secondary">Cancelar</button>';
    echo '<button type="button" id="confirm-update-description-btn" class="nb-btn nb-btn-primary">Actualizar</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // Modal: Éxito
    echo '<div id="success-confirm-modal" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-success">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title">Operación completada</h3>';
    echo '<p class="nb-modal-text" id="success-modal-message">La operación se realizó correctamente.</p>';
    echo '</div>';
    echo '<div class="nb-modal-footer">';
    echo '<button type="button" id="close-success-modal-btn" class="nb-btn nb-btn-success">Cerrar</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Modal: Error
    echo '<div id="fail-confirm-modal" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-error">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title">Error</h3>';
    echo '<p class="nb-modal-text" id="fail-modal-message">Ocurrió un error. Por favor, inténtalo de nuevo.</p>';
    echo '</div>';
    echo '<div class="nb-modal-footer">';
    echo '<button type="button" id="close-fail-modal-btn" class="nb-btn nb-btn-secondary">Cerrar</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Modal: Confirmar sincronización
    echo '<div id="nb-modal-sync-confirm" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-info">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title">Confirmar sincronización</h3>';
    echo '</div>';
    echo '<div class="nb-modal-body">';
    echo '<div class="nb-stats-grid">';
    echo '<div class="nb-stat-card">';
    echo '<div class="nb-stat-value" id="nb-sync-total">-</div>';
    echo '<div class="nb-stat-label">Total productos</div>';
    echo '</div>';
    echo '<div class="nb-stat-card">';
    echo '<div class="nb-stat-value nb-stat-value-success" id="nb-sync-with-stock">-</div>';
    echo '<div class="nb-stat-label">Con stock</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="nb-mt-4 nb-text-center">';
    echo '<p style="font-size: 13px; color: var(--nb-text-tertiary);">Tiempo estimado: <strong id="nb-sync-time">-</strong></p>';
    echo '</div>';
    echo '</div>';
    echo '<div class="nb-modal-footer">';
    echo '<button type="button" id="nb-btn-cancel-sync" class="nb-btn nb-btn-secondary">Cancelar</button>';
    echo '<button type="button" id="nb-btn-confirm-sync" class="nb-btn nb-btn-primary">Iniciar sincronización</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Modal: Progreso de sincronización
    echo '<div id="nb-modal-sync-progress" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-info" id="nb-progress-icon-loading">';
    echo '<svg class="nb-icon-lg nb-spinner-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation: nb-spin 1s linear infinite;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';
    echo '</div>';
    echo '<div class="nb-modal-icon nb-modal-icon-success nb-hidden" id="nb-progress-icon-success">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title" id="nb-progress-title">Sincronizando productos...</h3>';
    echo '</div>';
    echo '<div class="nb-modal-body">';
    echo '<div class="nb-progress-wrapper">';
    echo '<div class="nb-progress-header">';
    echo '<span class="nb-progress-label" id="nb-progress-text">0 / 0 productos</span>';
    echo '<span class="nb-progress-value" id="nb-progress-percent">0%</span>';
    echo '</div>';
    echo '<div class="nb-progress-track">';
    echo '<div class="nb-progress-fill" id="nb-progress-bar" style="width: 0%;"></div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="nb-stats-grid nb-mt-4">';
    echo '<div class="nb-stat-card">';
    echo '<div class="nb-stat-value nb-stat-value-success" id="nb-stat-created">0</div>';
    echo '<div class="nb-stat-label">Creados</div>';
    echo '</div>';
    echo '<div class="nb-stat-card">';
    echo '<div class="nb-stat-value nb-stat-value-info" id="nb-stat-updated">0</div>';
    echo '<div class="nb-stat-label">Actualizados</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="nb-modal-footer nb-hidden" id="nb-progress-close-container">';
    echo '<button type="button" id="nb-btn-close-progress" class="nb-btn nb-btn-success nb-w-full">Cerrar</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderiza los scripts JavaScript
 */
function nb_render_scripts_v2()
{
    ?>
    <script>
    jQuery(document).ready(function($) {
        // ============================================
        // Collapsible toggle text
        // ============================================
        $('.nb-collapsible-header').on('click', function() {
            var $toggle = $(this).find('#toggle-text');
            if ($(this).parent().hasClass('is-open')) {
                $toggle.text('Ocultar detalles');
            } else {
                $toggle.text('Mostrar detalles');
            }
        });

        // ============================================
        // Update connector button
        // ============================================
        $('#update-connector-btn').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="nb-spinner"></span> Actualizando...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'nb_update_connector' },
                success: function() { location.reload(); },
                error: function() {
                    alert('Error al actualizar el conector.');
                    $btn.prop('disabled', false).text('Actualizar');
                }
            });
        });

        // ============================================
        // Delete products modal
        // ============================================
        $('#delete-all-btn').on('click', function() {
            $('#delete-confirm-modal').removeClass('hidden').addClass('flex');
        });

        $('#cancel-delete').on('click', function() {
            $('#delete-confirm-modal').addClass('hidden').removeClass('flex');
        });

        $('#confirm-delete-btn').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="nb-spinner"></span> Eliminando...');
            
            var formData = new FormData($('#confirm-delete-form')[0]);
            
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                $('#delete-confirm-modal').addClass('hidden').removeClass('flex');
                if (data.success) {
                    $('#success-modal-message').text('Todos los productos de NewBytes han sido eliminados.');
                    $('#success-confirm-modal').removeClass('hidden').addClass('flex');
                } else {
                    $('#fail-modal-message').text(data.data || 'Error al eliminar los productos.');
                    $('#fail-confirm-modal').removeClass('hidden').addClass('flex');
                }
                $btn.prop('disabled', false).text('Eliminar todo');
            })
            .catch(() => {
                $('#delete-confirm-modal').addClass('hidden').removeClass('flex');
                $('#fail-modal-message').text('Error de conexión.');
                $('#fail-confirm-modal').removeClass('hidden').addClass('flex');
                $btn.prop('disabled', false).text('Eliminar todo');
            });
        });

        // ============================================
        // Update descriptions modal
        // ============================================
        $('#update-description-btn').on('click', function() {
            $('#update-description-confirm-modal').removeClass('hidden').addClass('flex');
        });

        $('#cancel-update-description').on('click', function() {
            $('#update-description-confirm-modal').addClass('hidden').removeClass('flex');
        });

        $('#confirm-update-description-btn').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="nb-spinner"></span> Procesando...');
            
            var formData = new FormData($('#confirm-update-description-form')[0]);
            
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                $('#update-description-confirm-modal').addClass('hidden').removeClass('flex');
                if (data.success) {
                    var stats = data.data.stats || {};
                    var total = (stats.updated || 0) + (stats.created || 0);
                    $('#success-modal-message').text('Se actualizaron las descripciones de ' + total + ' productos.');
                    $('#success-confirm-modal').removeClass('hidden').addClass('flex');
                } else {
                    $('#fail-modal-message').text('Error al actualizar las descripciones.');
                    $('#fail-confirm-modal').removeClass('hidden').addClass('flex');
                }
                $btn.prop('disabled', false).text('Actualizar');
            })
            .catch(() => {
                $('#update-description-confirm-modal').addClass('hidden').removeClass('flex');
                $('#fail-modal-message').text('Error de conexión.');
                $('#fail-confirm-modal').removeClass('hidden').addClass('flex');
                $btn.prop('disabled', false).text('Actualizar');
            });
        });

        // ============================================
        // Success/Fail modal close
        // ============================================
        $('#close-success-modal-btn').on('click', function() {
            $('#success-confirm-modal').addClass('hidden').removeClass('flex');
            location.reload();
        });

        $('#close-fail-modal-btn').on('click', function() {
            $('#fail-confirm-modal').addClass('hidden').removeClass('flex');
        });

        // ============================================
        // Sync with progress
        // ============================================
        var syncData = {};
        var totalCreated = 0;
        var totalUpdated = 0;

        $('#btn-prepare-sync').on('click', function() {
            var $btn = $(this);
            $('#btn-prepare-sync-text').addClass('nb-hidden');
            $('#btn-prepare-sync-spinner').removeClass('nb-hidden');
            $btn.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'nb_prepare_sync',
                    nonce: $('#nb_sync_nonce').val()
                },
                success: function(response) {
                    $('#btn-prepare-sync-text').removeClass('nb-hidden');
                    $('#btn-prepare-sync-spinner').addClass('nb-hidden');
                    $btn.prop('disabled', false);

                    if (response.success) {
                        syncData = response.data;
                        $('#nb-sync-total').text(syncData.total_products);
                        $('#nb-sync-with-stock').text(syncData.products_with_stock);
                        $('#nb-sync-time').text('~' + syncData.estimated_time);
                        $('#nb-modal-sync-confirm').removeClass('hidden').addClass('flex');
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    $('#btn-prepare-sync-text').removeClass('nb-hidden');
                    $('#btn-prepare-sync-spinner').addClass('nb-hidden');
                    $btn.prop('disabled', false);
                    alert('Error de conexión.');
                }
            });
        });

        $('#nb-btn-cancel-sync').on('click', function() {
            $('#nb-modal-sync-confirm').addClass('hidden').removeClass('flex');
        });

        $('#nb-btn-confirm-sync').on('click', function() {
            $('#nb-modal-sync-confirm').addClass('hidden').removeClass('flex');
            
            totalCreated = 0;
            totalUpdated = 0;
            
            $('#nb-progress-title').text('Sincronizando productos...');
            $('#nb-progress-icon-loading').removeClass('nb-hidden');
            $('#nb-progress-icon-success').addClass('nb-hidden');
            $('#nb-progress-close-container').addClass('nb-hidden');
            $('#nb-progress-bar').css('width', '0%');
            $('#nb-progress-percent').text('0%');
            $('#nb-progress-text').text('0 / ' + syncData.total_products + ' productos');
            $('#nb-stat-created').text('0');
            $('#nb-stat-updated').text('0');
            $('#nb-modal-sync-progress').removeClass('hidden').addClass('flex');
            
            processBatch(0);
        });

        function processBatch(offset) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'nb_process_batch',
                    nonce: $('#nb_sync_nonce').val(),
                    offset: offset,
                    batch_size: 50,
                    sync_description: 'false'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        totalCreated += data.batch_created || 0;
                        totalUpdated += data.batch_updated || 0;
                        
                        var percent = Math.round((data.processed / data.total) * 100);
                        $('#nb-progress-bar').css('width', percent + '%');
                        $('#nb-progress-percent').text(percent + '%');
                        $('#nb-progress-text').text(data.processed + ' / ' + data.total + ' productos');
                        $('#nb-stat-created').text(totalCreated);
                        $('#nb-stat-updated').text(totalUpdated);
                        
                        if (data.completed) {
                            $('#nb-progress-title').text('¡Sincronización completada!');
                            $('#nb-progress-icon-loading').addClass('nb-hidden');
                            $('#nb-progress-icon-success').removeClass('nb-hidden');
                            $('#nb-progress-close-container').removeClass('nb-hidden');
                            $('#nb-progress-bar').addClass('nb-progress-fill-success');
                            
                            if (data.stats) {
                                $('#nb-stat-created').text(data.stats.created);
                                $('#nb-stat-updated').text(data.stats.updated);
                            }
                        } else {
                            processBatch(data.processed);
                        }
                    } else {
                        alert('Error: ' + response.data.message);
                        $('#nb-modal-sync-progress').addClass('hidden').removeClass('flex');
                    }
                },
                error: function() {
                    alert('Error de conexión durante la sincronización.');
                    $('#nb-modal-sync-progress').addClass('hidden').removeClass('flex');
                }
            });
        }

        $('#nb-btn-close-progress').on('click', function() {
            $('#nb-modal-sync-progress').addClass('hidden').removeClass('flex');
            location.reload();
        });

        // Close modals on backdrop click
        $('.nb-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).addClass('hidden').removeClass('flex');
            }
        });
    });
    </script>
    <?php
}

// ============================================
// Funciones auxiliares (mantener compatibilidad)
// ============================================

add_action('wp_ajax_nb_update_connector', 'nb_update_connector');
function nb_update_connector()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $zip_url = 'https://github.com/New-Bytes/woocommerce-newbytes/archive/refs/heads/main.zip';
    $upload_dir = wp_upload_dir();
    $zip_file = $upload_dir['path'] . '/woocommerce-newbytes-main.zip';

    $response = wp_remote_get($zip_url, array('timeout' => 300));
    if (is_wp_error($response)) {
        wp_die('Error downloading the update.');
    }

    $zip_data = wp_remote_retrieve_body($response);
    if (empty($zip_data)) {
        wp_die('Empty response from the update server.');
    }

    if (!file_put_contents($zip_file, $zip_data)) {
        wp_die('Error saving the update file.');
    }

    if (!class_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    WP_Filesystem();
    $unzip_result = unzip_file($zip_file, WP_PLUGIN_DIR);

    if (is_wp_error($unzip_result)) {
        wp_die('Error unzipping the update file.');
    }

    unlink($zip_file);

    echo 'Conector NB actualizado correctamente.';
    wp_die();
}

function get_latest_version_nb()
{
    $file_url = 'https://raw.githubusercontent.com/New-Bytes/woocommerce-newbytes/main/woocommerce-newbytes.php';
    $response = wp_remote_get($file_url);

    if (is_wp_error($response)) {
        return 'Error fetching version data';
    }

    $body = wp_remote_retrieve_body($response);
    preg_match('/Version:\s*(\S+)/', $body, $matches);

    if (isset($matches[1])) {
        return $matches[1];
    } else {
        return 'Version not found';
    }
}
