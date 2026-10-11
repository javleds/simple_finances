#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ "$(uname -s)" == "Darwin" ]] && command -v brew >/dev/null 2>&1; then
    php_prefix="$(HOMEBREW_NO_AUTO_UPDATE=1 brew --prefix php)"
    export PATH="$php_prefix/bin:$php_prefix/sbin:$PATH"
fi

php -r '
if (!extension_loaded("imagick")) {
    fwrite(STDERR, "Imagick no está disponible en ".PHP_BINARY.". Instala la extensión y vuelve a ejecutar composer run dev.\n");
    exit(1);
}
foreach (["JPEG", "PNG", "WEBP", "HEIC"] as $format) {
    if (Imagick::queryFormats($format) === []) {
        fwrite(STDERR, "Imagick no soporta el formato requerido: ".$format."\n");
        exit(1);
    }
}
echo "PHP de desarrollo: ".PHP_BINARY." (Imagick disponible)\n";
'

exec npx --no-install concurrently --kill-others --names=php,vite,queue \
    "php artisan serve --host=127.0.0.1 --port=8000 --tries=1" \
    "npm run dev" \
    "php artisan queue:listen --queue=default,attachments --sleep=1 --tries=5 --timeout=60"
