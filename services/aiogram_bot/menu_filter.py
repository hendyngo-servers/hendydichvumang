from __future__ import annotations

from aiogram.filters import Filter
from aiogram.types import Message

from services.aiogram_bot.menu_config import normalize_menu_config, resolve_menu_action


class MenuActionFilter(Filter):
    """Match an action against the current database-managed button label."""

    def __init__(self, action: str) -> None:
        self.action = action

    async def __call__(
        self,
        message: Message,
        hendy_menu_config: dict | None = None,
        hendy_is_admin: bool = False,
    ) -> bool:
        if not message.text:
            return False
        config = normalize_menu_config(hendy_menu_config)
        return resolve_menu_action(message.text, config, hendy_is_admin) == self.action
