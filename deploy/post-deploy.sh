#!/usr/bin/env bash
#
# post-deploy.sh — Tareas de post-despliegue para un ambiente eTax2 (Laravel).
#
# Ejecutar SIEMPRE después de sincronizar/copiar archivos a un ambiente,
# porque eTax2 no usa git en el servidor y las vistas Blade quedan cacheadas.
#
# Uso:
#   bash post-deploy.sh /var/www/etax2.com        # producción
#   bash post-deploy.sh /var/www/dev.etax2.com    # desarrollo
#
# Qué hace:
#   1. Limpia cachés de Laravel (vistas, config, rutas, eventos, compiled).
#   2. Recompila las vistas Blade.
#   3. Valida con `php -l` el PHP compilado de cada vista. Esto detecta en el
#      DESPLIEGUE errores que `view:cache` NO detecta (p. ej. el bug de
#      @json() con expresiones con comas), en lugar de descubrirlos como un
#      500 en producción.
#
set -euo pipefail

APP_DIR="${1:?Uso: post-deploy.sh <ruta-app>  (p.ej. /var/www/etax2.com)}"
PHP="${PHP_BIN:-/usr/bin/php}"

[ -f "$APP_DIR/artisan" ] || { echo "ERROR: $APP_DIR no parece una app Laravel (falta artisan)"; exit 1; }
cd "$APP_DIR"
echo "[post-deploy] Ambiente: $APP_DIR"

echo "[post-deploy] Limpiando cachés…"
"$PHP" artisan optimize:clear

echo "[post-deploy] Recompilando vistas…"
"$PHP" artisan view:cache

echo "[post-deploy] Validando sintaxis de vistas compiladas…"
fail=0
for f in storage/framework/views/*.php; do
    [ -e "$f" ] || continue
    if ! "$PHP" -l "$f" >/dev/null 2>&1; then
        echo "  ✗ ERROR DE SINTAXIS en vista compilada: $f"
        "$PHP" -l "$f" 2>&1 | sed 's/^/    /'
        fail=1
    fi
done

if [ "$fail" -ne 0 ]; then
    echo "[post-deploy] FALLO: hay vistas que compilan a PHP inválido. Revisa el .blade fuente correspondiente."
    exit 1
fi

echo "[post-deploy] OK — cachés limpios y vistas válidas."
