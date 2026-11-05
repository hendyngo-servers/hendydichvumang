from __future__ import annotations

from aiogram import F, Router, html
from aiogram.filters import Command
from aiogram.types import (
    CallbackQuery,
    InlineKeyboardButton,
    InlineKeyboardMarkup,
    Message,
)

from apps.bot_phu.api_client import HendyApiClient
from apps.bot_phu.menu_filter import MenuActionFilter

automation_router = Router(name="hendy-automation")
AUTOMATION_CODES = frozenset({
    "cleanup_sessions",
    "notify_new_tickets",
    "remind_open_tickets",
})


async def _authorized(telegram_id: int, api: HendyApiClient) -> bool:
    return await api.is_admin(telegram_id)


def automation_keyboard(items: list[dict]) -> InlineKeyboardMarkup:
    rows: list[list[InlineKeyboardButton]] = []
    for item in items[:10]:
        code = str(item.get("code") or "")
        if code not in AUTOMATION_CODES:
            continue
        enabled = item.get("enabled") is True
        rows.append([
            InlineKeyboardButton(
                text="🔴 Tắt" if enabled else "🟢 Bật",
                callback_data=f"automation:toggle:{code}",
            ),
            InlineKeyboardButton(
                text="▶️ Chạy",
                callback_data=f"automation:confirm:{code}",
            ),
        ])
    rows.append([InlineKeyboardButton(text="↩️ Trung tâm quản trị", callback_data="admin:home")])
    return InlineKeyboardMarkup(inline_keyboard=rows)


async def show_automations(message: Message, telegram_id: int, api: HendyApiClient) -> None:
    items = await api.get_automations(telegram_id)
    if not items:
        await message.answer("⚙️ Chưa có tác vụ tự động hóa nào được cấu hình.")
        return

    labels = {
        "cleanup_sessions": "Dọn dữ liệu hết hạn",
        "notify_new_tickets": "Thông báo ticket mới",
        "remind_open_tickets": "Nhắc ticket đang chờ",
    }
    lines = ["⚙️ <b>QUẢN LÝ TỰ ĐỘNG HÓA</b>"]
    for item in items[:10]:
        code = str(item.get("code") or "")
        if code not in AUTOMATION_CODES:
            continue
        enabled = item.get("enabled") is True
        status = "ĐANG BẬT" if enabled else "ĐANG TẮT"
        interval = int(item.get("interval_minutes") or 0)
        last_status = html.quote(str(item.get("last_status") or "never"))
        description = html.quote(str(item.get("description") or "")[:180])
        lines.append(
            f"\n<b>{html.quote(labels.get(code, code))}</b> · {status}\n"
            f"Chu kỳ: {interval} phút · Lần chạy gần nhất: {last_status}\n"
            f"{description}"
        )

    await message.answer(
        "\n".join(lines),
        reply_markup=automation_keyboard(items),
    )


@automation_router.message(Command("automation"), F.chat.type == "private")
@automation_router.message(MenuActionFilter("automation"), F.chat.type == "private")
async def automation_command(message: Message, api: HendyApiClient) -> None:
    if not message.from_user or not await _authorized(message.from_user.id, api):
        await message.answer("🔒 Chức năng này chỉ dành cho quản trị viên HENDY.")
        return
    await show_automations(message, message.from_user.id, api)


@automation_router.callback_query(F.data.startswith("automation:"))
async def automation_callback(callback: CallbackQuery, api: HendyApiClient) -> None:
    if not await _authorized(callback.from_user.id, api):
        await callback.answer("Bạn không có quyền quản trị.", show_alert=True)
        return

    parts = str(callback.data or "").split(":")
    action = parts[1] if len(parts) > 1 else ""
    code = parts[2] if len(parts) > 2 else ""

    if action == "list":
        await callback.answer()
        if isinstance(callback.message, Message):
            await show_automations(callback.message, callback.from_user.id, api)
        return

    if code not in AUTOMATION_CODES:
        await callback.answer("Mã tác vụ không hợp lệ.", show_alert=True)
        return

    if action == "confirm":
        await callback.answer()
        if isinstance(callback.message, Message):
            await callback.message.answer(
                f"Bạn xác nhận chạy tác vụ <b>{html.quote(code)}</b> ngay bây giờ?",
                reply_markup=InlineKeyboardMarkup(inline_keyboard=[
                    [
                        InlineKeyboardButton(text="✅ Xác nhận chạy", callback_data=f"automation:run:{code}"),
                        InlineKeyboardButton(text="Hủy", callback_data="automation:list"),
                    ]
                ]),
            )
        return

    if action == "toggle":
        items = await api.get_automations(callback.from_user.id)
        item = next((entry for entry in items if entry.get("code") == code), None)
        if not item:
            await callback.answer("Không tìm thấy tác vụ.", show_alert=True)
            return
        enabled = item.get("enabled") is not True
        interval = int(item.get("interval_minutes") or 60)
        await api.update_automation(callback.from_user.id, code, enabled, interval)
        await callback.answer("Đã cập nhật trạng thái.")
        if isinstance(callback.message, Message):
            await show_automations(callback.message, callback.from_user.id, api)
        return

    if action == "run":
        await callback.answer("Đang chạy tác vụ…")
        result = await api.run_automation(callback.from_user.id, code)
        outcome = result.get("result", {})
        status = str(outcome.get("status") or "unknown")
        detail = outcome.get("detail", {})
        if isinstance(callback.message, Message):
            if status == "success":
                summary = "Tác vụ đã chạy thành công."
            elif status == "skipped":
                summary = "Tác vụ đã bỏ qua theo cấu hình hiện tại."
            elif status == "busy":
                summary = "Tác vụ đang chạy ở một tiến trình khác."
            else:
                summary = f"Tác vụ kết thúc với trạng thái: {html.quote(status)}."
            await callback.message.answer(
                f"⚙️ <b>KẾT QUẢ TỰ ĐỘNG HÓA</b>\n\n"
                f"{summary}\n"
                f"Mã tác vụ: <code>{html.quote(code)}</code>",
                reply_markup=InlineKeyboardMarkup(inline_keyboard=[
                    [InlineKeyboardButton(text="↻ Làm mới", callback_data="automation:list")],
                    [InlineKeyboardButton(text="↩️ Trung tâm quản trị", callback_data="admin:home")],
                ]),
            )
        return

    await callback.answer("Thao tác không hợp lệ.", show_alert=True)
