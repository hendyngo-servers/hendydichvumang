from __future__ import annotations

from aiogram import F, Router, html
from aiogram.filters import Command, CommandStart
from aiogram.types import (
    KeyboardButton,
    Message,
    ReplyKeyboardMarkup,
    ReplyKeyboardRemove,
    WebAppInfo,
)


from services.aiogram_bot.config import Settings
from services.aiogram_bot.api_client import HendyApiClient
from services.aiogram_bot.menu_config import build_menu_rows, normalize_menu_config

common_router = Router(name="hendy-common")


def main_menu(
    settings: Settings,
    menu_config: dict | None = None,
    is_admin: bool = False,
) -> ReplyKeyboardMarkup:
    config = normalize_menu_config(menu_config)
    rows = [
        [KeyboardButton(text=button_text) for button_text in row]
        for row in build_menu_rows(config, is_admin)
    ]
    if settings.public_app_url.startswith("https://"):
        rows.append([KeyboardButton(
            text="🌐 Mở Mini App",
            web_app=WebAppInfo(url=settings.public_app_url),
        )])
    return ReplyKeyboardMarkup(
        keyboard=rows,
        resize_keyboard=config["resizeKeyboard"],
        is_persistent=config["persistentKeyboard"],
        input_field_placeholder="Chọn chức năng HENDY…",
    )


@common_router.message(CommandStart(), F.chat.type == "private")
async def command_start(
    message: Message,
    settings: Settings,
    hendy_menu_config: dict | None = None,
    hendy_is_admin: bool = False,
) -> None:
    name = html.quote(message.from_user.first_name if message.from_user else "bạn")
    config = normalize_menu_config(hendy_menu_config)
    intro = html.quote(config["introText"])
    await message.answer(
        f"💠 <b>HENDY DỊCH VỤ MẠNG</b>\n\n"
        f"Chào mừng <b>{name}</b>!\n"
        "Mở Trung tâm khách hàng để quản lý tài khoản, xem dịch vụ và gửi yêu cầu hỗ trợ.\n\n"
        + intro,
        reply_markup=main_menu(settings, config, is_admin=hendy_is_admin),
    )


@common_router.message(Command("help"), F.chat.type == "private")
async def command_help(
    message: Message,
    settings: Settings,
    hendy_menu_config: dict | None = None,
    hendy_is_admin: bool = False,
) -> None:
    await message.answer(
        "<b>HƯỚNG DẪN HENDY</b>\n\n"
        "/start — Mở menu\n"
        "/customer — Trung tâm khách hàng\n"
        "/balance — Kiểm tra số dư\n"
        "/services — Danh sách dịch vụ\n"
        "/support — Tạo ticket hỗ trợ\n"
        "/cancel — Hủy quy trình đang nhập\n"
        "/admin — Trung tâm quản trị (chỉ quản trị viên)\n"
        "/automation — Quản lý tác vụ tự động hóa",
        reply_markup=main_menu(settings, hendy_menu_config, is_admin=hendy_is_admin),
    )


@common_router.message(Command("cancel"), F.chat.type == "private")
async def command_cancel(message: Message, state) -> None:
    await state.clear()
    await message.answer("Đã hủy thao tác hiện tại.", reply_markup=ReplyKeyboardRemove())


@common_router.message(F.chat.type != "private")
async def private_chat_only(message: Message) -> None:
    await message.answer("🔐 Vui lòng nhắn tin riêng với bot để sử dụng chức năng tài khoản HENDY.")
