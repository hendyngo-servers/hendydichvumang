from __future__ import annotations

from typing import Any

BOT_MENU_ACTIONS = {
    "customer": ("👤", "Trung tâm khách hàng"),
    "balance": ("💳", "Số dư"),
    "services": ("🛍", "Dịch vụ"),
    "support": ("🎫", "Hỗ trợ"),
    "admin": ("🛠", "Quản trị"),
    "automation": ("⚙️", "Tự động hóa"),
}

DEFAULT_MENU_ORDER = (
    "customer",
    "balance",
    "services",
    "support",
    "admin",
    "automation",
)


def normalize_menu_config(value: Any) -> dict[str, Any]:
    if not isinstance(value, dict):
        value = {}
    raw_order = value.get("buttonOrder", value.get("button_order", DEFAULT_MENU_ORDER))
    order = [
        action for action in raw_order
        if isinstance(action, str) and action in BOT_MENU_ACTIONS
    ] if isinstance(raw_order, list) else list(DEFAULT_MENU_ORDER)
    if "customer" not in order:
        order.insert(0, "customer")
    order = list(dict.fromkeys(order))

    raw_labels = value.get("buttonLabels", value.get("button_labels", {}))
    raw_icons = value.get("buttonIcons", value.get("button_icons", {}))
    labels = {
        key: str(raw_labels[key]).strip()[:40]
        for key in BOT_MENU_ACTIONS
        if isinstance(raw_labels, dict) and isinstance(raw_labels.get(key), str) and raw_labels[key].strip()
    }
    icons = {
        key: str(raw_icons[key]).strip()[:8]
        for key in BOT_MENU_ACTIONS
        if isinstance(raw_icons, dict) and isinstance(raw_icons.get(key), str)
    }
    menu_columns = value.get("menuColumns", value.get("menu_columns", 2))
    if not isinstance(menu_columns, int) or isinstance(menu_columns, bool) or menu_columns not in (1, 2, 3):
        menu_columns = 2

    return {
        "introText": str(value.get("introText", value.get("intro_text", "")))[:500],
        "menuColumns": menu_columns,
        "persistentKeyboard": value.get("persistentKeyboard", value.get("persistent_keyboard", True)) is True,
        "resizeKeyboard": value.get("resizeKeyboard", value.get("resize_keyboard", True)) is True,
        "buttonOrder": order,
        "buttonLabels": labels,
        "buttonIcons": icons,
    }


def menu_button_text(config: dict[str, Any], action: str) -> str:
    default_icon, default_label = BOT_MENU_ACTIONS[action]
    icon = config.get("buttonIcons", {}).get(action, default_icon)
    label = config.get("buttonLabels", {}).get(action, default_label)
    return " ".join(part for part in (icon, label) if part).strip()


def visible_menu_actions(config: dict[str, Any], is_admin: bool) -> list[str]:
    return [
        action for action in config["buttonOrder"]
        if action in BOT_MENU_ACTIONS and (action not in {"admin", "automation"} or is_admin)
    ]


def build_menu_rows(config: dict[str, Any], is_admin: bool) -> list[list[str]]:
    actions = visible_menu_actions(config, is_admin)
    width = config["menuColumns"]
    return [
        [menu_button_text(config, action) for action in actions[index:index + width]]
        for index in range(0, len(actions), width)
    ]


def resolve_menu_action(text: str, config: dict[str, Any], is_admin: bool) -> str | None:
    candidate = str(text or "").strip()
    aliases = {
        "👤 Trung tâm khách hàng": "customer",
        "👤 Tài khoản": "customer",
        "💳 Số dư": "balance",
        "🛍 Dịch vụ": "services",
        "🎫 Hỗ trợ": "support",
        "🛠 Quản trị": "admin",
        "⚙️ Tự động hóa": "automation",
    }
    for action in visible_menu_actions(config, is_admin):
        if menu_button_text(config, action) == candidate:
            return action
        if aliases.get(candidate) == action:
            return action
    return None
