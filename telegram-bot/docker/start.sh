#!/usr/bin/env bash
set -euo pipefail

cd /var/www/app

# Render terminates TLS and forwards all public HTTP/WebSocket traffic to PORT.
port="${PORT:-10000}"
if ! [[ "$port" =~ ^[0-9]{1,5}$ ]] || (( port < 1 || port > 65535 )); then
    echo "PORT phải là số nguyên từ 1 đến 65535." >&2
    exit 1
fi

sed -i -E "s/^Listen [0-9]+$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/:10000>/:${port}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/modules
chown -R www-data:www-data storage
chmod 750 storage storage/modules

php bin/validate-config.php
php bin/wait-for-database.php
if [[ "${RUN_MIGRATIONS_ON_START:-true}" == "true" ]]; then
    php bin/migrate.php
else
    echo "RUN_MIGRATIONS_ON_START=false; bỏ qua migration lúc khởi động."
fi

# Register only the production bot. The script persists/reuses the webhook secret,
# sets the HTTPS endpoint and verifies Telegram's getWebhookInfo response.
if [[ "${WEBHOOK_AUTO_REGISTER:-true}" == "true" ]]; then
    php bin/register-webhook.php
else
    echo "WEBHOOK_AUTO_REGISTER=false; bỏ qua đăng ký webhook tự động."
fi

# Start independent workers only when explicitly enabled. All secrets are read from
# Render Environment; they are never passed on command lines or printed to logs.
background_pids=()
if [[ "${AI_MEDIA_QUEUE_ENABLED:-false}" == "true" ]]; then
    if [[ -z "${OPENAI_API_KEY:-}" && -z "${ELEVENLABS_API_KEY:-}" ]]; then
        echo "AI_MEDIA_QUEUE_ENABLED=true nhưng chưa có OPENAI_API_KEY hoặc ELEVENLABS_API_KEY." >&2
        exit 1
    fi
    php bin/ai-worker.php &
    background_pids+=("$!")
    echo "HENDY AI media worker đã khởi chạy."
fi

if [[ "${CODE_TELEGRAM_WORKER_ENABLED:-false}" == "true" ]]; then
    if [[ "${CODE_TELEGRAM_ENABLED:-false}" != "true" ]]; then
        echo "CODE_TELEGRAM_WORKER_ENABLED=true nhưng CODE_TELEGRAM_ENABLED=false." >&2
        exit 1
    fi
    php bin/code-telegram-worker.php &
    background_pids+=("$!")
    echo "HENDY Code Telegram OCR worker đã khởi chạy."
fi

if [[ "${WEBSOCKET_ENABLED:-false}" == "true" ]]; then
    if [[ -z "${WEBSOCKET_ALLOWED_ORIGINS:-}" ]]; then
        echo "WEBSOCKET_ALLOWED_ORIGINS bắt buộc khi bật WebSocket." >&2
        exit 1
    fi
    php bin/websocket.php &
    background_pids+=("$!")
fi

apache2-foreground &
background_pids+=("$!")

cleanup() {
    for pid in "${background_pids[@]}"; do
        kill "$pid" 2>/dev/null || true
    done
    for pid in "${background_pids[@]}"; do
        wait "$pid" 2>/dev/null || true
    done
}
trap cleanup EXIT INT TERM

echo "HENDY services started; Apache port=$port; AI queue=${AI_MEDIA_QUEUE_ENABLED:-false}; WebSocket=${WEBSOCKET_ENABLED:-false}."
set +e
wait -n "${background_pids[@]}"
status=$?
set -e
echo "Một tiến trình HENDY đã dừng (exit=$status); kết thúc container để Render khởi động lại." >&2
exit "$status"
