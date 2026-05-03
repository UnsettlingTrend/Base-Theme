#!/usr/bin/env python3
"""
rh_fetch_orders.py — UT Robinhood bridge script.

Authenticates with Robinhood via robin_stocks, fetches all stock orders,
annotates each order with its resolved ticker symbol, and writes a JSON
array to STDOUT. The Drupal RobinhoodOrderImporter service reads this output.

Credentials and configuration are read exclusively from environment variables
so they never appear in the process table or script arguments:

  RH_USERNAME    Robinhood account email
  RH_PASSWORD    Robinhood account password
  RH_MFA_CODE    Your TOTP *secret* (not a generated code) from Robinhood's
                 authenticator setup screen. If set, pyotp generates a fresh
                 6-digit code on every run. Leave empty to rely on an existing
                 valid pickle session instead.
  RH_PICKLE_DIR  Directory to store the auth session pickle file
  RH_ACCOUNT_IDS Comma-separated list of Robinhood account IDs. If set,
                 orders are fetched per-account instead of the default.
                 Example: "ABC123,DEF456"

Exit codes:
  0  Success — valid JSON array written to STDOUT
  1  Missing credentials
  2  Authentication failure
  3  API fetch failure
  4  Unexpected error

Usage (called by Drupal via proc_open — do not call manually in production):
  python3 rh_fetch_orders.py
"""

import json
import os
import sys
import traceback

# ------------------------------------------------------------------ #
# Resolve credentials from environment                                 #
# ------------------------------------------------------------------ #

username   = os.environ.get("RH_USERNAME", "").strip()
password   = os.environ.get("RH_PASSWORD", "").strip()
mfa_secret = os.environ.get("RH_MFA_CODE", "").strip() or None
pickle_dir = os.environ.get("RH_PICKLE_DIR", "").strip() or None
account_ids_raw = os.environ.get("RH_ACCOUNT_IDS", "").strip()
account_ids = [aid.strip() for aid in account_ids_raw.split(",") if aid.strip()] if account_ids_raw else []

# Generate a live TOTP code from the secret if one is configured.
# RH_MFA_CODE should hold the *secret* shown during Robinhood's authenticator
# setup (e.g. "JBSWY3DPEHPK3PXP"), not a pre-generated 6-digit code.
mfa_code = None
if mfa_secret:
    try:
        import pyotp
        mfa_code = pyotp.TOTP(mfa_secret).now()
    except ImportError:
        print(
            "ERROR: RH_MFA_CODE is set but pyotp is not installed. "
            "Run: pip install pyotp",
            file=sys.stderr,
        )
        sys.exit(4)
    except Exception as exc:
        print(f"ERROR: Failed to generate TOTP code: {exc}", file=sys.stderr)
        sys.exit(4)

if not username or not password:
    print(
        "ERROR: RH_USERNAME and RH_PASSWORD environment variables must be set.",
        file=sys.stderr,
    )
    sys.exit(1)

# ------------------------------------------------------------------ #
# Import robin_stocks                                                  #
# ------------------------------------------------------------------ #


try:
    import robin_stocks.robinhood as rh
except ImportError:
    print(
        "ERROR: robin_stocks is not installed. Run: pip install robin_stocks",
        file=sys.stderr,
    )
    sys.exit(4)

# ------------------------------------------------------------------ #
# Authenticate                                                         #
# ------------------------------------------------------------------ #

try:
    # pickle_name scopes the session file to this account so multiple
    # Robinhood accounts can coexist on the same server.
    pickle_name = f"ut_robinhood_{username.replace('@', '_').replace('.', '_')}"

    login_result = rh.login(
        username=username,
        password=password,
        expiresIn=86400,        # 24 hours
        scope="internal",
        #by_sms=True,            # prefer SMS for MFA challenge
        store_session=True,     # persist the session pickle
        mfa_code=mfa_code,
        pickle_name=pickle_name,
        # Override the default pickle storage path if configured.
        **({"pickle_path": pickle_dir} if pickle_dir else {}),
    )

    if not login_result or "access_token" not in login_result:
        print(
            f"ERROR: Login did not return an access token. Response: {login_result}",
            file=sys.stderr,
        )
        sys.exit(2)

except Exception as exc:
    print(f"ERROR: Authentication failed: {exc}", file=sys.stderr)
    traceback.print_exc(file=sys.stderr)
    sys.exit(2)

# ------------------------------------------------------------------ #
# Fetch stock orders                                                   #
# ------------------------------------------------------------------ #

def fetch_orders_for_account(account_id: str) -> list[dict]:
    """Fetch all stock orders for a specific Robinhood account ID.

    Uses the Robinhood orders endpoint filtered by account URL.
    Paginates through all result pages automatically.
    """
    from robin_stocks.robinhood.helper import request_get

    account_url = f"https://api.robinhood.com/accounts/{account_id}/"
    url = f"https://api.robinhood.com/orders/?account={account_url}"
    all_orders = []

    while url:
        response = request_get(url, dataType="regular")
        if response is None:
            break
        results = response.get("results", [])
        all_orders.extend(results)
        url = response.get("next")
        if url:
            print(f"Loading next page for account {account_id} ...", file=sys.stderr)

    return all_orders

try:
    if account_ids:
        # Fetch orders for each specified account ID and merge them.
        orders = []
        for aid in account_ids:
            print(f"Fetching orders for account {aid} ...", file=sys.stderr)
            account_orders = fetch_orders_for_account(aid)
            orders.extend(account_orders)
        print(f"Fetched {len(orders)} total orders across {len(account_ids)} account(s).", file=sys.stderr)
    else:
        # Default: fetch all orders from the primary account.
        orders = rh.get_all_stock_orders()

    if orders is None:
        print("ERROR: order fetch returned None.", file=sys.stderr)
        sys.exit(3)

except Exception as exc:
    print(f"ERROR: Failed to fetch stock orders: {exc}", file=sys.stderr)
    traceback.print_exc(file=sys.stderr)
    sys.exit(3)

# ------------------------------------------------------------------ #
# Resolve ticker symbols                                               #
# ------------------------------------------------------------------ #
# robin_stocks does not include the ticker symbol in the order object.
# We resolve each instrument URL once (cached in a dict) using
# get_symbol_by_url() to avoid N+1 API calls for duplicate instruments.

symbol_cache: dict[str, str] = {}

def resolve_symbol(instrument_url: str) -> str:
    """Return the ticker symbol for a Robinhood instrument URL."""
    if not instrument_url:
        return ""
    if instrument_url not in symbol_cache:
        try:
            symbol = rh.get_symbol_by_url(instrument_url)
            symbol_cache[instrument_url] = symbol or ""
        except Exception:
            symbol_cache[instrument_url] = ""
    return symbol_cache[instrument_url]


for order in orders:
    instrument_url = order.get("instrument", "")
    order["symbol"] = resolve_symbol(instrument_url)

# ------------------------------------------------------------------ #
# Output                                                               #
# ------------------------------------------------------------------ #

try:
    # ensure_ascii=False preserves any unicode in company names etc.
    output = json.dumps(orders, ensure_ascii=False, default=str)
    print(output)
except Exception as exc:
    print(f"ERROR: JSON serialisation failed: {exc}", file=sys.stderr)
    traceback.print_exc(file=sys.stderr)
    sys.exit(4)

# Logout is intentionally skipped here because the session is stored in the
# pickle file and reused on the next run. Calling logout() would invalidate
# the token, forcing a full re-authentication (including MFA) every cron run.
