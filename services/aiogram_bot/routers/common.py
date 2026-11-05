from __future__ import annotations

from aiogram import F, Router, html
from aiogram.filters import Command, CommandStart
from aiogram.types import (
    KeyboardButton,
    Message,
    ReplyKeyboardMarkup,
    ReplyKeyboardRemove,
)


from services.aiogram_bot.config import Settings

common_router = Router(name="hendy-common")


def main_menu(settings: Settings) -> ReplyKeyboardMarkup:
    rows = [
        [KeyboardButton(text="👤 Trung tâm khách hàng")],
        [KeyboardButton(text="💳 Số dư"), KeyboardButton(text="🛍 Dịch vụ")],
        [KeyboardButton(text="🎫 Hỗ trợ")],
    ]
    return ReplyKeyboardMarkup(
        keyboard=rows,
        resize_keyboard=True,
        is_persistent=True,
        input_field_placeholder="Chọn chức năng HENDY…",
    )


@common_router.message(CommandStart(), F.chat.type == "private")
async def command_start(message: Message, settings: Settings) -> None:
    name = html.quote(message.from_user.first_name if message.from_user else "bạn")
    await message.answer(
        f"💠 <b>HENDY DỊCH VỤ MẠNG</b>\n\n"
        f"Chào mừng <b>{name}</b>!\n"
        "Mở Trung tâm khách hàng để quản lý tài khoản, xem dịch vụ và gửi yêu cầu hỗ trợ.",
        reply_markup=main_menu(settings),
    )


@common_router.message(Command("help"), F.chat.type == "private")
async def command_help(message: Message, settings: Settings) -> None:
    await message.answer(
        "<b>HƯỚNG DẪN HENDY</b>\n\n"
        "/start — Mở menu\n"
        "/customer — Trung tâm khách hàng\n"
        "/balance — Kiểm tra số dư\n"
        "/services — Danh sách dịch vụ\n"
        "/support — Tạo ticket hỗ trợ\n"
        "/cancel — Hủy quy trình đang nhập",
        reply_markup=main_menu(settings),
    )


@common_router.message(Command("cancel"), F.chat.type == "private")
async def command_cancel(message: Message, state) -> None:
    await state.clear()
    await message.answer("Đã hủy thao tác hiện tại.", reply_markup=ReplyKeyboardRemove())


@common_router.message(F.chat.type != "private")
async def private_chat_only(message: Message) -> None:
    await message.answer("🔐 Vui lòng nhắn tin riêng với bot để sử dụng chức năng tài khoản HENDY.")
