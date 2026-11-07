from __future__ import annotations

import unittest
from types import SimpleNamespace
from unittest.mock import AsyncMock

from apps.bot_phu.routers.errors import USER_ERROR_MESSAGE, handle_update_error


class ErrorHandlerTests(unittest.IsolatedAsyncioTestCase):
    async def test_callback_query_is_acknowledged_with_safe_message(self) -> None:
        callback = SimpleNamespace(answer=AsyncMock())
        event = SimpleNamespace(
            exception=RuntimeError("sensitive-service-key"),
            update=SimpleNamespace(callback_query=callback, message=None),
        )

        with self.assertLogs("apps.bot_phu.routers.errors", level="ERROR") as captured:
            handled = await handle_update_error(event)

        self.assertTrue(handled)
        callback.answer.assert_awaited_once_with(USER_ERROR_MESSAGE, show_alert=True)
        self.assertNotIn("sensitive-service-key", "\n".join(captured.output))
        self.assertIn("RuntimeError", "\n".join(captured.output))

    async def test_message_receives_generic_error_without_sensitive_details(self) -> None:
        message = SimpleNamespace(answer=AsyncMock())
        event = SimpleNamespace(
            exception=RuntimeError("database-password"),
            update=SimpleNamespace(callback_query=None, message=message),
        )

        handled = await handle_update_error(event)

        self.assertTrue(handled)
        message.answer.assert_awaited_once_with(USER_ERROR_MESSAGE)

    async def test_notification_failure_does_not_escape_error_handler(self) -> None:
        callback = SimpleNamespace(answer=AsyncMock(side_effect=RuntimeError("expired query")))
        event = SimpleNamespace(
            exception=ValueError("private detail"),
            update=SimpleNamespace(callback_query=callback, message=None),
        )

        self.assertTrue(await handle_update_error(event))
        callback.answer.assert_awaited_once()


if __name__ == "__main__":
    unittest.main()
