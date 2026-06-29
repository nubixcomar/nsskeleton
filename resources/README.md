# resources/ — Fuentes de assets

CSS de origen para compilar Tailwind. Los assets compilados/vendorizados quedan en
`system/public/assets/`.

## Estructura de assets
```
system/public/assets/
├── css/app.css          ← compilado por Tailwind (commiteado)
└── js/
    ├── alpine.min.js     ← vendorizado
    └── chart.umd.min.js  ← vendorizado
```

## Compilar el CSS
```
bash tools/build-css.sh
```
Descarga el binario standalone de Tailwind (a `tools/bin/`, no se versiona) si falta y
genera `system/public/assets/css/app.css` escaneando `resources/css/app.css`
(que incluye `@source` apuntando a las vistas y a la landing).

Tras agregar clases nuevas en las vistas, **recompilá** el CSS.

## Modo de assets
- `.env` → `ASSETS_MODE=local` (default): usa los assets locales (sin CDN).
- `ASSETS_MODE=cdn`: vuelve a los CDN (útil sin haber compilado).

El helper `Core\Assets` resuelve las etiquetas del `<head>` según el modo, y
`Core\Security` ajusta la CSP (sin orígenes externos en modo local).
