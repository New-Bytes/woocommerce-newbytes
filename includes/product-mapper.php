<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mapper unificado API NewBytes -> producto WooCommerce.
 *
 * Única fuente de verdad para traducir una fila del catálogo de la API en
 * llamadas $product->set_*(). Reemplaza la lógica que estaba triplicada en
 * cron-hooks.php, sync-ajax.php y product-sync.php.
 *
 * No llama $product->save(): lo hace el caller (así puede setear antes la
 * descripción traída por HTTP).
 *
 * @see .spec/specs/SPEC-0001-unified-product-mapper.md
 * @see .spec/specs/SPEC-0002-tax-class-and-status.md
 * @see .spec/specs/SPEC-0003-price-cotizacion-dimension-guards.md
 */
final class NB_Product_Mapper
{
    /** @var array<string,int|null> Caché de term_id de categoría por request. */
    private static $cat_cache = array();

    /**
     * Opciones por defecto tomadas de la configuración del plugin.
     *
     * @return array{prefix:string,sync_no_iva:bool,sync_usd:bool,skip_additional_description:bool,post_status:string}
     */
    public static function defaults()
    {
        return array(
            'prefix'      => (string) get_option('nb_prefix', 'NB_'),
            'sync_no_iva' => (bool) get_option('nb_sync_no_iva', false),
            'sync_usd'    => (bool) get_option('nb_sync_usd', false),
            'skip_additional_description' => false,
            'post_status' => 'publish',
        );
    }

    /**
     * Aplica al producto todos los campos comunes de la fila de la API.
     *
     * @param WC_Product $product
     * @param array      $row
     * @param array      $options
     * @return array{ok:bool,skipped:string[],notes:string[]}
     */
    public static function apply_core($product, array $row, array $options = array())
    {
        $o   = array_merge(self::defaults(), $options);
        $res = array('ok' => true, 'skipped' => array(), 'notes' => array());

        if (empty($row['sku']) || !isset($row['price'])) {
            return array('ok' => false, 'skipped' => array(), 'notes' => array('row inválido: falta sku o price'));
        }

        // SKU
        $product->set_sku($o['prefix'] . $row['sku']);

        // Precio (puede quedar omitido si no hay cotización válida)
        $price = self::map_price($row, $o);
        if ($price === null) {
            $res['skipped'][] = 'price';
            $res['notes'][]   = 'precio omitido: cotización ausente/no válida o datos de precio incompletos';
        } else {
            $product->set_regular_price((string) $price);
        }

        // Clase fiscal + estado impositivo (siempre, para crear y para corregir históricos)
        $product->set_tax_status('taxable');
        $product->set_tax_class(self::map_tax_class($row));

        // Stock
        $stock = isset($row['amountStock']) && is_numeric($row['amountStock']) ? (int) $row['amountStock'] : 0;
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock);
        $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');

        // Dimensiones (con guardas)
        self::apply_dimensions($product, $row);

        // Categoría
        $category = '';
        if (!empty($row['categoryDescriptionUser'])) {
            $category = (string) $row['categoryDescriptionUser'];
        } elseif (!empty($row['category'])) {
            $category = (string) $row['category'];
        }
        if ($category !== '') {
            $term_id = self::resolve_category_term_id($category);
            if ($term_id) {
                $product->set_category_ids(array($term_id));
            }
        }

        // Descripción adicional configurable (salvo que el caller la maneje él)
        if (empty($o['skip_additional_description'])) {
            $additional = (string) get_option('nb_description', '');
            if ($additional !== '') {
                $product->set_description($additional);
            }
        }

        // ID interno NewBytes para integraciones externas
        if (isset($row['id'])) {
            $product->update_meta_data('_nb_product_id', (int) $row['id']);
        }

        return $res;
    }

    /* --------------------------------------------------------------------- */

    /**
     * Calcula el precio a guardar en regular_price.
     *
     * Devuelve null cuando NO se puede calcular de forma confiable (sin
     * cotización válida en modo ARS, o datos de precio incompletos). En ese caso
     * el caller debe OMITIR la actualización de precio (nunca guardar 0).
     *
     * @return float|null
     */
    public static function map_price(array $row, array $options)
    {
        if (!isset($row['price']) || !is_array($row['price'])) {
            return null;
        }

        $no_iva = !empty($options['sync_no_iva']);
        $usd    = !empty($options['sync_usd']);

        $key = $no_iva ? 'value' : 'finalPriceWithUtility';
        if (!isset($row['price'][$key]) || !is_numeric($row['price'][$key])) {
            return null;
        }

        $base = (float) $row['price'][$key];

        if (!$usd) {
            $cot = isset($row['cotizacion']) && is_numeric($row['cotizacion']) ? (float) $row['cotizacion'] : null;
            if ($cot === null || $cot <= 0) {
                return null;
            }
            $base *= $cot;
        }

        if (!is_finite($base) || $base < 0) {
            return null;
        }

        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        $rounded  = function_exists('wc_format_decimal')
            ? wc_format_decimal($base, $decimals)
            : number_format($base, $decimals, '.', '');

        return (float) $rounded;
    }

    /** @var array<string,string> Slug de clase fiscal resuelto por clave, por request. */
    private static $class_slug_cache = array();

    /**
     * Devuelve el slug de clase fiscal de WooCommerce según el IVA de la API.
     *
     * Clasifica el IVA en una de tres claves:
     *   iva >= 21     -> 'standard' (siempre '' = Estándar)
     *   0 < iva < 21  -> 'reduced'  (p. ej. 10,5)
     *   iva <= 0      -> 'exempt'   (0 / exento)
     *
     * y resuelve el SLUG real de esa clave en ESTA tienda, en orden:
     *   1. opción nb_tax_class_map (override explícito del administrador)
     *   2. la clase de WooCommerce que tenga una tasa configurada igual al IVA
     *   3. heurística por slug/nombre de las clases existentes
     *   4. '' (Estándar) + aviso en el log
     *
     * El resultado es filtrable vía 'nb_tax_class_for_iva'.
     *
     * @return string slug de clase fiscal ('' = Estándar).
     */
    public static function map_tax_class(array $row)
    {
        $iva = isset($row['price']['iva']) && is_numeric($row['price']['iva'])
            ? (float) $row['price']['iva']
            : 21.0;

        if ($iva >= 21) {
            $key = 'standard';
        } elseif ($iva <= 0) {
            $key = 'exempt';
        } else {
            $key = 'reduced';
        }

        $slug = self::resolve_class_slug($key, $iva);

        /**
         * Permite sobreescribir la clase fiscal resuelta.
         *
         * @param string $slug Slug resuelto ('' = Estándar).
         * @param float  $iva  Alícuota informada por la API.
         * @param array  $row  Fila completa de producto de la API.
         */
        return (string) apply_filters('nb_tax_class_for_iva', $slug, $iva, $row);
    }

    /**
     * Resuelve el slug de clase fiscal para una clave ('standard'|'reduced'|'exempt').
     *
     * @param string $key
     * @param float  $iva Alícuota concreta (para el match por tasa).
     * @return string
     */
    public static function resolve_class_slug($key, $iva = null)
    {
        if (isset(self::$class_slug_cache[$key])) {
            return self::$class_slug_cache[$key];
        }

        // 1. Override explícito por opción
        $map = get_option('nb_tax_class_map', array());
        if (is_array($map) && array_key_exists($key, $map)) {
            return self::$class_slug_cache[$key] = (string) $map[$key];
        }

        if ($key === 'standard') {
            return self::$class_slug_cache[$key] = '';
        }

        // 2. Clase de WooCommerce cuya tasa configurada coincide con el IVA
        $target = ($iva !== null) ? (float) $iva : ($key === 'reduced' ? 10.5 : 0.0);
        $by_rate = self::class_slug_for_rate($target, $key === 'exempt');
        if ($by_rate !== null) {
            return self::$class_slug_cache[$key] = $by_rate;
        }

        // 3. Heurística por slug / nombre
        $by_kw = self::class_slug_by_keywords($key);
        if ($by_kw !== null) {
            return self::$class_slug_cache[$key] = $by_kw;
        }

        // 4. Sin resolver -> Estándar + aviso
        nb_log(
            'No se pudo determinar la clase fiscal para IVA "' . $key . '". El producto queda en Estándar (21%). '
            . 'Configurá la opción nb_tax_class_map con el slug correcto.',
            'warning'
        );

        return self::$class_slug_cache[$key] = '';
    }

    /**
     * Busca la clase fiscal (slug) que tiene alguna tasa configurada == $rate.
     *
     * @param float $rate         Alícuota buscada (p. ej. 10.5).
     * @param bool  $allow_zero  Si true, acepta también la clase Estándar ('') con tasa 0.
     * @return string|null Slug de la clase, o null si no hay match.
     */
    public static function class_slug_for_rate($rate, $allow_zero = false)
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return null;
        }

        $table = $wpdb->prefix . 'woocommerce_tax_rates';

        // Evitar consultar si la tabla de WooCommerce no existe.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return null;
        }

        $rows = $wpdb->get_results("SELECT DISTINCT tax_rate_class, tax_rate FROM {$table}");
        if (empty($rows)) {
            return null;
        }

        foreach ($rows as $r) {
            if (abs((float) $r->tax_rate - (float) $rate) < 0.01) {
                $slug = (string) $r->tax_rate_class; // '' = Estándar
                if ($slug === '' && !$allow_zero) {
                    continue; // no mandar "reducido/exento" a Estándar
                }
                return $slug;
            }
        }

        return null;
    }

    /**
     * Heurística: busca entre las clases fiscales existentes una cuyo slug o nombre
     * contenga palabras clave de la categoría buscada.
     *
     * @param string $key 'reduced' | 'exempt'
     * @return string|null
     */
    public static function class_slug_by_keywords($key)
    {
        if (!class_exists('WC_Tax')) {
            return null;
        }

        $slugs = WC_Tax::get_tax_class_slugs();
        $names = WC_Tax::get_tax_classes();
        if (empty($slugs)) {
            return null;
        }

        $needles = ($key === 'reduced')
            ? array('reduc')
            : array('zero', 'cero', 'exent', 'excent', 'exempt');

        foreach ($slugs as $i => $slug) {
            $name = isset($names[$i]) ? $names[$i] : '';
            $hay  = strtolower($slug . ' ' . (function_exists('remove_accents') ? remove_accents($name) : $name));
            foreach ($needles as $n) {
                if (strpos($hay, $n) !== false) {
                    return (string) $slug;
                }
            }
        }

        return null;
    }

    /**
     * Limpia la caché de slugs de clase fiscal (útil en tests / entre lotes).
     */
    public static function reset_tax_class_cache()
    {
        self::$class_slug_cache = array();
    }

    /**
     * Aplica peso y dimensiones sólo si vienen y son numéricos.
     * API en g/mm -> WooCommerce en kg/cm.
     */
    public static function apply_dimensions($product, array $row)
    {
        if (isset($row['weightAverage']) && is_numeric($row['weightAverage'])) {
            $product->set_weight((float) $row['weightAverage'] / 1000);
        }
        if (isset($row['widthAverage']) && is_numeric($row['widthAverage'])) {
            $product->set_width((float) $row['widthAverage'] / 10);
        }
        if (isset($row['lengthAverage']) && is_numeric($row['lengthAverage'])) {
            $product->set_length((float) $row['lengthAverage'] / 10);
        }
        if (isset($row['highAverage']) && is_numeric($row['highAverage'])) {
            $product->set_height((float) $row['highAverage'] / 10);
        }
    }

    /**
     * Resuelve (o crea) el término de categoría de producto y cachea el id.
     *
     * @return int|null term_id o null si no se pudo resolver.
     */
    public static function resolve_category_term_id($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        if (array_key_exists($name, self::$cat_cache)) {
            return self::$cat_cache[$name];
        }

        $term = term_exists($name, 'product_cat');
        if (!$term) {
            $term = wp_insert_term($name, 'product_cat');
        }

        if (is_wp_error($term)) {
            self::$cat_cache[$name] = null;
        } else {
            $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
            self::$cat_cache[$name] = $term_id ?: null;
        }

        return self::$cat_cache[$name];
    }

    /**
     * Limpia la caché estática de categorías (útil entre lotes largos).
     */
    public static function reset_category_cache()
    {
        self::$cat_cache = array();
    }
}
