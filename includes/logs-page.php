<?php

/**
 * Página de visualización de logs de sincronización NewBytes
 */

function nb_logs_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Add Tailwind CSS
    echo '<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>';

    $plugin_url = plugin_dir_url(__FILE__);
    $icon_url = $plugin_url . '../assets/icon-128x128.png';

    // Obtener lista de logs y estadísticas
    $logs_list = NB_Logs_Manager::get_logs_list();
    $logs_stats = NB_Logs_Manager::get_logs_stats();

    echo '<div class="wrap bg-gray-100 min-h-screen p-6">';
    echo '<div class="max-w-7xl mx-auto">';

    // Header
    echo '<div class="bg-white rounded-lg shadow-md p-6 mb-6">';
    echo '<div class="flex items-center justify-between">';
    echo '<div class="flex items-center">';
    echo '<img src="' . esc_url($icon_url) . '" alt="Logo" class="w-12 h-12 mr-4">';
    echo '<div>';
    echo '<h1 class="text-2xl font-bold text-gray-800">Logs de Sincronización NewBytes</h1>';
    echo '<p class="text-gray-600">Historial completo de sincronizaciones con la API</p>';
    echo '</div>';
    echo '</div>';
    echo '<a href="' . admin_url('options-general.php?page=nb') . '" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">';
    echo 'Configuración';
    echo '</a>';
    echo '</div>';
    echo '</div>';

    // Estadísticas
    echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">';
    
    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<p class="text-2xl font-semibold text-gray-900">' . $logs_stats['total_logs'] . '</p>';
    echo '<p class="text-sm text-gray-600">Total de Logs</p>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<p class="text-2xl font-semibold text-gray-900">' . $logs_stats['formatted_size'] . '</p>';
    echo '<p class="text-sm text-gray-600">Tamaño Total</p>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<p class="text-2xl font-semibold text-gray-900">' . $logs_stats['newest_log'] . '</p>';
    echo '<p class="text-sm text-gray-600">Último Log</p>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow-md p-6">';
    echo '<p class="text-2xl font-semibold text-gray-900">' . $logs_stats['oldest_log'] . '</p>';
    echo '<p class="text-sm text-gray-600">Primer Log</p>';
    echo '</div>';

    echo '</div>';

    // Botón de limpieza de logs
    if ($logs_stats['total_logs'] > 15) {
        echo '<div class="mb-6">';
        echo '<div class="bg-amber-50 border border-amber-200 rounded-lg p-4">';
        echo '<div class="flex items-center justify-between">';
        echo '<div class="flex items-center">';
        echo '<svg class="w-5 h-5 text-amber-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>';
        echo '</svg>';
        echo '<div>';
        echo '<h3 class="text-sm font-medium text-amber-800">Tienes más de 15 logs</h3>';
        echo '<p class="text-xs text-amber-700">Se recomienda mantener solo los 15 logs más recientes para ahorrar espacio.</p>';
        echo '</div>';
        echo '</div>';
        echo '<button id="cleanup-logs-btn" class="inline-flex items-center px-3 py-2 border border-amber-300 shadow-sm text-xs font-medium rounded-md text-amber-700 bg-white hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">';
        echo '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
        echo '</svg>';
        echo 'Limpiar Logs Antiguos';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Tabla de logs
    echo '<div class="bg-white rounded-lg shadow-md overflow-visible">';
    echo '<div class="px-6 py-4 border-b border-gray-200">';
    echo '<h2 class="text-lg font-semibold text-gray-900">Historial de Sincronizaciones</h2>';
    echo '</div>';

    if (empty($logs_list)) {
        echo '<div class="p-6 text-center">';
        echo '<h3 class="mt-2 text-sm font-medium text-gray-900">No hay logs disponibles</h3>';
        echo '<p class="mt-1 text-sm text-gray-500">Los logs se crearán automáticamente cuando se ejecuten sincronizaciones.</p>';
        echo '</div>';
    } else {
        echo '<div class="overflow-x-auto overflow-y-visible">';
        echo '<table class="min-w-full divide-y divide-gray-200">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha y Hora</th>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tamaño</th>';
        echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="bg-white divide-y divide-gray-200">';

        foreach ($logs_list as $log) {
            echo '<tr class="hover:bg-gray-50 cursor-pointer log-row" data-filename="' . esc_attr($log['filename']) . '">';
            echo '<td class="px-6 py-4 whitespace-nowrap">';
            echo '<div class="text-sm font-medium text-gray-900">' . esc_html($log['formatted_date']) . '</div>';
            echo '<div class="text-sm text-gray-500">' . esc_html($log['formatted_time']) . '</div>';
            echo '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap">';
            echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">';
            echo esc_html($log['user']);
            echo '</span>';
            echo '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">';
            echo esc_html($log['formatted_size']);
            echo '</td>';
            echo '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">';
            echo '<div class="relative inline-block text-left">';
            
            // Botón del dropdown más compacto
            echo '<button type="button" class="inline-flex items-center justify-center rounded-md border border-gray-300 shadow-sm px-3 py-1.5 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dropdown-toggle" data-filename="' . esc_attr($log['filename']) . '">';
            echo '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>';
            echo '</svg>';
            echo '<span class="ml-1 hidden sm:inline">Acciones</span>';
            echo '</button>';
            
            // Menú dropdown más compacto
            echo '<div class="dropdown-menu hidden fixed z-50 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" data-filename="' . esc_attr($log['filename']) . '" style="top: 0; right: 0;">';
            echo '<div class="py-1">';
            
            // Opción Ver Detalles
            echo '<button class="view-log-btn group flex items-center w-full px-3 py-2 text-xs text-gray-700 hover:bg-blue-50 hover:text-blue-900" data-filename="' . esc_attr($log['filename']) . '">';
            echo '<svg class="w-3 h-3 mr-2 text-gray-400 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
            echo '</svg>';
            echo 'Ver Detalles';
            echo '</button>';
            
            // Opción Descargar
            echo '<button class="download-log-btn group flex items-center w-full px-3 py-2 text-xs text-gray-700 hover:bg-green-50 hover:text-green-900" data-filename="' . esc_attr($log['filename']) . '">';
            echo '<svg class="w-3 h-3 mr-2 text-gray-400 group-hover:text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>';
            echo '</svg>';
            echo 'Descargar';
            echo '</button>';
            
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Modal y JavaScript
    nb_logs_modal();
    nb_logs_javascript();
}

function nb_logs_modal()
{
    echo '<div id="log-details-modal" class="hidden fixed inset-0 bg-gray-800/70 backdrop-blur-sm flex justify-center items-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">';
    
    // Header del modal compacto
    echo '<div class="bg-white px-4 py-3 border-b border-gray-200 flex items-center justify-between">';
    echo '<div class="flex items-center space-x-2">';
    echo '<div class="w-6 h-6 bg-blue-500 rounded flex items-center justify-center">';
    echo '<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>';
    echo '</svg>';
    echo '</div>';
    echo '<h3 class="text-base font-medium text-gray-900" id="modal-title">Detalles del Log</h3>';
    echo '</div>';
    echo '<button type="button" id="close-modal" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors">×</button>';
    echo '</div>';

    // Contenido del modal
    echo '<div class="flex h-[calc(90vh-80px)]">';
    
    // Panel izquierdo - Información del log (ultra compacto)
    echo '<div class="w-1/6 border-r border-gray-200 p-3 overflow-y-auto">';
    echo '<div id="log-metadata">Cargando...</div>';
    echo '</div>';

    // Panel derecho - Búsqueda y productos (máximo espacio)
    echo '<div class="w-5/6 flex flex-col">';
    
    // Barra de búsqueda moderna
    echo '<div class="bg-white border-b border-slate-200 p-3">';
    echo '<div class="flex items-center space-x-4">';
    echo '<div class="relative flex-1">';
    echo '<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">';
    echo '</div>';
    echo '<input type="text" id="search-products" placeholder="Buscar productos por nombre..." class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition-all duration-200 text-sm">';
    echo '</div>';
    echo '<div class="flex items-center space-x-3">';
    echo '<span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">Productos Sincronizados</span>';
    echo '</div>';
    echo '</div>';
    echo '<div class="mt-3">';
    echo '<div class="text-sm text-slate-600 font-medium" id="search-results-count"></div>';
    echo '</div>';
    echo '</div>';

    // Lista de productos
    echo '<div class="flex-1 overflow-y-auto p-3" id="products-container">Cargando productos...</div>';

    echo '</div>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Modal para detalle de precios
    nb_price_detail_modal();
    
    // Mini modal de confirmación para descarga
    nb_download_confirmation_modal();
}

function nb_price_detail_modal()
{
    echo '<div id="price-detail-modal" class="hidden fixed inset-0 bg-gray-800/70 backdrop-blur-sm flex justify-center items-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">';
    
    // Header del modal
    echo '<div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">';
    echo '<h3 class="text-lg font-semibold text-gray-900" id="price-modal-title">Detalle de Precios</h3>';
    echo '<button type="button" id="close-price-modal" class="text-gray-400 hover:text-gray-600">×</button>';
    echo '</div>';

    // Contenido del modal
    echo '<div class="p-6" id="price-detail-content">';
    echo '<div class="animate-pulse">';
    echo '<div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>';
    echo '<div class="h-4 bg-gray-200 rounded w-1/2 mb-2"></div>';
    echo '<div class="h-4 bg-gray-200 rounded w-2/3"></div>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
    echo '</div>';
}

function nb_download_confirmation_modal()
{
    echo '<div id="download-confirmation-modal" class="hidden fixed inset-0 bg-gray-800/70 backdrop-blur-sm flex justify-center items-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">';
    
    // Header del modal
    echo '<div class="px-6 py-4 border-b border-gray-200">';
    echo '<div class="flex items-center">';
    echo '<div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">';
    echo '<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-4-4m4 4l4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
    echo '</svg>';
    echo '</div>';
    echo '<h3 class="text-lg font-medium text-gray-900">Confirmar Descarga</h3>';
    echo '</div>';
    echo '</div>';

    // Contenido del modal
    echo '<div class="px-6 py-4">';
    echo '<p class="text-sm text-gray-600 mb-4">¿Estás seguro de que deseas descargar el archivo JSON?</p>';
    echo '<div class="text-xs text-gray-500 bg-gray-50 p-3 rounded-lg">';
    echo '<strong>Archivo:</strong> <span id="download-filename">-</span><br>';
    echo '<strong>Tamaño:</strong> <span id="download-filesize">-</span>';
    echo '</div>';
    echo '</div>';

    // Footer con botones
    echo '<div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3 rounded-b-lg">';
    echo '<button type="button" id="cancel-download" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">';
    echo 'Cancelar';
    echo '</button>';
    echo '<button type="button" id="confirm-download" class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">';
    echo '<svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-4-4m4 4l4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
    echo '</svg>';
    echo 'Descargar';
    echo '</button>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
}

function nb_logs_javascript()
{
    $nonce = wp_create_nonce('nb_get_log_data');
    echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('log-details-modal');
    const closeModal = document.getElementById('close-modal');
    const searchInput = document.getElementById('search-products');
    const priceModal = document.getElementById('price-detail-modal');
    const closePriceModal = document.getElementById('close-price-modal');
    
    let allProducts = [];

    // Abrir modal
    document.querySelectorAll('.log-row').forEach(element => {
        element.addEventListener('click', function(e) {
            // Solo abrir si no se hizo clic en el dropdown
            if (!e.target.closest('.relative')) {
                e.preventDefault();
                const filename = this.dataset.filename;
                if (filename) openLogModal(filename);
            }
        });
    });

    // Botones Ver Detalles del dropdown
    document.querySelectorAll('.view-log-btn').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const filename = this.dataset.filename;
            if (filename) {
                // Cerrar dropdown
                this.closest('.dropdown-menu').classList.add('hidden');
                openLogModal(filename);
            }
        });
    });

    // Cerrar modal
    closeModal.addEventListener('click', function() {
        modal.classList.add('hidden');
    });

    // Búsqueda
    searchInput.addEventListener('input', function() {
        filterProducts(this.value.toLowerCase().trim());
    });

    // Cerrar modal de precios
    closePriceModal.addEventListener('click', function() {
        priceModal.classList.add('hidden');
    });

    // Cerrar modal de precios al hacer clic fuera
    priceModal.addEventListener('click', function(e) {
        if (e.target === this) {
            priceModal.classList.add('hidden');
        }
    });

    function openLogModal(filename) {
        modal.classList.remove('hidden');
        document.getElementById('modal-title').textContent = 'Cargando: ' + filename;
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=nb_get_log_data&filename=' + encodeURIComponent(filename) + '&_wpnonce={$nonce}'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLogData(data.data, filename);
            } else {
                showError('Error al cargar el log');
            }
        })
        .catch(() => showError('Error de conexión'));
    }

    function displayLogData(logData, filename) {
        document.getElementById('modal-title').textContent = 'Log: ' + filename;
        
        const metadata = logData.metadata || {};
        const stats = metadata.sync_stats || {};
        
        document.getElementById('log-metadata').innerHTML = 
            '<div class=\"space-y-3\">' +
                '<div>' +
                    '<h4 class=\"font-medium text-xs mb-2 text-gray-700\">Información</h4>' +
                    '<div class=\"text-xs space-y-1 text-gray-600\">' +
                        '<div class=\"flex items-center space-x-1 truncate\" title=\"' + (metadata.timestamp || 'N/A') + '\">' +
                            '<svg class=\"w-3 h-3 text-gray-500 flex-shrink-0\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">' +
                                '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z\"></path>' +
                            '</svg>' +
                            '<span class=\"truncate\">' + (metadata.timestamp ? metadata.timestamp.split(' ')[0] : 'N/A') + '</span>' +
                        '</div>' +
                        '<div class=\"flex items-center space-x-1 truncate\">' +
                            '<svg class=\"w-3 h-3 text-gray-500 flex-shrink-0\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">' +
                                '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z\"></path>' +
                            '</svg>' +
                            '<span class=\"truncate\">' + (metadata.user || 'N/A') + '</span>' +
                        '</div>' +
                        '<div class=\"flex items-center space-x-1 truncate\">' +
                            '<svg class=\"w-3 h-3 text-gray-500 flex-shrink-0\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">' +
                                '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M13 10V3L4 14h7v7l9-11h-7z\"></path>' +
                            '</svg>' +
                            '<span class=\"truncate\">' + (metadata.sync_type || 'N/A') + '</span>' +
                        '</div>' +
                        '<div class=\"flex items-center space-x-1 truncate\">' +
                            '<svg class=\"w-3 h-3 text-gray-500 flex-shrink-0\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">' +
                                '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4\"></path>' +
                            '</svg>' +
                            '<span class=\"truncate\">' + (metadata.total_products || 0) + '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div>' +
                    '<h4 class=\"font-medium text-xs mb-2 text-gray-700\">Estadísticas</h4>' +
                    '<div class=\"space-y-1 text-xs\">' +
                        '<div class=\"flex justify-between items-center bg-green-50 px-2 py-1 rounded\">' +
                            '<svg class=\"w-3 h-3 text-green-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">' +
                                '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 6v6m0 0v6m0-6h6m-6 0H6\"></path>' +
                            '</svg> <span>Creados</span>' +
                            '<span class=\"font-medium text-green-800\">' + (stats.created || 0) + '</span>' +
                        '</div>' +
                        '<div class=\"flex justify-between items-center bg-blue-50 px-2 py-1 rounded\">' +
                            '<svg class=\"w-3 h-3 text-blue-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">' +
                                '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15\"></path>' +
                            '</svg> <span>Actualizados</span>' +
                            '<span class=\"font-medium text-blue-800\">' + (stats.updated || 0) + '</span>' +
                        '</div>' +
                        '<div class=\"flex justify-between items-center bg-amber-50 px-2 py-1 rounded\">' +
                            '<svg class=\"w-3 h-3 text-amber-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">' +
                                '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16\"></path>' +
                            '</svg> <span>Eliminados</span>' +
                            '<span class=\"font-medium text-amber-800\">' + (stats.deleted || 0) + '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        allProducts = logData.api_data || [];
        // Filtrar y mostrar solo productos con stock > 0
        const productsWithStock = allProducts.filter(p => (p.amountStock || 0) > 0);
        displayProducts(productsWithStock);
        updateSearchCount(productsWithStock.length, productsWithStock.length);
    }

    function getStockStatus(stock) {
        if (stock > 10) {
            return {
                class: 'bg-green-100 text-green-700 border border-green-200',
                dotClass: 'bg-green-500'
            };
        } else if (stock > 0) {
            return {
                class: 'bg-yellow-100 text-yellow-700 border border-yellow-200',
                dotClass: 'bg-yellow-500'
            };
        } else {
            return {
                class: 'bg-red-100 text-red-700 border border-red-200',
                dotClass: 'bg-red-500'
            };
        }
    }


    function displayProducts(products) {
        const container = document.getElementById('products-container');
        
        // Filtrar solo productos con stock > 0
        const productsWithStock = products.filter(product => (product.amountStock || 0) > 0);
        
        if (productsWithStock.length === 0) {
            container.innerHTML = '<div class=\"text-center py-16\"><div class=\"w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4\"><svg class=\"w-8 h-8 text-gray-400\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707.293l-2.414-2.414A1 1 0 006.586 13H4\"></path></svg></div><h3 class=\"text-lg font-medium text-gray-900 mb-2\">No hay productos con stock disponible</h3><p class=\"text-gray-500\">Todos los productos están sin stock o no coinciden con tu búsqueda</p></div>';
            return;
        }

        // Header estilo tabla compacta mejorado
        let html = '<div class=\"bg-gradient-to-r from-slate-50 to-gray-50 border-b border-slate-200 px-4 py-3 mb-1 sticky top-0 z-10\">';
        html += '<div class=\"grid grid-cols-12 gap-3 text-xs font-semibold text-slate-600 uppercase tracking-wider\">';
        html += '<div class=\"col-span-1 text-center\">#</div>';
        html += '<div class=\"col-span-5\">Producto</div>'; // Más espacio para el nombre
        html += '<div class=\"col-span-1 text-center\">Stock</div>';
        html += '<div class=\"col-span-2 text-center\">Precio ARS</div>';
        html += '<div class=\"col-span-2 text-center\">Precio USD</div>';
        html += '<div class=\"col-span-1 text-center\">Detalles</div>'; // Mejor descripción
        html += '</div>';
        html += '</div>';

        productsWithStock.forEach((product, index) => {
            const stockInfo = getStockStatus(product.amountStock);
            const priceARS = product.price?.finalPriceWithUtility ? (product.price.finalPriceWithUtility * product.cotizacion).toFixed(2) : 0;
            const priceUSD = product.price?.finalPriceWithUtility ? product.price.finalPriceWithUtility.toFixed(2) : 0;
            
            // Fila moderna con hover mejorado
            html += '<div class=\"group hover:bg-slate-50 border-b border-slate-100 px-4 py-3 transition-all duration-200\">';
            html += '<div class=\"grid grid-cols-12 gap-3 items-center\">';
            
            // Columna 1: Ranking
            html += '<div class=\"col-span-1 text-center\">';
            html += '<div class=\"w-7 h-7 bg-slate-100 rounded-lg flex items-center justify-center group-hover:bg-slate-200 transition-colors\">';
            html += '<span class=\"text-xs text-slate-600 font-mono font-semibold\">' + (index + 1) + '</span>';
            html += '</div>';
            html += '</div>';
            
            // Columna 2-6: Producto (más espacio para el nombre)
            html += '<div class=\"col-span-5\">';
            html += '<div class=\"flex items-center space-x-3\">';
            if (product.mainImageExp) {
                html += '<img src=\"' + product.mainImageExp + '\" alt=\"' + (product.title || '') + '\" class=\"w-10 h-10 object-cover rounded-lg border border-slate-200 group-hover:scale-105 transition-transform duration-200 shadow-sm\">';
            } else {
                html += '<div class=\"w-10 h-10 bg-gradient-to-br from-slate-100 to-slate-200 rounded-lg border border-slate-200 flex items-center justify-center\">';
                html += '<svg class=\"w-5 h-5 text-slate-400\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\"></path></svg>';
                html += '</div>';
            }
            html += '<div class=\"min-w-0 flex-1\">';
            html += '<div class=\"font-semibold text-slate-900 text-sm leading-tight group-hover:text-blue-600 transition-colors\" title=\"' + (product.title || 'Sin título') + '\">' + highlightSearchTerm(product.title || 'Sin título', searchInput.value) + '</div>';
            html += '<div class=\"flex items-center space-x-2 mt-1\">';
            if (product.brand) {
                html += '<span class=\"inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100\">' + product.brand + '</span>';
            }
            html += '<span class=\"text-xs text-slate-500 truncate\">' + (product.categoryDescriptionUser || product.category || 'Sin categoría') + '</span>';
            if (product.sku) {
                html += '<span class=\"text-xs text-slate-400 font-mono\">SKU: ' + product.sku + '</span>';
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // Columna 6: Stock
            html += '<div class=\"col-span-1 text-center\">';
            html += '<div class=\"inline-flex items-center justify-center w-16 py-1 rounded-full text-xs font-medium ' + stockInfo.class + '\">';
            html += '<span class=\"w-2 h-2 rounded-full ' + stockInfo.dotClass + ' mr-1.5\"></span>';
            html += (product.amountStock || 0);
            html += '</div>';
            html += '</div>';
            
            // Columna 7-8: Precio ARS
            html += '<div class=\"col-span-2 text-center\">';
            html += '<div class=\"font-bold text-emerald-600 text-sm\">$' + priceARS + '</div>';
            // html += '<div class=\"text-xs text-slate-500 font-medium\">ARS</div>';
            html += '</div>';
            
            // Columna 9-10: Precio USD
            html += '<div class=\"col-span-2 text-center\">';
            html += '<div class=\"font-semibold text-slate-700 text-sm\">$' + priceUSD + '</div>';
            // html += '<div class=\"text-xs text-slate-500 font-medium\">USD</div>';
            html += '</div>';
            
            // Columna 11: Botón de acción con icono de precio
            html += '<div class=\"col-span-1 text-center\">';
            html += '<button class=\"inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 text-blue-600 hover:text-blue-700 border border-blue-200 hover:border-blue-300 transition-all duration-200 price-detail-btn group-hover:scale-110 shadow-sm hover:shadow-md\" data-product-id=\"' + product.id + '\" title=\"Ver detalle de precios y utilidad\">';
            html += '<svg class=\"w-4 h-4\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z\"></path></svg>';
            html += '</button>';
            html += '</div>';
            
            html += '</div>'; // fin grid
            html += '</div>'; // fin fila
        });

        container.innerHTML = html;
        
        // Agregar event listeners para botones de detalle de precios
        document.querySelectorAll('.price-detail-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const productId = this.dataset.productId;
                showPriceDetail(productId);
            });
        });
    }

    function filterProducts(searchTerm) {
        // Primero filtrar por stock > 0, luego por término de búsqueda
        const productsWithStock = allProducts.filter(p => (p.amountStock || 0) > 0);
        const filtered = searchTerm ? 
            productsWithStock.filter(p => (p.title || '').toLowerCase().includes(searchTerm)) : productsWithStock;
        
        displayProducts(filtered);
        updateSearchCount(filtered.length, productsWithStock.length);
    }

    function updateSearchCount(filtered, total) {
        const searchTerm = searchInput.value.trim();
        let message = '';
        
        if (searchTerm) {
            if (filtered === 0) {
                message = 'No se encontraron productos que coincidan con \"' + searchTerm + '\"';
            } else if (filtered === 1) {
                message = 'Se encontró 1 producto de ' + total + ' en total';
            } else {
                message = 'Mostrando ' + filtered + ' productos de ' + total + ' en total';
            }
        } else {
            message = total + ' productos en total';
        }
        
        document.getElementById('search-results-count').textContent = message;
    }

    function highlightSearchTerm(text, searchTerm) {
        if (!searchTerm || !text) return text;
        try {
            const regex = new RegExp('(' + searchTerm + ')', 'gi');
            return text.replace(regex, '<mark class=\"bg-yellow-200\">$1</mark>');
        } catch(e) {
            return text;
        }
    }

    function showPriceDetail(productId) {
        const product = allProducts.find(p => p.id == productId);
        if (!product || !product.price) {
            return;
        }

        const price = product.price;
        const cotizacion = product.cotizacion || 1;
        const utility = product.utility || 0;
        
        // El campo utility ya representa el porcentaje directamente
        const utilityPercentage = utility.toFixed(1);
        
        document.getElementById('price-modal-title').textContent = 'Detalle de Precios - ' + (product.title || 'Producto');
        
        let priceHtml = '<div class=\"space-y-4\">';
        
        // Precio base
        priceHtml += '<div class=\"bg-gray-50 p-4 rounded-lg\">';
        priceHtml += '<h4 class=\"font-semibold text-gray-900 mb-3 flex items-center\">';
        priceHtml += '<svg class=\"w-5 h-5 mr-2 text-gray-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">';
        priceHtml += '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z\"></path>';
        priceHtml += '</svg>';
        priceHtml += 'Precio Base</h4>';
        priceHtml += '<div class=\"grid grid-cols-2 gap-3 text-sm\">';
        priceHtml += '<div><span class=\"text-gray-600\">Valor:</span> <span class=\"font-medium\">USD $' + (price.value || 0).toFixed(2) + '</span></div>';
        priceHtml += '<div><span class=\"text-gray-600\">IVA:</span> <span class=\"font-medium\">' + (price.iva || 0) + '%</span></div>';
        priceHtml += '<div><span class=\"text-gray-600\">Impuesto interno:</span> <span class=\"font-medium\">$' + (price.internalTax || 0).toFixed(2) + '</span></div>';
        priceHtml += '<div><span class=\"text-gray-600\">Costo extra:</span> <span class=\"font-medium\">$' + (price.ncostoextra || 0).toFixed(2) + '</span></div>';
        priceHtml += '</div>';
        priceHtml += '</div>';

        // Utilidad aplicada
        priceHtml += '<div class=\"bg-yellow-50 p-4 rounded-lg\">';
        priceHtml += '<h4 class=\"font-semibold text-gray-900 mb-3 flex items-center\">';
        priceHtml += '<svg class=\"w-5 h-5 mr-2 text-yellow-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">';
        priceHtml += '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M13 7h8m0 0v8m0-8l-8 8-4-4-6 6\"></path>';
        priceHtml += '</svg>';
        priceHtml += 'Utilidad Aplicada</h4>';
        priceHtml += '<div class=\"grid grid-cols-2 gap-3 text-sm\">';
        priceHtml += '<div><span class=\"text-gray-600\">Porcentaje:</span> <span class=\"font-bold text-yellow-600\">' + utilityPercentage + '%</span></div>';
        priceHtml += '<div><span class=\"text-gray-600\">Multiplicador:</span> <span class=\"font-medium\">' + (1 + utility/100).toFixed(3) + 'x</span></div>';
        priceHtml += '</div>';
        priceHtml += '</div>';

        // Precios calculados
        priceHtml += '<div class=\"bg-blue-50 p-4 rounded-lg\">';
        priceHtml += '<h4 class=\"font-semibold text-gray-900 mb-3 flex items-center\">';
        priceHtml += '<svg class=\"w-5 h-5 mr-2 text-blue-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">';
        priceHtml += '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z\"></path>';
        priceHtml += '</svg>';
        priceHtml += 'Precios Calculados (USD)</h4>';
        priceHtml += '<div class=\"space-y-2 text-sm\">';
        priceHtml += '<div class=\"flex justify-between\"><span class=\"text-gray-600\">Precio final (base + IVA + impuestos):</span> <span class=\"font-medium\">$' + (price.finalPrice || 0).toFixed(2) + '</span></div>';
        priceHtml += '<div class=\"flex justify-between border-t pt-2\"><span class=\"text-gray-600\">Con utilidad (+' + utilityPercentage + '%):</span> <span class=\"font-semibold text-blue-600\">$' + (price.finalPriceWithUtility || 0).toFixed(2) + '</span></div>';
        if (price.percepcion) {
            priceHtml += '<div class=\"flex justify-between\"><span class=\"text-gray-600\">Percepción:</span> <span class=\"font-medium\">$' + price.percepcion.toFixed(2) + '</span></div>';
        }
        priceHtml += '</div>';
        priceHtml += '</div>';

        // Precios en pesos argentinos
        priceHtml += '<div class=\"bg-green-50 p-4 rounded-lg\">';
        priceHtml += '<h4 class=\"font-semibold text-gray-900 mb-3 flex items-center\">';
        priceHtml += '<svg class=\"w-5 h-5 mr-2 text-green-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">';
        priceHtml += '<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v2a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z\"></path>';
        priceHtml += '</svg>';
        priceHtml += 'Precios en ARS</h4>';
        priceHtml += '<div class=\"space-y-2 text-sm\">';
        priceHtml += '<div class=\"flex justify-between\"><span class=\"text-gray-600\">Cotización USD:</span> <span class=\"font-medium\">$' + cotizacion + '</span></div>';
        priceHtml += '<div class=\"flex justify-between\"><span class=\"text-gray-600\">Precio final:</span> <span class=\"font-medium\">$' + ((price.finalPrice || 0) * cotizacion).toFixed(2) + '</span></div>';
        priceHtml += '<div class=\"flex justify-between border-t pt-2\"><span class=\"text-gray-900 font-semibold\">Con utilidad (+' + utilityPercentage + '%):</span> <span class=\"font-bold text-green-600 text-lg\">$' + ((price.finalPriceWithUtility || 0) * cotizacion).toFixed(2) + '</span></div>';
        priceHtml += '</div>';
        priceHtml += '</div>';

        priceHtml += '</div>';
        
        document.getElementById('price-detail-content').innerHTML = priceHtml;
        priceModal.classList.remove('hidden');
    }

    function showError(message) {
        document.getElementById('log-metadata').innerHTML = '<div class=\"text-red-600\">' + message + '</div>';
        document.getElementById('products-container').innerHTML = '<div class=\"text-red-600\">' + message + '</div>';
    }

    // Manejo del modal de descarga
    const downloadModal = document.getElementById('download-confirmation-modal');
    const cancelDownload = document.getElementById('cancel-download');
    const confirmDownload = document.getElementById('confirm-download');
    let currentDownloadFilename = '';

    // Manejo de dropdowns
    document.querySelectorAll('.dropdown-toggle').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Cerrar otros dropdowns abiertos
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu !== this.nextElementSibling) {
                    menu.classList.add('hidden');
                }
            });
            
            // Toggle del dropdown actual
            const menu = this.nextElementSibling;
            const isHidden = menu.classList.contains('hidden');
            
            if (isHidden) {
                // Calcular posición del botón
                const rect = this.getBoundingClientRect();
                const menuWidth = 160; // w-40 = 160px
                
                // Posicionar el menú
                menu.style.top = (rect.bottom + 4) + 'px';
                menu.style.right = (window.innerWidth - rect.right) + 'px';
                menu.style.left = 'auto';
                
                // Mostrar menú
                menu.classList.remove('hidden');
            } else {
                // Ocultar menú
                menu.classList.add('hidden');
            }
        });
    });

    // Cerrar dropdowns al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.relative')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });

    // Cerrar dropdowns al hacer scroll
    window.addEventListener('scroll', function() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.add('hidden');
        });
    });

    // Cerrar dropdowns al redimensionar ventana
    window.addEventListener('resize', function() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.add('hidden');
        });
    });

    // Botones de descarga
    document.querySelectorAll('.download-log-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const filename = this.dataset.filename;
            const row = this.closest('tr');
            const sizeElement = row.querySelector('td:nth-child(3)');
            const size = sizeElement ? sizeElement.textContent.trim() : 'Desconocido';
            
            currentDownloadFilename = filename;
            document.getElementById('download-filename').textContent = filename;
            document.getElementById('download-filesize').textContent = size;
            
            // Cerrar dropdown
            this.closest('.dropdown-menu').classList.add('hidden');
            
            downloadModal.classList.remove('hidden');
        });
    });

    // Cancelar descarga
    cancelDownload.addEventListener('click', function() {
        downloadModal.classList.add('hidden');
        currentDownloadFilename = '';
    });

    // Confirmar descarga
    confirmDownload.addEventListener('click', function() {
        if (currentDownloadFilename) {
            // Crear enlace de descarga
            const downloadUrl = '" . admin_url('admin-ajax.php') . "?action=nb_download_log&filename=' + encodeURIComponent(currentDownloadFilename) + '&_wpnonce=" . wp_create_nonce('nb_download_log') . "';
            
            // Crear elemento temporal para descarga
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = currentDownloadFilename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Cerrar modal
            downloadModal.classList.add('hidden');
            currentDownloadFilename = '';
        }
    });

    // Cerrar modal al hacer clic fuera
    downloadModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
            currentDownloadFilename = '';
        }
    });

    // Botón de limpieza de logs
    const cleanupBtn = document.getElementById('cleanup-logs-btn');
    if (cleanupBtn) {
        cleanupBtn.addEventListener('click', function() {
            if (confirm('¿Estás seguro de que deseas eliminar los logs antiguos?\\n\\nSe mantendrán solo los 15 logs más recientes y se eliminarán el resto.')) {
                // Deshabilitar botón y mostrar loading
                this.disabled = true;
                this.innerHTML = '<svg class=\"animate-spin w-4 h-4 mr-1\" fill=\"none\" viewBox=\"0 0 24 24\"><circle class=\"opacity-25\" cx=\"12\" cy=\"12\" r=\"10\" stroke=\"currentColor\" stroke-width=\"4\"></circle><path class=\"opacity-75\" fill=\"currentColor\" d=\"M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z\"></path></svg>Limpiando...';
                
                // Hacer petición AJAX
                fetch('" . admin_url('admin-ajax.php') . "', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=nb_cleanup_logs&_wpnonce=" . wp_create_nonce('nb_cleanup_logs') . "'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.data.message);
                        // Recargar página para mostrar cambios
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.data);
                        // Restaurar botón
                        this.disabled = false;
                        this.innerHTML = '<svg class=\"w-4 h-4 mr-1\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16\"></path></svg>Limpiar Logs Antiguos';
                    }
                })
                .catch(error => {
                    alert('Error de conexión: ' + error);
                    // Restaurar botón
                    this.disabled = false;
                    this.innerHTML = '<svg class=\"w-4 h-4 mr-1\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16\"></path></svg>Limpiar Logs Antiguos';
                });
            }
        });
    }
});
</script>";
}

/**
 * Endpoint AJAX para obtener datos de un log específico
 */
function nb_get_log_data()
{
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
        return;
    }

    // Verificar nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'nb_get_log_data')) {
        wp_send_json_error('Nonce inválido');
        return;
    }

    // Obtener filename
    $filename = sanitize_file_name($_POST['filename']);
    if (empty($filename)) {
        wp_send_json_error('Nombre de archivo requerido');
        return;
    }

    // Leer log
    $log_data = NB_Logs_Manager::read_log($filename);
    if ($log_data === false) {
        wp_send_json_error('No se pudo leer el archivo de log');
        return;
    }

    wp_send_json_success($log_data);
}

/**
 * Endpoint AJAX para descargar un archivo de log
 */
function nb_download_log()
{
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('Permisos insuficientes');
        return;
    }

    // Verificar nonce
    if (!wp_verify_nonce($_GET['_wpnonce'], 'nb_download_log')) {
        wp_die('Nonce inválido');
        return;
    }

    // Obtener filename
    $filename = sanitize_file_name($_GET['filename']);
    if (empty($filename)) {
        wp_die('Nombre de archivo requerido');
        return;
    }

    // Verificar que el archivo existe
    $logs_dir = plugin_dir_path(__FILE__) . '../logs-sync-nb/';
    $file_path = $logs_dir . $filename;
    
    if (!file_exists($file_path)) {
        wp_die('Archivo no encontrado');
        return;
    }

    // Configurar headers para descarga
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Leer y enviar el archivo
    readfile($file_path);
    exit;
}

/**
 * Endpoint AJAX para limpiar logs excedentes
 */
function nb_cleanup_logs()
{
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
        return;
    }

    // Verificar nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'nb_cleanup_logs')) {
        wp_send_json_error('Nonce inválido');
        return;
    }

    // Ejecutar limpieza manteniendo solo los últimos 15 logs
    $deleted_count = NB_Logs_Manager::cleanup_excess_logs(15);
    
    if ($deleted_count > 0) {
        wp_send_json_success([
            'message' => "Se eliminaron {$deleted_count} logs antiguos. Se mantuvieron los 15 más recientes.",
            'deleted_count' => $deleted_count
        ]);
    } else {
        wp_send_json_success([
            'message' => 'No hay logs para eliminar. Ya tienes 15 o menos logs.',
            'deleted_count' => 0
        ]);
    }
}
