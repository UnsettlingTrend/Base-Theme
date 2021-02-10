import robin_stocks as r
import pprint
import os
from includes import holdings as h
from includes import orders as o
from includes import utils as u
from yaml import load, dump


# Attempt to open the settings file
try:
    from yaml import CLoader as Loader, CDumper as Dumper
    stream = open('config/settings.yml', 'r')
    settings = load(stream, Loader=Loader)
except ImportError:
    from yaml import Loader, Dumper

# Login for the current session
r.login(settings.get('account_username'), settings.get('account_password'))

# Do stuff
pp = pprint.PrettyPrinter(indent=2)

#print(r.build_holdings)
#print(r.get_all_open_crypto_orders)
pp.pprint(h.get_all_stock_open_orders())

#pp.pprint(h.get_all_data())
#pp.pprint(h.get_all_stock_open_orders())
