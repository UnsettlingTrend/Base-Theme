import robin_stocks as r
from decimal import Decimal, ROUND_HALF_UP


def get_all_orders():
    r.login()
