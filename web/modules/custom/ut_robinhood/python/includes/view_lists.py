import robin_stocks as r
from decimal import Decimal, ROUND_HALF_UP


def get_my_lists():
    r.login()
    watchlists = r.get_all_watchlists()
    return_data = {}

    # Load all watchlists
    for watchlist in watchlists['results']:
        list_name = watchlist['display_name']
        list_data = r.get_watchlist_by_name(list_name)
        return_data[list_name] = {}
        for item in list_data['results']:
            name = item['name']
            price = Decimal(r.get_latest_price(item['symbol'])[0]).quantize(Decimal('.01'), rounding=ROUND_HALF_UP)
            return_data[list_name][name] = price

    return return_data
