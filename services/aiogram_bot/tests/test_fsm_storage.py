from __future__ import annotations

import unittest

from aiogram.fsm.storage.base import StorageKey

from services.aiogram_bot.fsm_storage import advisory_lock_id, storage_key_digest


class StorageKeyTests(unittest.TestCase):
    def test_same_key_produces_same_digest(self) -> None:
        first = StorageKey(bot_id=100, chat_id=200, user_id=300)
        second = StorageKey(bot_id=100, chat_id=200, user_id=300)
        self.assertEqual(storage_key_digest(first), storage_key_digest(second))
        self.assertEqual(advisory_lock_id(first), advisory_lock_id(second))

    def test_different_bot_or_user_is_isolated(self) -> None:
        first = StorageKey(bot_id=100, chat_id=200, user_id=300)
        different_bot = StorageKey(bot_id=101, chat_id=200, user_id=300)
        different_user = StorageKey(bot_id=100, chat_id=200, user_id=301)
        self.assertNotEqual(storage_key_digest(first), storage_key_digest(different_bot))
        self.assertNotEqual(storage_key_digest(first), storage_key_digest(different_user))

    def test_forum_topic_and_business_connection_are_isolated(self) -> None:
        first = StorageKey(bot_id=100, chat_id=200, user_id=300)
        topic = StorageKey(bot_id=100, chat_id=200, user_id=300, thread_id=55)
        business = StorageKey(bot_id=100, chat_id=200, user_id=300, business_connection_id="bc-1")
        self.assertNotEqual(storage_key_digest(first), storage_key_digest(topic))
        self.assertNotEqual(storage_key_digest(first), storage_key_digest(business))


if __name__ == "__main__":
    unittest.main()
