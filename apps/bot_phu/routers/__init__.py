from apps.bot_phu.routers.admin import admin_router
from apps.bot_phu.routers.automation import automation_router
from apps.bot_phu.routers.common import common_router
from apps.bot_phu.routers.customer import customer_router
from apps.bot_phu.routers.errors import error_router
from apps.bot_phu.routers.support import support_router

__all__ = [
    "admin_router",
    "automation_router",
    "common_router",
    "customer_router",
    "error_router",
    "support_router",
]
