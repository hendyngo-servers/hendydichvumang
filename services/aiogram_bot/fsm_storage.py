from __future__ import annotations

import hashlib
import json
from collections.abc import AsyncGenerator, Mapping
from contextlib import asynccontextmanager
from typing import Any

import asyncpg
from aiogram.exceptions import DataNotDictLikeError
from aiogram.fsm.state import State
from aiogram.fsm.storage.base import BaseEventIsolation, BaseStorage, StateType, StorageKey


def storage_key_digest(key: StorageKey) -> str:
    payload = {
        "bot_id": key.bot_id,
        "chat_id": key.chat_id,
        "user_id": key.user_id,
        "thread_id": key.thread_id,
        "business_connection_id": key.business_connection_id,
        "destiny": key.destiny,
    }
    canonical = json.dumps(payload, sort_keys=True, separators=(",", ":"))
    return hashlib.sha256(canonical.encode("utf-8")).hexdigest()


def advisory_lock_id(key: StorageKey) -> int:
    digest = hashlib.sha256(("hendy-aiogram:" + storage_key_digest(key)).encode("utf-8")).digest()
    return int.from_bytes(digest[:8], byteorder="big", signed=True)


class PostgresFSMStorage(BaseStorage):
    """Persistent aiogram FSM storage backed by the HENDY PostgreSQL database."""

    def __init__(self, pool: asyncpg.Pool, ttl_hours: int = 24) -> None:
        self.pool = pool
        self.ttl_hours = ttl_hours

    def _key(self, key: StorageKey) -> str:
        return storage_key_digest(key)

    async def set_state(self, key: StorageKey, state: StateType = None) -> None:
        value = state.state if isinstance(state, State) else state
        await self.pool.execute(
            """
            INSERT INTO bot_fsm_states (key_hash, bot_id, chat_id, user_id, state, expires_at)
            VALUES ($1, $2, $3, $4, $5, NOW() + make_interval(hours => $6))
            ON CONFLICT (key_hash) DO UPDATE
              SET state = EXCLUDED.state,
                  expires_at = EXCLUDED.expires_at,
                  updated_at = NOW()
            """,
            self._key(key),
            key.bot_id,
            key.chat_id,
            key.user_id,
            value,
            self.ttl_hours,
        )

    async def get_state(self, key: StorageKey) -> str | None:
        value = await self.pool.fetchval(
            "SELECT state FROM bot_fsm_states WHERE key_hash = $1 AND expires_at > NOW()",
            self._key(key),
        )
        if value is None:
            await self.pool.execute(
                "DELETE FROM bot_fsm_states WHERE key_hash = $1 AND expires_at <= NOW()",
                self._key(key),
            )
        return value

    async def set_data(self, key: StorageKey, data: Mapping[str, Any]) -> None:
        if not isinstance(data, Mapping):
            raise DataNotDictLikeError(f"Data must be dict-like, got {type(data).__name__}")
        encoded = json.dumps(dict(data), ensure_ascii=False, separators=(",", ":"))
        await self.pool.execute(
            """
            INSERT INTO bot_fsm_states (key_hash, bot_id, chat_id, user_id, data, expires_at)
            VALUES ($1, $2, $3, $4, $5::jsonb, NOW() + make_interval(hours => $6))
            ON CONFLICT (key_hash) DO UPDATE
              SET data = EXCLUDED.data,
                  expires_at = EXCLUDED.expires_at,
                  updated_at = NOW()
            """,
            self._key(key),
            key.bot_id,
            key.chat_id,
            key.user_id,
            encoded,
            self.ttl_hours,
        )

    async def get_data(self, key: StorageKey) -> dict[str, Any]:
        value = await self.pool.fetchval(
            "SELECT data FROM bot_fsm_states WHERE key_hash = $1 AND expires_at > NOW()",
            self._key(key),
        )
        if value is None:
            await self.pool.execute(
                "DELETE FROM bot_fsm_states WHERE key_hash = $1 AND expires_at <= NOW()",
                self._key(key),
            )
            return {}
        if isinstance(value, str):
            decoded = json.loads(value)
        else:
            decoded = value
        return decoded if isinstance(decoded, dict) else {}

    async def close(self) -> None:
        # The pool is shared with event isolation and is closed by the app lifecycle.
        return None


class PostgresEventIsolation(BaseEventIsolation):
    """Use PostgreSQL session advisory locks to serialize updates per user/chat."""

    def __init__(self, pool: asyncpg.Pool) -> None:
        self.pool = pool

    @asynccontextmanager
    async def lock(self, key: StorageKey) -> AsyncGenerator[None, None]:
        connection = await self.pool.acquire()
        lock_id = advisory_lock_id(key)
        acquired = False
        try:
            await connection.fetchval("SELECT pg_advisory_lock($1)", lock_id)
            acquired = True
            yield
        finally:
            if acquired:
                try:
                    await connection.fetchval("SELECT pg_advisory_unlock($1)", lock_id)
                finally:
                    await self.pool.release(connection)
            else:
                await self.pool.release(connection)

    async def close(self) -> None:
        return None
