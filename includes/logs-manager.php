<?php

/**
 * Gestor de logs JSON para sincronizaciones NewBytes
 * Maneja la creación, almacenamiento y lectura de logs de sincronización
 */

class NB_Logs_Manager
{
    private static $logs_dir;
    
    public function __construct()
    {
        self::$logs_dir = plugin_dir_path(__FILE__) . '../logs-sync-nb/';
        
        // Asegurar que el directorio existe
        if (!file_exists(self::$logs_dir)) {
            wp_mkdir_p(self::$logs_dir);
        }
    }
    
    /**
     * Crear un nuevo archivo de log JSON
     * 
     * @param array $api_data Datos obtenidos de la API
     * @param array $sync_stats Estadísticas de la sincronización
     * @param string $sync_type Tipo de sincronización (manual, auto, description)
     * @return string|false Ruta del archivo creado o false en caso de error
     */
    public static function create_log($api_data, $sync_stats = [], $sync_type = 'auto')
    {
        try {
            // Obtener información del usuario actual
            $current_user = wp_get_current_user();
            $username = $current_user->user_login ?: 'system';
            
            // Crear nombre del archivo con formato: YYYY-MM-DD_HH-mm_[usuario].json
            $timestamp = current_time('Y-m-d_H-i');
            $filename = $timestamp . '_' . sanitize_file_name($username) . '.json';
            $filepath = self::$logs_dir . $filename;
            
            // Preparar datos del log
            $log_data = [
                'metadata' => [
                    'timestamp' => current_time('Y-m-d H:i:s'),
                    'user' => $username,
                    'user_id' => get_current_user_id(),
                    'sync_type' => $sync_type,
                    'plugin_version' => VERSION_NB,
                    'wordpress_version' => get_bloginfo('version'),
                    'total_products' => count($api_data),
                    'sync_stats' => $sync_stats
                ],
                'configuration' => [
                    'nb_prefix' => get_option('nb_prefix'),
                    'nb_sync_no_iva' => get_option('nb_sync_no_iva'),
                    'nb_sync_usd' => get_option('nb_sync_usd'),
                    'nb_sync_interval' => get_option('nb_sync_interval'),
                    'nb_description' => get_option('nb_description')
                ],
                'api_data' => $api_data
            ];
            
            // Escribir archivo JSON
            $json_content = json_encode($log_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($filepath, $json_content) !== false) {
                error_log("Log JSON creado: " . $filename, 3, self::$logs_dir . 'debug-logs.txt');
                
                // Limpiar logs antiguos manteniendo solo los últimos 15
                self::cleanup_excess_logs(15);
                
                return $filepath;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Error creando log JSON: ' . $e->getMessage(), 3, self::$logs_dir . 'debug-logs.txt');
            return false;
        }
    }
    
    /**
     * Obtener lista de todos los archivos de log
     * 
     * @return array Lista de archivos con información
     */
    public static function get_logs_list()
    {
        $logs = [];
        
        if (!is_dir(self::$logs_dir)) {
            return $logs;
        }
        
        $files = glob(self::$logs_dir . '*.json');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $filesize = filesize($file);
            $filemtime = filemtime($file);
            
            // Extraer información del nombre del archivo
            if (preg_match('/^(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2})_(.+)\.json$/', $filename, $matches)) {
                $date = $matches[1];
                $time = str_replace('-', ':', $matches[2]);
                $user = $matches[3];
                
                $logs[] = [
                    'filename' => $filename,
                    'filepath' => $file,
                    'date' => $date,
                    'time' => $time,
                    'user' => $user,
                    'size' => $filesize,
                    'modified' => $filemtime,
                    'formatted_date' => date('d/m/Y', strtotime($date)),
                    'formatted_time' => $time,
                    'formatted_size' => self::format_bytes($filesize)
                ];
            }
        }
        
        // Ordenar por fecha/hora descendente (más reciente primero)
        usort($logs, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $logs;
    }
    
    /**
     * Leer contenido de un archivo de log específico
     * 
     * @param string $filename Nombre del archivo
     * @return array|false Contenido del log o false en caso de error
     */
    public static function read_log($filename)
    {
        $filepath = self::$logs_dir . sanitize_file_name($filename);
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            return false;
        }
        
        return json_decode($content, true);
    }
    
    /**
     * Buscar productos en un log por nombre
     * 
     * @param array $log_data Datos del log
     * @param string $search_term Término de búsqueda
     * @return array Productos encontrados
     */
    public static function search_products_in_log($log_data, $search_term)
    {
        $results = [];
        
        if (!isset($log_data['api_data']) || empty($search_term)) {
            return $results;
        }
        
        $search_term = strtolower(trim($search_term));
        
        foreach ($log_data['api_data'] as $product) {
            $product_name = isset($product['title']) ? strtolower($product['title']) : '';
            $product_sku = isset($product['sku']) ? strtolower($product['sku']) : '';
            $product_category = isset($product['category']) ? strtolower($product['category']) : '';
            
            if (strpos($product_name, $search_term) !== false || 
                strpos($product_sku, $search_term) !== false ||
                strpos($product_category, $search_term) !== false) {
                
                $results[] = $product;
            }
        }
        
        return $results;
    }
    
    /**
     * Limpiar logs antiguos (opcional)
     * 
     * @param int $days_to_keep Días a mantener (por defecto 30)
     * @return int Número de archivos eliminados
     */
    public static function cleanup_old_logs($days_to_keep = 30)
    {
        $deleted_count = 0;
        $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
        
        $files = glob(self::$logs_dir . '*.json');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Limpiar logs excedentes manteniendo solo los N más recientes
     * 
     * @param int $max_logs Número máximo de logs a mantener (por defecto 15)
     * @return int Número de archivos eliminados
     */
    public static function cleanup_excess_logs($max_logs = 15)
    {
        $deleted_count = 0;
        
        if (!is_dir(self::$logs_dir)) {
            return $deleted_count;
        }
        
        // Obtener todos los archivos JSON
        $files = glob(self::$logs_dir . '*.json');
        
        if (count($files) <= $max_logs) {
            return $deleted_count; // No hay nada que limpiar
        }
        
        // Crear array con información de archivos
        $file_info = [];
        foreach ($files as $file) {
            $file_info[] = [
                'path' => $file,
                'filename' => basename($file),
                'mtime' => filemtime($file)
            ];
        }
        
        // Ordenar por fecha de modificación descendente (más reciente primero)
        usort($file_info, function($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });
        
        // Eliminar los archivos excedentes (mantener solo los primeros $max_logs)
        $files_to_delete = array_slice($file_info, $max_logs);
        
        foreach ($files_to_delete as $file) {
            if (unlink($file['path'])) {
                $deleted_count++;
                error_log("Log eliminado (exceso): " . $file['filename'], 3, self::$logs_dir . 'debug-logs.txt');
            }
        }
        
        if ($deleted_count > 0) {
            error_log("Limpieza automática: {$deleted_count} logs eliminados. Mantenidos: {$max_logs} más recientes.", 3, self::$logs_dir . 'debug-logs.txt');
        }
        
        return $deleted_count;
    }
    
    /**
     * Formatear bytes a formato legible
     * 
     * @param int $bytes
     * @return string
     */
    private static function format_bytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Obtener estadísticas de logs
     * 
     * @return array
     */
    public static function get_logs_stats()
    {
        $logs = self::get_logs_list();
        $total_size = 0;
        
        foreach ($logs as $log) {
            $total_size += $log['size'];
        }
        
        return [
            'total_logs' => count($logs),
            'total_size' => $total_size,
            'formatted_size' => self::format_bytes($total_size),
            'oldest_log' => !empty($logs) ? end($logs)['formatted_date'] : 'N/A',
            'newest_log' => !empty($logs) ? $logs[0]['formatted_date'] : 'N/A'
        ];
    }
}

// Inicializar el gestor de logs
new NB_Logs_Manager();
