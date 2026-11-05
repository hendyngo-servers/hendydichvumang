from __future__ import annotations

import unittest

from services.aiogram_bot.config import Settings, normalize_webhook_secret


class SettingsTests(unittest.TestCase):
    def valid_env(self) -> dict[str, str]:
        return {
            "AIOGRAM_BOT_TOKEN": "123456:aiogram-test-token",
            "AIOGRAM_WEBHOOK_SECRET": "render-generated-secret+/",
            "RENDER_EXTERNAL_URL": "https://aiogram.onrender.com",
            "HENDY_API_BASE_URL": "https://api-hendy.onrender.com",
            "HENDY_BOT_SERVICE_KEY": "a" * 48,
            "DATABASE_URL": "postgresql://user:pass@db.example.com:5432/hendy?sslmode=require",
            "PUBLIC_APP_URL": "https://hendy-site.onrender.com",
            "PGSSLMODE": "require",
        }

    def test_valid_settings_are_normalized(self) -> None:
        settings = Settings.from_env(self.valid_env())
        self.assertEqual(settings.webhook_base_url, "https://aiogram.onrender.com")
        self.assertEqual(settings.api_base_url, "https://api-hendy.onrender.com")
        self.assertEqual(settings.database_ssl_mode, "require")
        self.assertNotIn("sslmode", settings.database_url)
        self.assertEqual(settings.webhook_path, "/telegram/webhook")

    def test_webhook_secret_is_url_safe(self) -> None:
        normalized = normalize_webhook_secret("render-value+/=")
        self.assertRegex(normalized, r"^[A-Za-z0-9_-]{43}$")

    def test_aiogram_token_must_differ_from_primary_grammy_token(self) -> None:
        env = self.valid_env()
        env["BOT_TOKEN"] = env["AIOGRAM_BOT_TOKEN"]
        with self.assertRaisesRegex(ValueError, "phải khác"):
            Settings.from_env(env)

    def test_api_and_mini_app_must_use_https(self) -> None:
        env = self.valid_env()
        env["HENDY_API_BASE_URL"] = "http://api.example.com"
        with self.assertRaisesRegex(ValueError, "HTTPS"):
            Settings.from_env(env)

    def test_legacy_url_rejects_static_token(self) -> None:
        env = self.valid_env()
        env["LEGACY_CUSTOMER_URL"] = "https://legacy.example.com/?token=static"
        with self.assertRaisesRegex(ValueError, "token"):
            Settings.from_env(env)

    def test_invalid_fsm_ttl_is_rejected(self) -> None:
        env = self.valid_env()
        env["AIOGRAM_FSM_TTL_HOURS"] = "0"
        with self.assertRaisesRegex(ValueError, "1–168"):
            Settings.from_env(env)


if __name__ == "__main__":
    unittest.main()
