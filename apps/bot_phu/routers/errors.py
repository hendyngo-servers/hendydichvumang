from __future__ import annotations

import logging

from aiogram import Router
from aiogram.types import ErrorEvent

error_router = Router(name="hendy-errors")
logger = logging.getLogger(__name__)

USER_ERROR_MESSAGE = (
    "Hệ thống đang bận xử lý. Vui lòng thử lại hoặc mở Trung tâm khách hàng."
)


@error_router.errors()
async def handle_update_error(event: ErrorEvent) -> bool:
    # Do not log exception text or traceback: third-party HTTP exceptions may
    # contain request details, credentials, service URLs, or request headers.
    logger.error("Aiogram update failed: %s", type(event.exception).__name__)

    callback = event.update.callback_query
    if callback is not None:
        # A callback may already have been answered before a later operation failed.
        # Never let a secondary Telegram API error escape this error handler.
        try:
            await callback.answer(USER_ERROR_MESSAGE, show_alert=True)
        except Exception:
            pass
        return True

    message = event.update.message
    if message is not None:
        try:
            await message.answer(USER_ERROR_MESSAGE)
        except Exception:
            # A failed notification must not turn error handling into another error.
            pass
    return True
