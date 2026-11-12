# HENDY Website Source

All Website UI and related page sources live in this directory.

- `app-store/`: Website home, App Store, network services, Website Admin, authentication, CSS and uploads.
- `telegram/admin-dashboard/`: Telegram Bot Admin UI; served by the Bot PHP backend at `/admin`.
- `telegram/customer-center/`: Telegram customer application UI; served by the Bot PHP backend at `/customer` and `/miniapp`.
- `telegram/.htaccess`: prevents private Telegram UI source files from being exposed by the public Website virtual host.
- `uploads/`: private/local Website image fallback storage.
- `healthz.php`: Render Website health endpoint.

The Bot PHP source remains in `../telegram-bot/`; the Bot Docker build copies this directory outside its public document root and reads the Admin/Customer HTML from it. Public Website navigation deliberately does not expose a Customer Center link.
