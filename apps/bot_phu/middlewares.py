from __future__ import annotations

from collections.abc import Awaitable, Callable
from typing import Any

from aiogram.dispatcher.middlewares.base import BaseMiddleware
from aiogram.types import Message, TelegramObject

from services.aiogram_bot.api_client import HendyApiClient


class EnsureCustomerMiddleware(BaseMiddleware):
    """Keep the HENDY customer profile synchronized for private-chat messages."""

    def __init__(self, api: HendyApiClient) -> None:
        self.api = api

    async def __call__(
        self,
        handler: Callable[[TelegramObject, dict[str, Any]], Awaitable[Any]],
        event: TelegramObject,
        data: dict[str, Any],
    ) -> Any:
        if isinstance(event, Message) and event.chat.type == "private" and event.from_user:
            profile = await self.api.upsert_user(event.from_user)
            data["hendy_customer"] = profile
            data["hendy_is_admin"] = profile.get("is_admin") is True
            menu = profile.get("menu")
            data["hendy_menu_config"] = menu if isinstance(menu, dict) else await self.api.get_menu_config("aiogram")
        return await handler(event, data)
