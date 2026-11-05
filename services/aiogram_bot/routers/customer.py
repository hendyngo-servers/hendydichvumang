from __future__ import annotations

from decimal import Decimal, InvalidOperation

from aiogram import F, Router, html
from aiogram.filters import Command
from aiogram.types import (
    CallbackQuery,
    InlineKeyboardButton,
    InlineKeyboardMarkup,
    Message,
    WebAppInfo,
)

from services.aiogram_bot.api_client import HendyApiClient
from services.aiogram_bot.config import Settings
from services.aiogram_bot.routers.common import main_menu
from services.aiogram_bot.menu_filter import MenuActionFilter

customer_router = Router(name="hendy-customer")


def customer_center_keyboard(settings: Settings) -> InlineKeyboardMarkup:
    rows = [
        [InlineKeyboardButton(
            text="🚀 MINI APP BẢN MỚI | CHỈ MOBILE",
            web_app=WebAppInfo(url=settings.public_app_url),
        )],
    ]
    if settings.legacy_customer_url:
        rows.append([InlineKeyboardButton(
            text="🔑 PHIÊN BẢN WEB CŨ",
            url=settings.legacy_customer_url,
        )])
    rows.append([InlineKeyboardButton(text="↩️ TRỞ VỀ MENU CHÍNH", callback_data="customer:home")])
    return InlineKeyboardMarkup(inline_keyboard=rows)


def format_vnd(value: object) -> str:
    try:
        amount = Decimal(str(value or "0"))
    except (InvalidOperation, ValueError):
        amount = Decimal("0")
    return f"{amount:,.0f}".replace(",", ".") + " đ"


async def send_customer_center(message: Message, settings: Settings) -> None:
    legacy_text = (
        "• <b>Phiên bản Web cũ:</b> tiếp tục sử dụng trang web truyền thống."
        if settings.legacy_customer_url
        else "• <b>Phiên bản Web cũ:</b> hiện chưa được cấu hình."
    )
    await message.answer(
        "🚀 <b>TRUNG TÂM QUẢN LÝ TÀI KHOẢN</b>\n\n"
        "Chọn một trong hai cách truy cập bên dưới:\n"
        "• <b>Mini App bản mới:</b> tối ưu cho Telegram trên điện thoại, tự xác thực bằng tài khoản Telegram.\n"
        + legacy_text
        + "\n\nKhuyến nghị sử dụng Mini App để quản lý tài khoản và yêu cầu hỗ trợ.",
        reply_markup=customer_center_keyboard(settings),
    )


@customer_router.callback_query(F.data == "customer:home")
async def customer_home(callback: CallbackQuery, settings: Settings, api: HendyApiClient) -> None:
    await callback.answer("Đã mở menu chính.")
    if callback.message:
        await callback.message.answer(
            "💠 Menu chính HENDY. Chọn chức năng bạn cần:",
            reply_markup=main_menu(
                settings,
                await api.get_menu_config("aiogram"),
                is_admin=await api.is_admin(callback.from_user.id),
            ),
        )

@customer_router.message(Command("customer"), F.chat.type == "private")
async def customer_command(message: Message, settings: Settings) -> None:
    await send_customer_center(message, settings)


@customer_router.message(MenuActionFilter("customer"), F.chat.type == "private")
async def menu_customer(message: Message, settings: Settings) -> None:
    await send_customer_center(message, settings)


@customer_router.message(Command("account"), F.chat.type == "private")
async def account_alias(message: Message, settings: Settings) -> None:
    await send_customer_center(message, settings)


@customer_router.message(Command("balance"), F.chat.type == "private")
async def account_balance(message: Message, api: HendyApiClient) -> None:
    assert message.from_user is not None
    result = await api.get_balance(message.from_user.id)
    await message.answer(
        "💳 <b>SỐ DƯ TÀI KHOẢN</b>\n\n"
        f"Số dư hiện tại: <b>{format_vnd(result.get('balance'))}</b>\n\n"
        "Số dư chỉ thay đổi sau khi giao dịch được hệ thống đối soát hợp lệ."
    )


@customer_router.message(Command("services"), F.chat.type == "private")
@customer_router.message(MenuActionFilter("services"), F.chat.type == "private")
async def list_services(message: Message, api: HendyApiClient) -> None:
    services = await api.get_services()
    if not services:
        await message.answer("Hiện chưa có dịch vụ nào được quản trị viên công khai.")
        return

    lines: list[str] = ["🛍 <b>DANH SÁCH DỊCH VỤ HENDY</b>\n"]
    for item in services[:15]:
        name = str(item.get("name") or "Dịch vụ")
        category = str(item.get("category") or "Khác")
        description = str(item.get("description") or "")
        price = format_vnd(item.get("price"))
        lines.append(
            f"• <b>{html.quote(name)}</b> ({html.quote(category)})\n"
            f"  {html.quote(description)}\n"
            f"  Giá tham khảo: <b>{price}</b>"
        )
    await message.answer("\n\n".join(lines))
