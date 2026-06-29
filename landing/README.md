# landing/ — Landing page de descarga

Página estática de presentación y descarga de nsSkeleton.

## Archivos
- `index.html` — landing (síntesis de para qué sirve, funciones, guía de instalación y descarga). Tailwind por CDN.
- `build-download.php` — genera el paquete ZIP en `downloads/`.
- `downloads/` — paquetes generados (no se versionan; ver `.gitignore`).

## Generar el paquete descargable
```
php landing/build-download.php
```
Crea `downloads/nsSkeleton-<VERSION>.zip` (la versión sale del archivo `VERSION`),
excluyendo `.env`, runtime de `storage/`, `vendor`, `node_modules`, `.git` y la propia
carpeta de descargas.

## Ver la landing
- Directo: abrí `landing/index.html` en el navegador.
- Servida: `php -S 127.0.0.1:8080 -t landing` → http://127.0.0.1:8080
- En XAMPP: http://localhost/skeleton/landing/

> Tras un cambio de versión (`VERSION`), regenerá el ZIP y actualizá el enlace de
> descarga en `index.html` si cambió el nombre del archivo.
