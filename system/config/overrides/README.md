# config/overrides/ — Overrides de configuración (de la app)

Punto de extensión del core. Para personalizar una config **sin editar el archivo del core**
(que el actualizador pisa), creá acá un archivo con el **mismo nombre** y devolvé solo las
claves a cambiar. `Core\Config` mezcla core + override; **en conflicto gana la app**.

Ejemplo — agregar feature flags propios y cambiar un default sin tocar `config/features.php`:

```php
// config/overrides/features.php
<?php
return [
    'maintenance_banner' => true,   // pisa el default del core
    'mi_flag_de_negocio' => false,  // agrega uno nuevo
];
```

Ejemplo — cambiar el nombre/zona de la app:

```php
// config/overrides/app.php
<?php
return [
    'timezone' => 'America/Argentina/Buenos_Aires',
];
```

## Cómo funciona el merge
- Mapas (arrays asociativos) → se combinan **recursivamente**.
- Listas y escalares → el valor del override **reemplaza** al del core.

## Qué configs son overrideables
Cualquiera que el core lea con `Core\Config::load('nombre')`. Hoy ya pasan por Config:
`app`, `features`. Para hacer overrideable otra, basta con que el core la lea vía Config
(el patrón es un cambio de una línea en el servicio que la consume).

> Estos archivos de override son **de la app**: el actualizador de core no los toca.
