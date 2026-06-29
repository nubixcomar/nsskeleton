# Walkthrough — Iteración B: landing page + descarga

**Fecha y hora:** 2026-06-22 21:30 | **Agente:** loop/dev-web (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S1 | **Versión:** 1.0.0

---

## Resumen ejecutivo
Se construyó la landing page de presentación y descarga de nsSkeleton 1.0.0, con
síntesis de para qué sirve, las dos capas, grilla de funciones, guía de instalación y
sección de descarga con un ZIP real generado del proyecto. Todo verificado sirviendo la
página y descargando el paquete.

## Cambios realizados
- **`landing/index.html`**: landing responsive (Tailwind CDN) — hero + CTA, "¿para qué
  sirve?", dos capas (agéntica / sistema), 8 funciones, guía de instalación en 3 pasos,
  caja de descarga (botón ZIP + git clone), footer con versión.
- **`landing/build-download.php`**: empaqueta el proyecto en
  `landing/downloads/nsSkeleton-<VERSION>.zip` bajo una carpeta raíz versionada,
  excluyendo `.env`, runtime de `storage/`, `vendor`, `node_modules`, `.git` y `downloads`.
- **`landing/README.md`** + `.gitignore` (no versionar el ZIP generado) + `.gitkeep`.
- **README**: se agregó `landing/` al árbol del proyecto.

## Verificación
- `php landing/build-download.php` → ZIP de **189 archivos / 175 KB**. Contiene
  `AGENTS.md`, `system/public/index.php`, `landing/index.html`, `.env.example`; **NO**
  contiene `.env` ni la carpeta `downloads`.
- Servida la landing (`php -S -t landing`): `GET /` → 200 (hero + botón de descarga +
  guía presentes); `GET /downloads/nsSkeleton-1.0.0.zip` → 200 `application/zip` (178 KB).

## Decisiones de diseño
- Landing estática (sin backend) → portable: se abre directo, se sirve o se publica.
- El ZIP se genera con el número de `VERSION`; el builder excluye secretos y runtime.
- El binario del ZIP no se versiona (se regenera con el script).

## Estado del pedido
Completo: (1) DB en 3307 verificada end-to-end, (2) landing de descarga + ZIP, (3)
release v1.0.0. El loop se detiene (no se reprograma).

## Referencias
- `landing/index.html`, `landing/build-download.php`, `VERSION`, `docs/INSTALL.md`.
