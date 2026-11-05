from __future__ import annotations

from typing import Any

from aiogram import F, Router, html
from aiogram.filters import Command
from aiogram.types import (
    CallbackQuery,
    InlineKeyboardButton,
    InlineKeyboardMarkup,
    Message,
)

from services.aiogram_bot.api_client import HendyApiClient

admin_router = Router(name="hendy-admin")


def admin_panel_keyboard() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(text="📊 Thống kê", callback_data="admin:stats"),
                InlineKeyboardButton(text="🎫 Ticket hỗ trợ", callback_data="admin:tickets"),
            ],
            [InlineKeyboardButton(text="⚙️ Tự động hóa", callback_data="automation:list")],
            [InlineKeyboardButton(text="↩️ Menu chính", callback_data="customer:home")],
        ]
    )


async def is_authorized(telegram_id: int, api: HendyApiClient) -> bool:
    return await api.is_admin(telegram_id)


async def send_admin_panel(message: Message, api: HendyApiClient) -> None:
    if not message.from_user or not await is_authorized(message.from_user.id, api):
        await message.answer("🔒 Tài khoản Telegram này không được cấp quyền quản trị.")
        return

    await message.answer(
        "🛠 <b>TRUNG TÂM QUẢN TRỊ HENDY</b>\n\n"
        "Chọn chức năng cần quản lý. Quyền quản trị được xác minh tại API trung tâm "
        "bằng ADMIN_TELEGRAM_ID; không dựa vào tên người dùng hoặc nút bấm phía client.",
        reply_markup=admin_panel_keyboard(),
    )


async def show_admin_tickets(message: Message, telegram_id: int, api: HendyApiClient) -> None:
    tickets = await api.get_admin_tickets(telegram_id)
    if not tickets:
        await message.answer("🎫 Hiện không có ticket nào.")
        return

    lines = ["🎫 <b>DANH SÁCH TICKET GẦN NHẤT</b>"]
    buttons: list[list[InlineKeyboardButton]] = []
    for ticket in tickets[:8]:
        ticket_id = int(ticket.get("id") or 0)
        status = str(ticket.get("status") or "open")
        subject = html.quote(str(ticket.get("subject") or "Không có tiêu đề")[:120])
        customer = html.quote(str(ticket.get("first_name") or ticket.get("username") or ticket.get("telegram_id") or "Khách hàng")[:80])
        lines.append(f"\n<b>#{ticket_id}</b> · {html.quote(status)} · {customer}\n{subject}")
        buttons.append([
            InlineKeyboardButton(
                text=f"#{ticket_id} · Xem / xử lý",
                callback_data=f"admin:ticket:{ticket_id}:view",
            )
        ])

    buttons.append([InlineKeyboardButton(text="↩️ Trung tâm quản trị", callback_data="admin:home")])
    await message.answer(
        "\n".join(lines),
        reply_markup=InlineKeyboardMarkup(inline_keyboard=buttons),
    )


async def show_ticket_detail(
    message: Message,
    ticket: dict[str, Any],
    telegram_id: int,
    api: HendyApiClient,
) -> None:
    ticket_id = int(ticket.get("id") or 0)
    customer = html.quote(str(ticket.get("first_name") or ticket.get("username") or ticket.get("telegram_id") or "Khách hàng"))
    text = (
        f"🎫 <b>TICKET #{ticket_id}</b>\n\n"
        f"<b>Khách hàng:</b> {customer}\n"
        f"<b>Telegram ID:</b> <code>{html.quote(str(ticket.get('telegram_id') or '—'))}</code>\n"
        f"<b>Tiêu đề:</b> {html.quote(str(ticket.get('subject') or ''))}\n"
        f"<b>Trạng thái:</b> {html.quote(str(ticket.get('status') or 'open'))}\n\n"
        f"<b>Nội dung:</b>\n{html.quote(str(ticket.get('message') or '')[:1800])}"
    )
    note = str(ticket.get("admin_note") or "").strip()
    if note:
        text += f"\n\n<b>Ghi chú quản trị:</b>\n{html.quote(note[:800])}"

    keyboard = InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(text="🟡 Đang xử lý", callback_data=f"admin:ticket:{ticket_id}:processing"),
                InlineKeyboardButton(text="✅ Đóng ticket", callback_data=f"admin:ticket:{ticket_id}:closed"),
            ],
            [InlineKeyboardButton(text="↩️ Danh sách ticket", callback_data="admin:tickets")],
        ]
    )
    await message.answer(text, reply_markup=keyboard)


@admin_router.message(Command("admin"), F.chat.type == "private")
@admin_router.message(F.text == "🛠 Quản trị", F.chat.type == "private")
async def admin_home(message: Message, api: HendyApiClient) -> None:
    await send_admin_panel(message, api)


@admin_router.callback_query(F.data == "admin:home")
async def admin_home_callback(callback: CallbackQuery, api: HendyApiClient) -> None:
    if not await is_authorized(callback.from_user.id, api):
        await callback.answer("Bạn không có quyền quản trị.", show_alert=True)
        return
    await callback.answer()
    if isinstance(callback.message, Message):
        await send_admin_panel(callback.message, api)


@admin_router.callback_query(F.data == "admin:stats")
async def admin_stats(callback: CallbackQuery, api: HendyApiClient) -> None:
    if not await is_authorized(callback.from_user.id, api):
        await callback.answer("Bạn không có quyền quản trị.", show_alert=True)
        return
    await callback.answer()
    result = await api.get_admin_stats(callback.from_user.id)
    stats = result.get("stats", {})
    if isinstance(callback.message, Message):
        await callback.message.answer(
            "📊 <b>TỔNG QUAN HỆ THỐNG</b>\n\n"
            f"👥 Khách hàng: <b>{stats.get('users', 0)}</b>\n"
            f"🎫 Ticket chưa đóng: <b>{stats.get('open_tickets', 0)}</b>\n"
            f"🛍 Dịch vụ: <b>{stats.get('services', 0)}</b>\n"
            f"💰 Tổng số dư ví: <b>{html.quote(str(stats.get('wallet_total', '0')))} đ</b>",
            reply_markup=admin_panel_keyboard(),
        )


@admin_router.callback_query(F.data == "admin:tickets")
async def admin_tickets(callback: CallbackQuery, api: HendyApiClient) -> None:
    if not await is_authorized(callback.from_user.id, api):
        await callback.answer("Bạn không có quyền quản trị.", show_alert=True)
        return
    await callback.answer()
    if isinstance(callback.message, Message):
        await show_admin_tickets(callback.message, callback.from_user.id, api)


@admin_router.callback_query(F.data.startswith("admin:ticket:"))
async def admin_ticket_action(callback: CallbackQuery, api: HendyApiClient) -> None:
    if not await is_authorized(callback.from_user.id, api):
        await callback.answer("Bạn không có quyền quản trị.", show_alert=True)
        return

    parts = str(callback.data or "").split(":")
    if len(parts) != 4 or not parts[2].isdigit():
        await callback.answer("Thao tác ticket không hợp lệ.", show_alert=True)
        return

    ticket_id = int(parts[2])
    action = parts[3]
    if action == "view":
        tickets = await api.get_admin_tickets(callback.from_user.id)
        ticket = next((item for item in tickets if int(item.get("id") or 0) == ticket_id), None)
        if not ticket:
            await callback.answer("Không tìm thấy ticket trong danh sách gần nhất.", show_alert=True)
            return
        await callback.answer()
        if isinstance(callback.message, Message):
            await show_ticket_detail(callback.message, ticket, callback.from_user.id, api)
        return

    if action not in {"processing", "closed"}:
        await callback.answer("Trạng thái ticket không hợp lệ.", show_alert=True)
        return

    await api.update_admin_ticket(callback.from_user.id, ticket_id, action)
    await callback.answer("Đã cập nhật ticket.")
    if isinstance(callback.message, Message):
        await show_admin_tickets(callback.message, callback.from_user.id, api)
