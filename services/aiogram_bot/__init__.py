"""Compatibility namespace; source moved to apps.bot_phu.

New code and deployments should import apps.bot_phu. This namespace keeps
previous import paths working during the directory migration.
"""
from apps.bot_phu import __path__ as __path__
