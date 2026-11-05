from __future__ import annotations

import os
import unittest
from uuid import uuid4

import asyncpg
from aiogram.fsm.storage.base import StorageKey

from services.aiogram_bot.fsm_storage import (
    PostgresEventIsolation,
    PostgresFSMStorage,
    storage_key_digest,
)


DATABASE_URL = os.getenv("TEST_DATABASE_URL", "")


@unittest.skipUnless(DATABASE_URL, "TEST_DATABASE_URL is not configured")
class PostgresFSMIntegrationTests(unittest.IsolatedAsyncioTestCase):
    async def asyncSetUp(self) -> None:
        self.pool = await asyncpg.create_pool(DATABASE_URL, min_size=1, max_size=4)
        self.storage = PostgresFSMStorage(self.pool, ttl_hours=1)
        self.key = StorageKey(
            bot_id=900000001,
            chat_id=900000002,
            user_id=900000003,
            destiny="test-" + uuid4().hex,
        )

    async def asyncTearDown(self) -> None:
        await self.pool.execute(
            "DELETE FROM bot_fsm_states WHERE key_hash = $1",
            storage_key_digest(self.key),
        )
        await self.pool.close()

    async def test_state_and_data_persist_across_storage_calls(self) -> None:
        await self.storage.set_state(self.key, "TicketForm:subject")
        await self.storage.set_data(self.key, {"subject": "Cần hỗ trợ", "step": 1})
        self.assertEqual(await self.storage.get_state(self.key), "TicketForm:subject")
        self.assertEqual(
            await self.storage.get_data(self.key),
            {"subject": "Cần hỗ trợ", "step": 1},
        )

    async def test_expired_state_is_removed_on_read(self) -> None:
        await self.storage.set_state(self.key, "TicketForm:message")
        await self.pool.execute(
            "UPDATE bot_fsm_states SET expires_at = NOW() - INTERVAL '1 second' WHERE key_hash = $1",
            storage_key_digest(self.key),
        )
        self.assertIsNone(await self.storage.get_state(self.key))
        self.assertEqual(
            await self.pool.fetchval(
                "SELECT COUNT(*) FROM bot_fsm_states WHERE key_hash = $1",
                storage_key_digest(self.key),
            ),
            0,
        )

    async def test_event_isolation_serializes_same_user(self) -> None:
        import asyncio

        isolation = PostgresEventIsolation(self.pool)
        order: list[str] = []

        async def worker(label: str) -> None:
            async with isolation.lock(self.key):
                order.append(label + "-enter")
                await asyncio.sleep(0.02)
                order.append(label + "-exit")

        await asyncio.gather(worker("a"), worker("b"))
        self.assertIn(order, [
            ["a-enter", "a-exit", "b-enter", "b-exit"],
            ["b-enter", "b-exit", "a-enter", "a-exit"],
        ])


if __name__ == "__main__":
    unittest.main()
