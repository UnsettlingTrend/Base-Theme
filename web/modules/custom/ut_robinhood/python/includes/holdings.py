import robin_stocks as r
from includes import utils as u
from decimal import Decimal, ROUND_HALF_UP


def get_all_holdings():
    return r.build_holdings()


def get_all_holdings_current_values():
    holdings = r.build_holdings()
    holdings_values = {}
    for key in holdings.keys():
        holdings_values[key] = holdings.get(key).get('price')
    return holdings_values


def get_all_open_orders():
    data = {'stock': r.get_all_open_stock_orders(),
            'option': r.get_all_open_option_orders(),
            'crypto': r.get_all_open_crypto_orders()
            }
    return data


def get_all_stock_open_orders():
    open_orders = get_all_open_orders()
    data = {}
    data_keys = {'id', 'price', 'quantity', 'side', 'state', 'time_in_force', 'total_notional', 'trigger', 'type'}

    for order_type in open_orders:
        i = 0
        for order in order_type:
            data[order_type] = {}
            data[order_type][i] = order
            i += 1
    return open_orders


def get_all_data():
    holdings = get_all_holdings()
    data = {}
    data_keys = {'name', 'price', 'equity', 'equity_change'}
    for key in holdings.keys():
        data[key] = {}
        for data_key in data_keys:
            data[key][data_key] = holdings.get(key).get(data_key)
    return data
