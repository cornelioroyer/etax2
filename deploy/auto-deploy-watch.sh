#!/usr/bin/env bash
#
# auto-deploy-watch.sh — Reconstruye el caché de vistas Blade cuando cambian
# los fuentes, para despliegues por SFTP/copia (eTax2 no usa git en el server).
#
# Pensado para ejecutarse por cron cada minuto, por ambiente:
#   * * * * * /home/rocky/auto-deploy-watch.sh /var/www/etax2.com     >/dev/null 2>&1
#   * * * * * /home/rocky/auto-deploy-watch.sh /var/www/dev.etax2.com >/dev/null 2>&1
#
# Por qué: un sync por SFTP/rsync puede dejar el .blade fuente con un mtime
# ANTERIOR al de la vista ya compilada en storage/framework/views. Laravel
# entonces considera el caché "fresco" y sirve PHP viejo/roto (causa del 500
# de CxP). Este watcher fuerza un view:clear + view:cache cuando el contenido
# de las vistas cambió, y valida el PHP compilado con `php -l`.
#
# Debounce: solo actúa cuando el hash de las vistas está ESTABLE (igual en dos
# chequeos seguidos) y difiere del último ya reconstruido. Así evita actuar a
# mitad de una subida SFTP de varios archivos.
#
set -euo pipefail

APP_DIR="${1:?uso: auto-deploy-watch.sh <ruta-app>}"
PHP="${PHP_BIN:-/usr/bin/php}"
[ -f "$APP_DIR/artisan" ] || exit 0

STATE_DIR="/home/rocky/.etax2-watch"
mkdir -p "$STATE_DIR"
key=$(printf '%s' "$APP_DIR" | md5sum | cut -d' ' -f1)
seen_f="$STATE_DIR/seen_$key"
done_f="$STATE_DIR/done_$key"

# Hash barato (mtime+tamaño+ruta, sin leer contenido) de todas las vistas Blade.
cur=$(cd "$APP_DIR" && find resources/views -type f -name '*.blade.php' \
        -printf '%T@ %s %p\n' 2>/dev/null | sort | md5sum | cut -d' ' -f1)

prev_seen=$(cat "$seen_f" 2>/dev/null || echo "")
prev_done=$(cat "$done_f" 2>/dev/null || echo "")
printf '%s' "$cur" > "$seen_f"

# Estable (igual al chequeo anterior) y distinto del último reconstruido.
[ "$cur" = "$prev_seen" ] || exit 0
[ "$cur" != "$prev_done" ] || exit 0

cd "$APP_DIR"
"$PHP" artisan view:clear >/dev/null 2>&1 || true
"$PHP" artisan view:cache >/dev/null 2>&1 || true

fail=0
for f in storage/framework/views/*.php; do
    [ -e "$f" ] || continue
    if ! "$PHP" -l "$f" >/dev/null 2>&1; then
        fail=1
        echo "$(date '+%F %T') [$APP_DIR] SYNTAX ERROR en vista compilada: $f" >> "$STATE_DIR/errors.log"
    fi
done

printf '%s' "$cur" > "$done_f"
logger -t etax2-watch "vistas recompiladas en $APP_DIR (errores_lint=$fail)"
