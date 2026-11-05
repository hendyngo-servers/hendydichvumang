from __future__ import annotations

from typing import Any

import aiohttp


class HendyApiError(RuntimeError):
    def __init__(self, message: str, status: int = 0) -> None:
        super().__init__(message)
        self.status = status


class HendyApiClient:
    def __init__(self, base_url: str, service_key: str) -> None:
        self.base_url = base_url.rstrip("/")
        self.service_key = service_key
        self._session: aiohttp.ClientSession | None = None

    async def open(self) -> None:
        if self._session is None or self._session.closed:
            timeout = aiohttp.ClientTimeout(total=12, connect=4, sock_read=8)
            self._session = aiohttp.ClientSession(
                timeout=timeout,
                headers={
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                    "X-Hendy-Service-Key": self.service_key,
                    "User-Agent": "HENDY-Aiogram-Adapter/1.0",
                },
            )

    async def close(self) -> None:
        if self._session and not self._session.closed:
            await self._session.close()

    async def _request(
        self,
        method: str,
        path: str,
        *,
        payload: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        await self.open()
        assert self._session is not None
        try:
            async with self._session.request(
                method,
                self.base_url + path,
                json=payload,
            ) as response:
                try:
                    data = await response.json(content_type=None)
                except (aiohttp.ContentTypeError, ValueError):
                    data = {}
                if not isinstance(data, dict):
                    data = {}
                if response.status >= 400:
                    message = str(data.get("error") or f"HENDY API trả HTTP {response.status}")
                    raise HendyApiError(message, response.status)
                return data
        except (aiohttp.ClientError, TimeoutError) as exc:
            raise HendyApiError("Không thể kết nối HENDY API. Vui lòng thử lại.") from exc

    async def health(self) -> dict[str, Any]:
        return await self._request("GET", "/healthz")

    async def get_primary_bot_identity(self) -> dict[str, Any]:
        return await self._request("GET", "/internal/bot/primary-identity")

    async def upsert_user(self, user: Any) -> dict[str, Any]:
        return await self._request(
            "POST",
            "/internal/bot/users/upsert",
            payload={
                "telegram_id": str(user.id),
                "username": user.username,
                "first_name": user.first_name or "",
                "last_name": user.last_name or "",
                "language_code": user.language_code or "vi",
            },
        )

    async def get_balance(self, telegram_id: int) -> dict[str, Any]:
        return await self._request(
            "GET",
            f"/internal/bot/users/{int(telegram_id)}/balance",
        )

    async def get_services(self) -> list[dict[str, Any]]:
        response = await self._request("GET", "/api/services")
        services = response.get("services", [])
        return services if isinstance(services, list) else []

    async def create_ticket(
        self,
        telegram_id: int,
        subject: str,
        message: str,
    ) -> dict[str, Any]:
        return await self._request(
            "POST",
            "/internal/bot/tickets",
            payload={
                "telegram_id": str(telegram_id),
                "subject": subject,
                "message": message,
            },
        )


    def _admin_path(self, telegram_id: int) -> str:
        value = int(telegram_id)
        if value < 1:
            raise ValueError("Telegram ID quản trị không hợp lệ.")
        return f"/internal/bot/admin/{value}"

    async def is_admin(self, telegram_id: int) -> bool:
        result = await self._request("GET", self._admin_path(telegram_id) + "/access")
        return result.get("authorized") is True

    async def get_admin_stats(self, telegram_id: int) -> dict[str, Any]:
        return await self._request("GET", self._admin_path(telegram_id) + "/stats")

    async def get_admin_tickets(self, telegram_id: int) -> list[dict[str, Any]]:
        result = await self._request("GET", self._admin_path(telegram_id) + "/tickets")
        tickets = result.get("tickets", [])
        return tickets if isinstance(tickets, list) else []

    async def update_admin_ticket(
        self,
        telegram_id: int,
        ticket_id: int,
        status: str,
        admin_note: str | None = None,
    ) -> dict[str, Any]:
        payload: dict[str, Any] = {"status": status}
        if admin_note is not None:
            payload["admin_note"] = admin_note
        return await self._request(
            "PATCH",
            self._admin_path(telegram_id) + f"/tickets/{int(ticket_id)}",
            payload=payload,
        )

    async def get_automations(self, telegram_id: int) -> list[dict[str, Any]]:
        result = await self._request("GET", self._admin_path(telegram_id) + "/automations")
        automations = result.get("automations", [])
        return automations if isinstance(automations, list) else []

    async def update_automation(
        self,
        telegram_id: int,
        code: str,
        enabled: bool,
        interval_minutes: int,
    ) -> dict[str, Any]:
        return await self._request(
            "PATCH",
            self._admin_path(telegram_id) + f"/automations/{code}",
            payload={"enabled": bool(enabled), "interval_minutes": int(interval_minutes)},
        )

    async def run_automation(self, telegram_id: int, code: str) -> dict[str, Any]:
        return await self._request(
            "POST",
            self._admin_path(telegram_id) + f"/automations/{code}/run",
            payload={},
        )
