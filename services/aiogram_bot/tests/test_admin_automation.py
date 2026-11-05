from __future__ import annotations

import unittest

from aiogram.types import InlineKeyboardMarkup

from services.aiogram_bot.routers.admin import admin_panel_keyboard
from services.aiogram_bot.routers.automation import (
    AUTOMATION_CODES,
    automation_keyboard,
)


class AdminKeyboardTests(unittest.TestCase):
    def test_admin_panel_only_exposes_expected_internal_actions(self) -> None:
        keyboard = admin_panel_keyboard()
        callbacks = {
            button.callback_data
            for row in keyboard.inline_keyboard
            for button in row
        }
        self.assertEqual(
            callbacks,
            {"admin:stats", "admin:tickets", "automation:list", "customer:home"},
        )

    def test_automation_keyboard_filters_unknown_job_codes(self) -> None:
        keyboard = automation_keyboard([
            {"code": "cleanup_sessions", "enabled": True},
            {"code": "notify_new_tickets", "enabled": False},
            {"code": "delete_everything", "enabled": True},
        ])
        callbacks = [
            button.callback_data
            for row in keyboard.inline_keyboard
            for button in row
        ]
        self.assertIn("automation:toggle:cleanup_sessions", callbacks)
        self.assertIn("automation:confirm:cleanup_sessions", callbacks)
        self.assertIn("automation:toggle:notify_new_tickets", callbacks)
        self.assertIn("automation:confirm:notify_new_tickets", callbacks)
        self.assertNotIn("automation:toggle:delete_everything", callbacks)
        self.assertNotIn("automation:confirm:delete_everything", callbacks)
        self.assertTrue(all(len(value.encode("utf-8")) <= 64 for value in callbacks if value))

    def test_only_existing_node_automation_handlers_are_allowlisted(self) -> None:
        self.assertEqual(
            AUTOMATION_CODES,
            {"cleanup_sessions", "notify_new_tickets", "remind_open_tickets"},
        )

    def test_automation_keyboard_has_back_navigation_even_when_empty(self) -> None:
        keyboard = automation_keyboard([])
        self.assertIsInstance(keyboard, InlineKeyboardMarkup)
        self.assertEqual(
            keyboard.inline_keyboard[0][0].callback_data,
            "admin:home",
        )


if __name__ == "__main__":
    unittest.main()
