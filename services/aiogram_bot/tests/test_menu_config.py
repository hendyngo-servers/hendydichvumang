from __future__ import annotations

import unittest

from services.aiogram_bot.menu_config import (
    build_menu_rows,
    menu_button_text,
    normalize_menu_config,
    resolve_menu_action,
    visible_menu_actions,
)


class MenuConfigTests(unittest.TestCase):
    def test_default_menu_rows_are_localized_and_grid_is_respected(self) -> None:
        config = normalize_menu_config({
            "menuColumns": 2,
            "buttonOrder": ["customer", "balance", "services", "support", "admin", "automation"],
        })
        self.assertEqual([len(row) for row in build_menu_rows(config, False)], [2, 2])
        self.assertEqual([len(row) for row in build_menu_rows(config, True)], [2, 2, 2])
        self.assertEqual(build_menu_rows(config, False)[0][0], "👤 Trung tâm khách hàng")

    def test_admin_actions_are_hidden_for_non_admin(self) -> None:
        config = normalize_menu_config({})
        self.assertNotIn("admin", visible_menu_actions(config, False))
        self.assertNotIn("automation", visible_menu_actions(config, False))
        self.assertIn("admin", visible_menu_actions(config, True))
        self.assertIn("automation", visible_menu_actions(config, True))

    def test_custom_label_and_icon_still_resolve_to_fixed_action(self) -> None:
        config = normalize_menu_config({
            "buttonLabels": {"customer": "Khu vực thành viên"},
            "buttonIcons": {"customer": "🧭"},
        })
        rendered = menu_button_text(config, "customer")
        self.assertEqual(rendered, "🧭 Khu vực thành viên")
        self.assertEqual(resolve_menu_action(rendered, config, False), "customer")
        self.assertIsNone(resolve_menu_action("lệnh tùy ý", config, False))

    def test_unknown_actions_are_removed_and_customer_is_restored(self) -> None:
        config = normalize_menu_config({
            "buttonOrder": ["unknown", "support", "support"],
            "menuColumns": 9,
        })
        self.assertEqual(config["buttonOrder"], ["customer", "support"])
        self.assertEqual(config["menuColumns"], 2)

    def test_legacy_button_text_remains_supported_during_rollout(self) -> None:
        config = normalize_menu_config({})
        self.assertEqual(resolve_menu_action("🎫 Hỗ trợ", config, False), "support")


if __name__ == "__main__":
    unittest.main()
