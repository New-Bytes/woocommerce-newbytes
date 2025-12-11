<?php
/**
 * Página de configuración del Conector NewBytes
 * Versión 3 - UX Mejorada con Tabs, Dashboard, Toast y más
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

    // Obtener estadísticas de productos
    $product_stats = nb_get_product_stats();
    
    // Información de sincronización
    $interval = intval(get_option('nb_sync_interval', 3600));
    $next_sync = wp_next_scheduled('nb_cron_sync_event');
    $last_update = get_option('nb_last_update');
    
    if (!$next_sync) {
        $next_sync = time() + $interval;
    }
    $time_diff = max(0, $next_sync - time());
    $minutes_remaining = floor($time_diff / 60);

    echo '<div class="nb-wrap">';
    echo '<div class="nb-container" style="max-width: 900px;">';

    // Toast container
    echo '<div id="nb-toast-container" class="nb-toast-container"></div>';

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
    
    echo '<div class="nb-flex nb-items-center nb-gap-4">';
    if ($show_new_version_button) {
        echo '<button type="button" id="update-connector-btn" class="nb-btn nb-btn-sm nb-btn-secondary nb-version-badge-new">';
        echo '<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>';
        echo 'v' . esc_html($latest_commit);
        echo '</button>';
    } else {
        echo '<span class="nb-version-badge">v' . VERSION_NB . '</span>';
    }
    echo '</div>';
    echo '</header>';

    // ============================================
    // Dashboard de Estado
    // ============================================
    echo '<div class="nb-dashboard">';
    
    // Productos totales
    echo '<div class="nb-dashboard-card">';
    echo '<svg class="nb-dashboard-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>';
    echo '<div class="nb-dashboard-value">' . number_format($product_stats['total']) . '</div>';
    echo '<div class="nb-dashboard-label">Productos NB</div>';
    echo '</div>';
    
    // Con stock
    echo '<div class="nb-dashboard-card">';
    echo '<svg class="nb-dashboard-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    echo '<div class="nb-dashboard-value" style="color: var(--nb-success);">' . number_format($product_stats['in_stock']) . '</div>';
    echo '<div class="nb-dashboard-label">Con stock</div>';
    echo '</div>';
    
    // Próxima sync
    echo '<div class="nb-dashboard-card">';
    echo '<svg class="nb-dashboard-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    echo '<div class="nb-dashboard-value">' . $minutes_remaining . '<span style="font-size: 14px; font-weight: 400;">m</span></div>';
    echo '<div class="nb-dashboard-label">Próxima sync</div>';
    echo '</div>';
    
    // Estado conexión
    echo '<div class="nb-dashboard-card">';
    echo '<svg class="nb-dashboard-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>';
    $status_color = $is_authenticated ? 'var(--nb-success)' : 'var(--nb-error)';
    $status_text = $is_authenticated ? 'Online' : 'Offline';
    echo '<div class="nb-dashboard-value" style="color: ' . $status_color . ';">' . $status_text . '</div>';
    echo '<div class="nb-dashboard-label">Estado API</div>';
    echo '</div>';
    
    echo '</div>'; // dashboard

    // ============================================
    // Alerta FIFU
    // ============================================
    if (!is_plugin_active('featured-image-from-url/featured-image-from-url.php') && !is_plugin_active('fifu-premium/fifu-premium.php')) {
        echo '<div class="nb-alert nb-alert-warning nb-mb-4">';
        echo '<svg class="nb-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
        echo '<div class="nb-alert-content">';
        echo '<p class="nb-alert-title">Plugin requerido</p>';
        echo '<p class="nb-alert-text">Para las imágenes se requiere <a href="' . wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=featured-image-from-url'), 'install-plugin_featured-image-from-url') . '" style="text-decoration: underline;">FIFU</a></p>';
        echo '</div>';
        echo '</div>';
    }

    // ============================================
    // Tabs Navigation
    // ============================================
    echo '<div class="nb-card">';
    echo '<div class="nb-tabs">';
    echo '<button class="nb-tab active" data-tab="credentials">Credenciales</button>';
    echo '<button class="nb-tab" data-tab="sync">Sincronización</button>';
    echo '<button class="nb-tab" data-tab="tools">Herramientas</button>';
    echo '</div>';

    // ============================================
    // Tab: Credenciales
    // ============================================
    echo '<div id="tab-credentials" class="nb-tab-content active">';
    echo '<div class="nb-card-body">';
    
    // Estado de conexión
    echo '<div class="nb-connection-status" id="connection-status">';
    $indicator_class = $is_authenticated ? 'connected' : 'disconnected';
    echo '<div class="nb-connection-indicator ' . $indicator_class . '" id="connection-indicator"></div>';
    echo '<div class="nb-connection-text">';
    echo '<div class="nb-connection-title" id="connection-title">' . ($is_authenticated ? 'Conectado a NewBytes API' : 'Sin conexión') . '</div>';
    echo '<div class="nb-connection-subtitle" id="connection-subtitle">' . ($is_authenticated ? 'Las credenciales son válidas' : 'Verifica tus credenciales') . '</div>';
    echo '</div>';
    echo '<button type="button" id="test-connection-btn" class="nb-btn nb-btn-sm nb-btn-secondary">';
    echo '<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';
    echo 'Probar';
    echo '</button>';
    echo '</div>';
    
    echo '<form id="nb-credentials-form">';
    
    // Usuario
    echo '<div class="nb-form-group">';
    echo '<label for="nb_user" class="nb-form-label">Usuario</label>';
    echo '<input type="text" name="nb_user" id="nb_user" value="' . esc_attr(get_option('nb_user')) . '" class="nb-input" placeholder="Tu usuario de NewBytes" autocomplete="username" />';
    echo '</div>';
    
    // Contraseña
    echo '<div class="nb-form-group">';
    echo '<label for="nb_password" class="nb-form-label">Contraseña</label>';
    echo '<input type="password" name="nb_password" id="nb_password" value="' . esc_attr(get_option('nb_password')) . '" class="nb-input" placeholder="••••••••" autocomplete="current-password" />';
    echo '</div>';
    
    // Prefijo SKU con preview
    echo '<div class="nb-form-group">';
    echo '<div class="nb-flex nb-items-center nb-gap-2">';
    echo '<label for="nb_prefix" class="nb-form-label" style="margin-bottom: 0;">Prefijo SKU</label>';
    echo '<div class="nb-tooltip-wrapper">';
    echo '<span class="nb-tooltip-trigger"><svg class="nb-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></span>';
    echo '<span class="nb-tooltip">Identifica productos NewBytes en tu catálogo</span>';
    echo '</div>';
    echo '</div>';
    echo '<input type="text" name="nb_prefix" id="nb_prefix" value="' . esc_attr(get_option('nb_prefix')) . '" class="nb-input" placeholder="NB_" />';
    echo '<div class="nb-sku-preview">';
    echo '<span class="nb-sku-preview-label">Preview:</span>';
    echo '<span class="nb-sku-preview-value" id="sku-preview">' . esc_html(get_option('nb_prefix', 'NB_')) . '12345</span>';
    echo '</div>';
    echo '</div>';
    
    echo '<button type="submit" class="nb-btn nb-btn-primary">';
    echo '<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo 'Guardar credenciales';
    echo '</button>';
    
    echo '</form>';
    echo '</div>'; // card-body
    echo '</div>'; // tab-credentials

    // ============================================
    // Tab: Sincronización
    // ============================================
    echo '<div id="tab-sync" class="nb-tab-content">';
    echo '<div class="nb-card-body">';
    
    echo '<form id="nb-sync-settings-form">';
    
    // Intervalo
    echo '<div class="nb-form-group">';
    echo '<label for="nb_sync_interval" class="nb-form-label">Intervalo de sincronización automática</label>';
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
    
    // Checkboxes de precio
    echo '<div class="nb-form-group">';
    echo '<label class="nb-form-label">Opciones de precio</label>';
    echo '<label class="nb-checkbox-group" style="margin-bottom: 8px;">';
    echo '<input type="checkbox" name="nb_sync_no_iva" id="nb_sync_no_iva" value="1" ' . checked(1, get_option('nb_sync_no_iva'), false) . ' class="nb-checkbox" />';
    echo '<span class="nb-checkbox-label">Sincronizar precios sin IVA</span>';
    echo '</label>';
    echo '<label class="nb-checkbox-group">';
    echo '<input type="checkbox" name="nb_sync_usd" id="nb_sync_usd" value="1" ' . checked(1, get_option('nb_sync_usd'), false) . ' class="nb-checkbox" />';
    echo '<span class="nb-checkbox-label">Sincronizar precios en USD</span>';
    echo '</label>';
    echo '</div>';
    
    // Descripción adicional
    echo '<div class="nb-form-group">';
    echo '<label for="nb_description" class="nb-form-label">Descripción adicional (opcional)</label>';
    echo '<textarea name="nb_description" id="nb_description" class="nb-input nb-textarea" placeholder="Texto que se agregará a todos los productos...">' . esc_textarea(get_option('nb_description')) . '</textarea>';
    echo '<p class="nb-form-hint">Se añadirá al final de la descripción de cada producto</p>';
    echo '</div>';
    
    // Info última sync
    $last_update_formatted = $last_update ? date('d/m/Y H:i', strtotime($last_update . '-3 hours')) : 'Nunca';
    echo '<div class="nb-data-row" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--nb-border-primary);">';
    echo '<span class="nb-data-label">Última sincronización</span>';
    echo '<span class="nb-data-value" id="last-sync-time">' . esc_html($last_update_formatted) . '</span>';
    echo '</div>';
    
    echo '<button type="submit" class="nb-btn nb-btn-primary nb-mt-4">';
    echo '<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo 'Guardar configuración';
    echo '</button>';
    
    echo '</form>';
    echo '</div>'; // card-body
    echo '</div>'; // tab-sync

    // ============================================
    // Tab: Herramientas
    // ============================================
    echo '<div id="tab-tools" class="nb-tab-content">';
    echo '<div class="nb-card-body">';
    
    // Sincronizar productos
    echo '<div class="nb-action-card nb-mb-4">';
    echo '<div class="nb-action-info">';
    echo '<div class="nb-action-icon">';
    echo '<svg class="nb-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';
    echo '</div>';
    echo '<div class="nb-action-text">';
    echo '<h4>Sincronizar productos</h4>';
    echo '<p>Actualiza todos los productos desde la API de NewBytes</p>';
    echo '</div>';
    echo '</div>';
    echo '<button type="button" id="btn-prepare-sync" class="nb-btn nb-btn-primary">';
    echo '<span id="btn-prepare-sync-text">Sincronizar ahora</span>';
    echo '<span id="btn-prepare-sync-spinner" class="nb-hidden"><span class="nb-spinner"></span></span>';
    echo '</button>';
    echo '</div>';
    
    // Ver logs
    echo '<div class="nb-action-card nb-mb-4">';
    echo '<div class="nb-action-info">';
    echo '<div class="nb-action-icon">';
    echo '<svg class="nb-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
    echo '</div>';
    echo '<div class="nb-action-text">';
    echo '<h4>Historial de sincronizaciones</h4>';
    echo '<p>Consulta los logs detallados de cada sincronización</p>';
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
    echo '<h4>Actualizar descripciones</h4>';
    echo '<p>Reemplaza las descripciones con las de la API</p>';
    echo '</div>';
    echo '</div>';
    echo '<button type="button" id="update-description-btn" class="nb-btn nb-btn-secondary">Actualizar</button>';
    echo '</div>';
    
    // Zona de peligro
    echo '<div class="nb-danger-zone">';
    echo '<div class="nb-danger-zone-header" onclick="this.parentElement.classList.toggle(\'is-open\')">';
    echo '<div class="nb-danger-zone-title">';
    echo '<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
    echo 'Zona de peligro';
    echo '</div>';
    echo '<div class="nb-danger-zone-toggle">';
    echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
    echo '</div>';
    echo '</div>';
    echo '<div class="nb-danger-zone-content">';
    echo '<div class="nb-action-card">';
    echo '<div class="nb-action-info">';
    echo '<div class="nb-action-icon" style="background: var(--nb-error-bg); color: var(--nb-error);">';
    echo '<svg class="nb-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
    echo '</div>';
    echo '<div class="nb-action-text">';
    echo '<h4>Eliminar todos los productos</h4>';
    echo '<p>Elimina permanentemente todos los productos de NewBytes</p>';
    echo '</div>';
    echo '</div>';
    echo '<button type="button" id="delete-all-btn" class="nb-btn nb-btn-danger">Eliminar</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>'; // card-body
    echo '</div>'; // tab-tools

    echo '</div>'; // card

    // Footer
    echo '<div class="nb-text-center nb-mt-6" style="color: var(--nb-text-tertiary); font-size: 13px;">';
    echo '<p>¿Necesitas ayuda? <a href="https://developers.nb.com.ar/" target="_blank" style="color: var(--nb-text-secondary); text-decoration: underline;">Documentación</a></p>';
    echo '</div>';

    // Nonces
    echo '<input type="hidden" id="nb_sync_nonce" value="' . wp_create_nonce('nb_sync_nonce') . '" />';
    echo '<input type="hidden" id="nb_settings_nonce" value="' . wp_create_nonce('nb_settings_nonce') . '" />';

    echo '</div>'; // container
    echo '</div>'; // wrap

    // Modales
    nb_render_modals_v3();
    
    // JavaScript
    nb_render_scripts_v3();
}

/**
 * Obtener estadísticas de productos NewBytes
 */
function nb_get_product_stats() {
    global $wpdb;
    
    $prefix = get_option('nb_prefix', 'NB_');
    
    // Total de productos con el prefijo
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = '_sku' 
         AND pm.meta_value LIKE %s
         AND p.post_type = 'product'
         AND p.post_status = 'publish'",
        $prefix . '%'
    ));
    
    // Productos con stock
    $in_stock = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = '_sku' 
         AND pm.meta_value LIKE %s
         AND pm2.meta_key = '_stock'
         AND CAST(pm2.meta_value AS SIGNED) > 0
         AND p.post_type = 'product'
         AND p.post_status = 'publish'",
        $prefix . '%'
    ));
    
    return array(
        'total' => intval($total),
        'in_stock' => intval($in_stock),
        'out_of_stock' => intval($total) - intval($in_stock)
    );
}

/**
 * Renderiza los modales
 */
function nb_render_modals_v3()
{
    // Modal: Confirmar eliminación
    echo '<div id="delete-confirm-modal" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-warning">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title">Eliminar productos</h3>';
    echo '<p class="nb-modal-text">Esta acción eliminará todos los productos de NewBytes. No se puede deshacer.</p>';
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

    // Modal: Confirmar actualización de descripciones (paso 1 - preparar)
    echo '<div id="nb-modal-desc-confirm" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-info">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title">Actualizar descripciones</h3>';
    echo '</div>';
    echo '<div class="nb-modal-body">';
    echo '<div class="nb-stats-grid">';
    echo '<div class="nb-stat-card"><div class="nb-stat-value" id="nb-desc-total">-</div><div class="nb-stat-label">Descripciones</div></div>';
    echo '<div class="nb-stat-card"><div class="nb-stat-value nb-stat-value-info" id="nb-desc-file">-</div><div class="nb-stat-label">Archivo JSON</div></div>';
    echo '</div>';
    echo '<p class="nb-mt-4 nb-text-center" style="font-size: 13px; color: var(--nb-text-tertiary);">Tiempo estimado: <strong id="nb-desc-time">-</strong></p>';
    echo '<p class="nb-mt-2 nb-text-center" style="font-size: 12px; color: var(--nb-text-tertiary);">Se descargará el catálogo de descripciones y se procesará localmente.</p>';
    echo '</div>';
    echo '<div class="nb-modal-footer">';
    echo '<button type="button" id="nb-btn-cancel-desc" class="nb-btn nb-btn-secondary">Cancelar</button>';
    echo '<button type="button" id="nb-btn-confirm-desc" class="nb-btn nb-btn-primary">Iniciar actualización</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Modal: Progreso de actualización de descripciones
    echo '<div id="nb-modal-desc-progress" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-info" id="nb-desc-progress-icon-loading">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation: nb-spin 1s linear infinite;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';
    echo '</div>';
    echo '<div class="nb-modal-icon nb-modal-icon-success nb-hidden" id="nb-desc-progress-icon-success">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title" id="nb-desc-progress-title">Actualizando descripciones...</h3>';
    echo '</div>';
    echo '<div class="nb-modal-body">';
    echo '<div class="nb-progress-wrapper">';
    echo '<div class="nb-progress-header">';
    echo '<span class="nb-progress-label" id="nb-desc-progress-text">0 / 0</span>';
    echo '<span class="nb-progress-value" id="nb-desc-progress-percent">0%</span>';
    echo '</div>';
    echo '<div class="nb-progress-track"><div class="nb-progress-fill" id="nb-desc-progress-bar" style="width: 0%;"></div></div>';
    echo '</div>';
    echo '<div class="nb-stats-grid nb-mt-4">';
    echo '<div class="nb-stat-card"><div class="nb-stat-value nb-stat-value-success" id="nb-desc-stat-updated">0</div><div class="nb-stat-label">Actualizadas</div></div>';
    echo '<div class="nb-stat-card"><div class="nb-stat-value nb-stat-value-warning" id="nb-desc-stat-notfound">0</div><div class="nb-stat-label">No encontrados</div></div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="nb-modal-footer nb-hidden" id="nb-desc-progress-close-container">';
    echo '<button type="button" id="nb-btn-close-desc-progress" class="nb-btn nb-btn-success nb-w-full">Cerrar</button>';
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
    echo '<div class="nb-stat-card"><div class="nb-stat-value" id="nb-sync-total">-</div><div class="nb-stat-label">Total</div></div>';
    echo '<div class="nb-stat-card"><div class="nb-stat-value nb-stat-value-success" id="nb-sync-with-stock">-</div><div class="nb-stat-label">Con stock</div></div>';
    echo '</div>';
    echo '<p class="nb-mt-4 nb-text-center" style="font-size: 13px; color: var(--nb-text-tertiary);">Tiempo estimado: <strong id="nb-sync-time">-</strong></p>';
    echo '</div>';
    echo '<div class="nb-modal-footer">';
    echo '<button type="button" id="nb-btn-cancel-sync" class="nb-btn nb-btn-secondary">Cancelar</button>';
    echo '<button type="button" id="nb-btn-confirm-sync" class="nb-btn nb-btn-primary">Iniciar</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Modal: Progreso de sincronización
    echo '<div id="nb-modal-sync-progress" class="nb-modal hidden">';
    echo '<div class="nb-modal-content">';
    echo '<div class="nb-modal-header">';
    echo '<div class="nb-modal-icon nb-modal-icon-info" id="nb-progress-icon-loading">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation: nb-spin 1s linear infinite;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';
    echo '</div>';
    echo '<div class="nb-modal-icon nb-modal-icon-success nb-hidden" id="nb-progress-icon-success">';
    echo '<svg class="nb-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    echo '</div>';
    echo '<h3 class="nb-modal-title" id="nb-progress-title">Sincronizando...</h3>';
    echo '</div>';
    echo '<div class="nb-modal-body">';
    echo '<div class="nb-progress-wrapper">';
    echo '<div class="nb-progress-header">';
    echo '<span class="nb-progress-label" id="nb-progress-text">0 / 0</span>';
    echo '<span class="nb-progress-value" id="nb-progress-percent">0%</span>';
    echo '</div>';
    echo '<div class="nb-progress-track"><div class="nb-progress-fill" id="nb-progress-bar" style="width: 0%;"></div></div>';
    echo '</div>';
    echo '<div class="nb-stats-grid nb-mt-4">';
    echo '<div class="nb-stat-card"><div class="nb-stat-value nb-stat-value-success" id="nb-stat-created">0</div><div class="nb-stat-label">Creados</div></div>';
    echo '<div class="nb-stat-card"><div class="nb-stat-value nb-stat-value-info" id="nb-stat-updated">0</div><div class="nb-stat-label">Actualizados</div></div>';
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
function nb_render_scripts_v3()
{
    $ajax_url = esc_url(admin_url('admin-ajax.php'));
    ?>
    <script>
    jQuery(document).ready(function($) {
        // ============================================
        // Toast System
        // ============================================
        function showToast(type, title, message) {
            var icons = {
                success: '<svg class="nb-toast-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
                error: '<svg class="nb-toast-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
                warning: '<svg class="nb-toast-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                info: '<svg class="nb-toast-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
            };
            
            var $toast = $('<div class="nb-toast nb-toast-' + type + '">' +
                icons[type] +
                '<div class="nb-toast-content">' +
                '<p class="nb-toast-title">' + title + '</p>' +
                (message ? '<p class="nb-toast-message">' + message + '</p>' : '') +
                '</div>' +
                '<button class="nb-toast-close"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>' +
                '</div>');
            
            $('#nb-toast-container').append($toast);
            
            $toast.find('.nb-toast-close').on('click', function() {
                $toast.addClass('hiding');
                setTimeout(function() { $toast.remove(); }, 200);
            });
            
            setTimeout(function() {
                $toast.addClass('hiding');
                setTimeout(function() { $toast.remove(); }, 200);
            }, 5000);
        }

        // ============================================
        // Tabs Navigation
        // ============================================
        $('.nb-tab').on('click', function() {
            var tab = $(this).data('tab');
            $('.nb-tab').removeClass('active');
            $(this).addClass('active');
            $('.nb-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });

        // ============================================
        // SKU Preview
        // ============================================
        $('#nb_prefix').on('input', function() {
            var prefix = $(this).val() || 'NB_';
            $('#sku-preview').text(prefix + '12345');
        });

        // ============================================
        // Test Connection
        // ============================================
        $('#test-connection-btn').on('click', function() {
            var $btn = $(this);
            var $indicator = $('#connection-indicator');
            var $title = $('#connection-title');
            var $subtitle = $('#connection-subtitle');
            
            $btn.prop('disabled', true).html('<span class="nb-spinner"></span>');
            $indicator.removeClass('connected disconnected').addClass('checking');
            $title.text('Verificando conexión...');
            $subtitle.text('');
            
            $.ajax({
                url: '<?php echo $ajax_url; ?>',
                type: 'POST',
                data: {
                    action: 'nb_test_connection',
                    nonce: $('#nb_settings_nonce').val()
                },
                success: function(response) {
                    $btn.prop('disabled', false).html('<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Probar');
                    $indicator.removeClass('checking');
                    
                    if (response.success) {
                        $indicator.addClass('connected');
                        $title.text('Conectado a NewBytes API');
                        $subtitle.text('Las credenciales son válidas');
                        showToast('success', 'Conexión exitosa', 'API de NewBytes accesible');
                    } else {
                        $indicator.addClass('disconnected');
                        $title.text('Sin conexión');
                        $subtitle.text(response.data || 'Verifica tus credenciales');
                        showToast('error', 'Error de conexión', response.data || 'No se pudo conectar');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Probar');
                    $indicator.removeClass('checking').addClass('disconnected');
                    $title.text('Error de red');
                    $subtitle.text('No se pudo realizar la prueba');
                    showToast('error', 'Error', 'Error de conexión');
                }
            });
        });

        // ============================================
        // Save Credentials Form
        // ============================================
        $('#nb-credentials-form').on('submit', function(e) {
            e.preventDefault();
            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).html('<span class="nb-spinner"></span> Guardando...');
            
            $.ajax({
                url: '<?php echo $ajax_url; ?>',
                type: 'POST',
                data: {
                    action: 'nb_save_credentials',
                    nonce: $('#nb_settings_nonce').val(),
                    nb_user: $('#nb_user').val(),
                    nb_password: $('#nb_password').val(),
                    nb_prefix: $('#nb_prefix').val()
                },
                success: function(response) {
                    $btn.prop('disabled', false).html('<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Guardar credenciales');
                    if (response.success) {
                        showToast('success', 'Guardado', 'Credenciales actualizadas correctamente');
                        $('#test-connection-btn').click();
                    } else {
                        showToast('error', 'Error', response.data || 'No se pudo guardar');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Guardar credenciales');
                    showToast('error', 'Error', 'Error de conexión');
                }
            });
        });

        // ============================================
        // Save Sync Settings Form
        // ============================================
        $('#nb-sync-settings-form').on('submit', function(e) {
            e.preventDefault();
            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).html('<span class="nb-spinner"></span> Guardando...');
            
            $.ajax({
                url: '<?php echo $ajax_url; ?>',
                type: 'POST',
                data: {
                    action: 'nb_save_sync_settings',
                    nonce: $('#nb_settings_nonce').val(),
                    nb_sync_interval: $('#nb_sync_interval').val(),
                    nb_sync_no_iva: $('#nb_sync_no_iva').is(':checked') ? 1 : 0,
                    nb_sync_usd: $('#nb_sync_usd').is(':checked') ? 1 : 0,
                    nb_description: $('#nb_description').val()
                },
                success: function(response) {
                    $btn.prop('disabled', false).html('<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Guardar configuración');
                    if (response.success) {
                        showToast('success', 'Guardado', 'Configuración actualizada');
                    } else {
                        showToast('error', 'Error', response.data || 'No se pudo guardar');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<svg class="nb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Guardar configuración');
                    showToast('error', 'Error', 'Error de conexión');
                }
            });
        });

        // ============================================
        // Update Connector
        // ============================================
        $('#update-connector-btn').on('click', function() {
            if (!confirm('¿Actualizar el conector a la última versión?')) return;
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="nb-spinner"></span>');
            
            $.ajax({
                url: '<?php echo $ajax_url; ?>',
                type: 'POST',
                data: { action: 'nb_update_connector' },
                success: function() {
                    showToast('success', 'Actualizado', 'Recargando página...');
                    setTimeout(function() { location.reload(); }, 1000);
                },
                error: function() {
                    $btn.prop('disabled', false).text('Actualizar');
                    showToast('error', 'Error', 'No se pudo actualizar');
                }
            });
        });

        // ============================================
        // Delete Products
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
            
            $.ajax({
                url: '<?php echo $ajax_url; ?>',
                type: 'POST',
                data: $('#confirm-delete-form').serialize(),
                success: function(response) {
                    $('#delete-confirm-modal').addClass('hidden').removeClass('flex');
                    $btn.prop('disabled', false).text('Eliminar todo');
                    if (response.success) {
                        showToast('success', 'Eliminado', 'Productos eliminados correctamente');
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        showToast('error', 'Error', response.data || 'No se pudo eliminar');
                    }
                },
                error: function() {
                    $('#delete-confirm-modal').addClass('hidden').removeClass('flex');
                    $btn.prop('disabled', false).text('Eliminar todo');
                    showToast('error', 'Error', 'Error de conexión');
                }
            });
        });

        // ============================================
        // Update Descriptions (nuevo flujo con descarga masiva)
        // ============================================
        var descData = {};
        var totalDescUpdated = 0;
        var totalDescNotFound = 0;

        $('#update-description-btn').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="nb-spinner"></span>');
            
            // Primero preparar: descargar JSON de descripciones
            $.ajax({
                url: '<?php echo $ajax_url; ?>',
                type: 'POST',
                data: {
                    action: 'nb_prepare_descriptions',
                    nonce: $('#nb_sync_nonce').val()
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Actualizar');
                    
                    if (response.success) {
                        descData = response.data;
                        $('#nb-desc-total').text(descData.total_descriptions);
                        $('#nb-desc-file').html('<span style="font-size: 10px;">✓</span>');
                        $('#nb-desc-time').text('~' + descData.estimated_time);
                        $('#nb-modal-desc-confirm').removeClass('hidden').addClass('flex');
                    } else {
                        showToast('error', 'Error', response.data.message || 'No se pudo descargar');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Actualizar');
                    showToast('error', 'Error', 'Error de conexión al descargar descripciones');
                }
            });
        });

        $('#nb-btn-cancel-desc').on('click', function() {
            $('#nb-modal-desc-confirm').addClass('hidden').removeClass('flex');
        });

        $('#nb-btn-confirm-desc').on('click', function() {
            $('#nb-modal-desc-confirm').addClass('hidden').removeClass('flex');
            
            // Resetear estadísticas
            totalDescUpdated = 0;
            totalDescNotFound = 0;
            
            // Mostrar modal de progreso
            $('#nb-desc-progress-title').text('Actualizando descripciones...');
            $('#nb-desc-progress-icon-loading').removeClass('nb-hidden');
            $('#nb-desc-progress-icon-success').addClass('nb-hidden');
            $('#nb-desc-progress-close-container').addClass('nb-hidden');
            $('#nb-desc-progress-bar').css('width', '0%');
            $('#nb-desc-progress-percent').text('0%');
            $('#nb-desc-progress-text').text('0 / ' + descData.total_descriptions);
            $('#nb-desc-stat-updated').text('0');
            $('#nb-desc-stat-notfound').text('0');
            $('#nb-modal-desc-progress').removeClass('hidden').addClass('flex');
            
            // Iniciar procesamiento por lotes
            processDescriptionsBatch(0);
        });

        function processDescriptionsBatch(offset) {
            $.ajax({
                url: '<?php echo $ajax_url; ?>',
                type: 'POST',
                data: {
                    action: 'nb_process_descriptions_batch',
                    nonce: $('#nb_sync_nonce').val(),
                    offset: offset,
                    batch_size: 50
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Actualizar estadísticas
                        totalDescUpdated += data.batch_updated || 0;
                        totalDescNotFound += data.batch_not_found || 0;
                        
                        // Actualizar UI
                        var percent = Math.round((data.processed / data.total) * 100);
                        $('#nb-desc-progress-bar').css('width', percent + '%');
                        $('#nb-desc-progress-percent').text(percent + '%');
                        $('#nb-desc-progress-text').text(data.processed + ' / ' + data.total);
                        $('#nb-desc-stat-updated').text(totalDescUpdated);
                        $('#nb-desc-stat-notfound').text(totalDescNotFound);
                        
                        if (data.completed) {
                            // Completado
                            $('#nb-desc-progress-title').text('¡Completado!');
                            $('#nb-desc-progress-icon-loading').addClass('nb-hidden');
                            $('#nb-desc-progress-icon-success').removeClass('nb-hidden');
                            $('#nb-desc-progress-close-container').removeClass('nb-hidden');
                            
                            if (data.stats) {
                                $('#nb-desc-stat-updated').text(data.stats.updated);
                                $('#nb-desc-stat-notfound').text(data.stats.not_found);
                            }
                        } else {
                            // Continuar con el siguiente lote
                            processDescriptionsBatch(data.processed);
                        }
                    } else {
                        showToast('error', 'Error', response.data.message);
                        $('#nb-modal-desc-progress').addClass('hidden').removeClass('flex');
                    }
                },
                error: function() {
                    showToast('error', 'Error', 'Error de conexión durante la actualización');
                    $('#nb-modal-desc-progress').addClass('hidden').removeClass('flex');
                }
            });
        }

        $('#nb-btn-close-desc-progress').on('click', function() {
            $('#nb-modal-desc-progress').addClass('hidden').removeClass('flex');
            location.reload();
        });

        // ============================================
        // Sync with Progress
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
                url: '<?php echo $ajax_url; ?>',
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
                        showToast('error', 'Error', response.data.message || 'No se pudo preparar');
                    }
                },
                error: function() {
                    $('#btn-prepare-sync-text').removeClass('nb-hidden');
                    $('#btn-prepare-sync-spinner').addClass('nb-hidden');
                    $btn.prop('disabled', false);
                    showToast('error', 'Error', 'Error de conexión');
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
            
            $('#nb-progress-title').text('Sincronizando...');
            $('#nb-progress-icon-loading').removeClass('nb-hidden');
            $('#nb-progress-icon-success').addClass('nb-hidden');
            $('#nb-progress-close-container').addClass('nb-hidden');
            $('#nb-progress-bar').css('width', '0%').removeClass('nb-progress-fill-success');
            $('#nb-progress-percent').text('0%');
            $('#nb-progress-text').text('0 / ' + syncData.total_products);
            $('#nb-stat-created').text('0');
            $('#nb-stat-updated').text('0');
            $('#nb-modal-sync-progress').removeClass('hidden').addClass('flex');
            
            processBatch(0);
        });

        function processBatch(offset) {
            $.ajax({
                url: '<?php echo $ajax_url; ?>',
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
                        $('#nb-progress-text').text(data.processed + ' / ' + data.total);
                        $('#nb-stat-created').text(totalCreated);
                        $('#nb-stat-updated').text(totalUpdated);
                        
                        if (data.completed) {
                            $('#nb-progress-title').text('¡Completado!');
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
                        showToast('error', 'Error', response.data.message);
                        $('#nb-modal-sync-progress').addClass('hidden').removeClass('flex');
                    }
                },
                error: function() {
                    showToast('error', 'Error', 'Error durante la sincronización');
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

        // Keyboard navigation
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.nb-modal').addClass('hidden').removeClass('flex');
            }
        });
    });
    </script>
    <?php
}

// ============================================
// AJAX Handlers
// ============================================

add_action('wp_ajax_nb_test_connection', 'nb_ajax_test_connection');
function nb_ajax_test_connection() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $token = nb_get_token();
    if ($token) {
        wp_send_json_success();
    } else {
        wp_send_json_error('No se pudo autenticar');
    }
}

add_action('wp_ajax_nb_save_credentials', 'nb_ajax_save_credentials');
function nb_ajax_save_credentials() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    update_option('nb_user', sanitize_text_field($_POST['nb_user']));
    update_option('nb_password', sanitize_text_field($_POST['nb_password']));
    update_option('nb_prefix', sanitize_text_field($_POST['nb_prefix']));
    
    // Limpiar token para forzar re-autenticación
    delete_option('nb_token');
    
    wp_send_json_success();
}

add_action('wp_ajax_nb_save_sync_settings', 'nb_ajax_save_sync_settings');
function nb_ajax_save_sync_settings() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $old_interval = get_option('nb_sync_interval');
    $new_interval = intval($_POST['nb_sync_interval']);
    
    update_option('nb_sync_interval', $new_interval);
    update_option('nb_sync_no_iva', intval($_POST['nb_sync_no_iva']));
    update_option('nb_sync_usd', intval($_POST['nb_sync_usd']));
    update_option('nb_description', sanitize_textarea_field($_POST['nb_description']));
    
    // Actualizar cron si cambió el intervalo
    if ($old_interval != $new_interval) {
        nb_update_cron_schedule();
    }
    
    wp_send_json_success();
}

add_action('wp_ajax_nb_update_connector', 'nb_update_connector');
function nb_update_connector()
{
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }

    $zip_url = 'https://github.com/New-Bytes/woocommerce-newbytes/archive/refs/heads/main.zip';
    $upload_dir = wp_upload_dir();
    $zip_file = $upload_dir['path'] . '/woocommerce-newbytes-main.zip';

    $response = wp_remote_get($zip_url, array('timeout' => 300));
    if (is_wp_error($response)) {
        wp_die('Error de descarga');
    }

    $zip_data = wp_remote_retrieve_body($response);
    if (empty($zip_data)) {
        wp_die('Respuesta vacía');
    }

    if (!file_put_contents($zip_file, $zip_data)) {
        wp_die('Error al guardar');
    }

    if (!class_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    WP_Filesystem();
    $unzip_result = unzip_file($zip_file, WP_PLUGIN_DIR);

    if (is_wp_error($unzip_result)) {
        wp_die('Error al descomprimir');
    }

    unlink($zip_file);
    wp_send_json_success();
}

function get_latest_version_nb()
{
    $file_url = 'https://raw.githubusercontent.com/New-Bytes/woocommerce-newbytes/main/woocommerce-newbytes.php';
    $response = wp_remote_get($file_url, array('timeout' => 5));

    if (is_wp_error($response)) {
        return VERSION_NB;
    }

    $body = wp_remote_retrieve_body($response);
    preg_match('/Version:\s*(\S+)/', $body, $matches);

    return isset($matches[1]) ? $matches[1] : VERSION_NB;
}
