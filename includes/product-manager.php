<?php

/**
 * Gestor de productos JSON para sincronizaciones NewBytes
 * Maneja la generación, almacenamiento y lectura de archivos JSON de productos
 */

class NB_Product_Manager
{
    private static $products_dir;
    
    /**
     * Inicializar el directorio de productos y asegurar que existe con permisos correctos
     */
    private static function init_products_dir()
    {
        if (empty(self::$products_dir)) {
            self::$products_dir = plugin_dir_path(__FILE__) . '../nb-products/';
        }
        
        // Asegurar que el directorio existe con permisos de escritura
        if (!file_exists(self::$products_dir)) {
            // Crear directorio con permisos 0755 (lectura/escritura para owner, lectura para otros)
            if (!mkdir(self::$products_dir, 0755, true)) {
                // Fallback a wp_mkdir_p si mkdir falla
                wp_mkdir_p(self::$products_dir);
            }
        }
        
        // Asegurar permisos de escritura (0755 o 0775 según configuración de WP)
        if (is_dir(self::$products_dir) && !is_writable(self::$products_dir)) {
            @chmod(self::$products_dir, 0755);
            // Si aún no es escribible, intentar con permisos más permisivos
            if (!is_writable(self::$products_dir)) {
                @chmod(self::$products_dir, 0775);
            }
        }
        
        // Crear archivo index.php vacío para protección
        $index_file = self::$products_dir . 'index.php';
        if (!file_exists($index_file) && is_writable(self::$products_dir)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }
    
    /**
     * Genera un archivo JSON con los productos crudos de la API
     * 
     * @return array Resultado con 'success', 'filepath' o 'error'
     */
    public static function generate_products_json()
    {
        self::init_products_dir();
        
        try {
            // Obtener token de autenticación
            $token = nb_get_token();
            if (!$token) {
                return array(
                    'success' => false,
                    'error' => 'No fue posible obtener el token de autenticación.'
                );
            }
            
            // Llamar a la API
            $url = API_URL_NB . '/';
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30,
                'blocking' => true,
            );
            
            $response = wp_remote_get($url, $args);
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => 'Error en la solicitud a la API: ' . $response->get_error_message()
                );
            }
            
            $body = wp_remote_retrieve_body($response);
            $json_data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return array(
                    'success' => false,
                    'error' => 'Error al decodificar JSON de la API: ' . json_last_error_msg()
                );
            }
            
            // Crear nombre del archivo con formato: nb-products-YYYY-MM-DD_HH-mm.json
            $timestamp = current_time('Y-m-d_H-i');
            $filename = 'nb-products-' . $timestamp . '.json';
            $filepath = self::$products_dir . $filename;
            
            // Guardar JSON crudo (sin modificaciones, sin estadísticas, sin info de WP)
            $json_content = json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($filepath, $json_content) === false) {
                return array(
                    'success' => false,
                    'error' => 'Error al guardar el archivo JSON.'
                );
            }
            
            // Limpiar archivos antiguos (mantener máximo 10)
            self::cleanup_old_products_json(10);
            
            nb_log('Archivo JSON de productos generado: ' . $filename, 'info', array(
                'total_products' => count($json_data)
            ));
            
            return array(
                'success' => true,
                'filepath' => $filepath,
                'filename' => $filename,
                'total_products' => count($json_data)
            );
            
        } catch (Exception $e) {
            nb_log('Error generando JSON de productos: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => 'Excepción: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Obtiene el archivo JSON de productos más reciente
     * 
     * @return array|false Datos del archivo o false si no existe
     */
    public static function get_latest_products_json()
    {
        self::init_products_dir();
        
        $files = glob(self::$products_dir . 'nb-products-*.json');
        
        if (empty($files)) {
            return false;
        }
        
        // Ordenar por fecha de modificación descendente
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latest_file = $files[0];
        
        return array(
            'filepath' => $latest_file,
            'filename' => basename($latest_file),
            'modified' => filemtime($latest_file),
            'size' => filesize($latest_file)
        );
    }
    
    /**
     * Lee el contenido del archivo JSON de productos más reciente
     * 
     * @return array Resultado con 'success', 'data' o 'error'
     */
    public static function read_latest_products_json()
    {
        $latest = self::get_latest_products_json();
        
        if (!$latest) {
            return array(
                'success' => false,
                'error' => 'No se encontró ningún archivo JSON de productos en nb-products/. Ejecute primero la descarga del catálogo.'
            );
        }
        
        $content = file_get_contents($latest['filepath']);
        
        if ($content === false) {
            return array(
                'success' => false,
                'error' => 'Error al leer el archivo: ' . $latest['filename']
            );
        }
        
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Error al decodificar JSON: ' . json_last_error_msg()
            );
        }
        
        return array(
            'success' => true,
            'data' => $data,
            'file_info' => $latest
        );
    }
    
    /**
     * Limpia archivos JSON antiguos, manteniendo solo los N más recientes
     * 
     * @param int $max_files Número máximo de archivos a mantener
     * @return int Número de archivos eliminados
     */
    public static function cleanup_old_products_json($max_files = 10)
    {
        self::init_products_dir();
        
        $deleted_count = 0;
        $files = glob(self::$products_dir . 'nb-products-*.json');
        
        if (count($files) <= $max_files) {
            return $deleted_count;
        }
        
        // Ordenar por fecha de modificación descendente
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Eliminar los archivos excedentes
        $files_to_delete = array_slice($files, $max_files);
        
        foreach ($files_to_delete as $file) {
            if (unlink($file)) {
                $deleted_count++;
                nb_log('Archivo JSON de productos eliminado: ' . basename($file), 'debug');
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Obtiene lista de todos los archivos JSON de productos
     * 
     * @return array Lista de archivos con información
     */
    public static function get_products_json_list()
    {
        self::init_products_dir();
        
        $files_list = array();
        $files = glob(self::$products_dir . 'nb-products-*.json');
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Extraer fecha del nombre: nb-products-YYYY-MM-DD_HH-mm.json
            if (preg_match('/^nb-products-(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2})\.json$/', $filename, $matches)) {
                $files_list[] = array(
                    'filename' => $filename,
                    'filepath' => $file,
                    'date' => $matches[1],
                    'time' => str_replace('-', ':', $matches[2]),
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'formatted_size' => self::format_bytes(filesize($file))
                );
            }
        }
        
        // Ordenar por fecha de modificación descendente
        usort($files_list, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $files_list;
    }
    
    /**
     * Formatear bytes a formato legible
     * 
     * @param int $bytes
     * @return string
     */
    private static function format_bytes($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Inicializar el directorio al cargar la clase
add_action('plugins_loaded', function() {
    // Asegurar que el directorio existe al cargar el plugin
    if (class_exists('NB_Product_Manager')) {
        NB_Product_Manager::get_products_json_list();
    }
});
