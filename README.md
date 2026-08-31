# Conector NewBytes

Este plugin de WordPress permite sincronizar los productos del catálogo de NewBytes con WooCommerce.

## Descripción

El plugin "Conector NewBytes" sincroniza automáticamente los productos desde el catálogo de NewBytes con tu tienda WooCommerce. Incluye funcionalidades para gestionar la sincronización de productos, actualización de inventarios y precios, y configuración de imágenes destacadas mediante URL.

## Requisitos

Para que el plugin funcione correctamente, se deben instalar los siguientes plugins adicionales:

- **FIFU (Featured Image From URL)**: Para gestionar las imágenes destacadas de los productos utilizando URLs.

## Instalación

1. Comprime la carpeta del plugin en un archivo `.zip`.
2. Desde el panel de administración de WordPress, ve a `Plugins` > `Añadir nuevo` > `Subir plugin`.
3. Selecciona el archivo `.zip` y haz clic en `Instalar ahora`.
4. Activa el plugin desde el menú de `Plugins` en WordPress.

## Configuración

Después de activar el plugin, ve a `Ajustes` > `Conector NB` para configurar los siguientes parámetros:

- **Usuario**: Tu nombre de usuario de NewBytes.
- **Contraseña**: Tu contraseña de NewBytes.
- **Prefijo SKU**: Un prefijo que se añadirá al inicio de cada SKU de producto.
- **Descripción corta**: Una descripción que se agregará a todos los productos.

## Sincronización de Productos

El plugin permite sincronizar los productos automáticamente cada minuto. También puedes forzar una sincronización manual desde la página de ajustes del plugin.

## Funcionalidades

- **Sincronización Automática**: Sincroniza los productos automáticamente a intervalos regulares.
- **Actualización de Inventarios y Precios**: Mantiene actualizados los inventarios y precios de los productos.
- **Configuración de Imágenes Destacadas**: Integra con el plugin FIFU para gestionar imágenes destacadas mediante URL.
- **Interfaz de Ajustes**: Proporciona una página de ajustes para configurar los parámetros necesarios para la sincronización.

## Configuración de IVA

El conector asigna la clase fiscal de cada producto según el IVA que informa la API
(21% → Estándar, 10,5% → Reducido, 0% → Exento). Configurá las tasas
correspondientes en **WooCommerce → Ajustes → Impuestos**.

El campo **"Sincronizar precios sin IVA"** debe combinarse con el ajuste
**"Precios introducidos con impuestos"** de WooCommerce:

| "Sincronizar sin IVA" | "Precios con impuestos" (WooCommerce) | Resultado |
|---|---|---|
| **Tildado** (recomendado) | **No** | WooCommerce agrega el IVA según la clase fiscal. Correcto. |
| Destildado | **Sí** | WooCommerce descuenta el IVA del precio. Correcto. |
| Destildado | **No** | El IVA se cobra dos veces. **Evitar.** |

Si tu tienda usa clases fiscales con *slugs* distintos de los de WooCommerce
(`reduced-rate`, `zero-rate`), definí la opción `nb_tax_class_map`:

```php
update_option('nb_tax_class_map', [
    'standard' => '',          // 21%
    'reduced'  => 'tu-slug',   // 10,5%
    'exempt'   => 'tu-slug',   // 0%
]);
```

## Desarrollo

Este plugin se desarrolla por especificaciones. Ver `.spec/` (vault de contexto,
specs por incidencia y orquestación).
