from __future__ import annotations

from aiogram import F, Router, html
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import KeyboardButton, Message, ReplyKeyboardMarkup, ReplyKeyboardRemove

from apps.bot_phu.api_client import HendyApiError
from apps.bot_phu.menu_filter import MenuActionFilter

support_router = Router(name="hendy-support")


class TicketForm(StatesGroup):
    subject = State()
    message = State()


def cancel_keyboard() -> ReplyKeyboardMarkup:
    return ReplyKeyboardMarkup(
        keyboard=[[KeyboardButton(text="❌ Hủy")]],
        resize_keyboard=True,
        one_time_keyboard=True,
    )


@support_router.message(Command("support"), F.chat.type == "private")
@support_router.message(MenuActionFilter("support"), F.chat.type == "private")
async def start_ticket(message: Message, state: FSMContext) -> None:
    await state.clear()
    await state.set_state(TicketForm.subject)
    await message.answer(
        "🎫 <b>TẠO YÊU CẦU HỖ TRỢ</b>\n\n"
        "Bước 1/2 — Nhập tiêu đề yêu cầu (3–160 ký tự).\n"
        "Không gửi mật khẩu, token hoặc mã OTP.",
        reply_markup=cancel_keyboard(),
    )


@support_router.message(TicketForm.subject, F.text.casefold() == "❌ hủy")
@support_router.message(TicketForm.message, F.text.casefold() == "❌ hủy")
async def cancel_ticket(message: Message, state: FSMContext) -> None:
    await state.clear()
    await message.answer("Đã hủy yêu cầu hỗ trợ.", reply_markup=ReplyKeyboardRemove())


@support_router.message(TicketForm.subject, F.text)
async def ticket_subject(message: Message, state: FSMContext) -> None:
    subject = (message.text or "").strip()
    if not 3 <= len(subject) <= 160:
        await message.answer("Tiêu đề phải có từ 3 đến 160 ký tự. Vui lòng nhập lại.")
        return
    await state.update_data(subject=subject)
    await state.set_state(TicketForm.message)
    await message.answer(
        "Bước 2/2 — Mô tả vấn đề (5–3.000 ký tự).",
        reply_markup=cancel_keyboard(),
    )


@support_router.message(TicketForm.subject)
async def ticket_subject_text_only(message: Message) -> None:
    await message.answer("Vui lòng gửi tiêu đề bằng tin nhắn văn bản.")


@support_router.message(TicketForm.message, F.text)
async def ticket_message(message: Message, state: FSMContext, api) -> None:
    body = (message.text or "").strip()
    if not 5 <= len(body) <= 3000:
        await message.answer("Nội dung cần từ 5 đến 3.000 ký tự. Vui lòng nhập lại.")
        return
    assert message.from_user is not None
    data = await state.get_data()
    try:
        result = await api.create_ticket(
            message.from_user.id,
            str(data.get("subject") or ""),
            body,
        )
    except HendyApiError as exc:
        await message.answer(
            f"Chưa gửi được yêu cầu: {html.quote(str(exc))}. "
            "Bạn có thể thử lại hoặc gửi /cancel."
        )
        return

    await state.clear()
    ticket = result.get("ticket", {})
    subject = html.quote(str(data.get("subject") or ""))
    await message.answer(
        "✅ <b>ĐÃ GHI NHẬN YÊU CẦU</b>\n\n"
        f"Mã ticket: <b>#{ticket.get('id', '—')}</b>\n"
        f"Tiêu đề: <b>{subject}</b>\n"
        "Đội ngũ HENDY sẽ tiếp nhận và phản hồi trong Trung tâm khách hàng.",
        reply_markup=ReplyKeyboardRemove(),
    )


@support_router.message(TicketForm.message)
async def ticket_message_text_only(message: Message) -> None:
    await message.answer("Vui lòng mô tả vấn đề bằng tin nhắn văn bản.")
