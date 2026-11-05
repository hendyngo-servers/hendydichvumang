from __future__ import annotations

import logging

from aiogram import Router
from aiogram.types import ErrorEvent

error_router = Router(name="hendy-errors")
logger = logging.getLogger(__name__)


@error_router.errors()
async def handle_update_error(event: ErrorEvent) -> bool:
    logger.error(
        "Aiogram update failed: %s",
        type(event.exception).__name__,
        exc_info=(type(event.exception), event.exception, event.exception.__traceback__),
    )
    message = event.update.message
    if message:
        await message.answer("Hệ thống đang bận xử lý. Vui lòng thử lại hoặc mở Trung tâm khách hàng.")
    return True
