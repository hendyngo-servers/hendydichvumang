from services.aiogram_bot.routers.common import common_router
from services.aiogram_bot.routers.customer import customer_router
from services.aiogram_bot.routers.errors import error_router
from services.aiogram_bot.routers.support import support_router

__all__ = ["common_router", "customer_router", "error_router", "support_router"]
