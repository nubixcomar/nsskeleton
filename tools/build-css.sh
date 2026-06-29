#!/usr/bin/env bash
# Compila el CSS de Tailwind a system/public/assets/css/app.css
# Descarga el binario standalone de Tailwind si falta (no se versiona).
#
#   bash tools/build-css.sh
set -e
cd "$(dirname "$0")/.."

BIN="tools/bin/tailwindcss.exe"   # en Linux/macOS usar el binario correspondiente
URL_WIN="https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-windows-x64.exe"

mkdir -p tools/bin system/public/assets/css

if [ ! -f "$BIN" ]; then
  echo "Descargando Tailwind standalone..."
  curl -sL --max-time 600 -o "$BIN" "$URL_WIN"
fi

echo "Compilando CSS..."
"$BIN" -i resources/css/app.css -o system/public/assets/css/app.css --minify
echo "OK: system/public/assets/css/app.css"
