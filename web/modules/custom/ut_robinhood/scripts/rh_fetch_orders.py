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
   RH_START_DATE  Optional start date (YYYY-MM-DD) to limit the fetch to
                  orders from that date onward. If empty, all orders are
                  fetched. Set automatically by the Drupal importer based
                  on the last successful import.
   RH_MFA_WAIT    Seconds to wait for the user to approve a push-notification
                  MFA challenge on their phone. Defaults to 15. Set to 0 to
                  skip the wait (useful when a valid session pickle exists).

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
import time
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
start_date = os.environ.get("RH_START_DATE", "").strip() or None
mfa_wait   = int(os.environ.get("RH_MFA_WAIT", "15"))

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
    from robin_stocks.robinhood.helper import set_output
    # Redirect robin_stocks' internal print() calls (e.g. error messages from
    # request_post) to stderr so they don't contaminate the JSON on stdout.
    set_output(sys.stderr)
    # robin_stocks 3.4.0 also prints directly via print() in authentication.py.
    # Redirect all stdout prints from the login flow to stderr by temporarily
    # reassigning sys.stdout during import/login.
except ImportError:
    print(
        "ERROR: robin_stocks is not installed. Run: pip install robin_stocks",
        file=sys.stderr,
    )
    sys.exit(4)

# Override the default User-Agent header. robin_stocks ships with
# User-Agent: * which Robinhood's CDN / WAF rejects with a 502.
try:
    from robin_stocks.robinhood.globals import SESSION
    SESSION.headers["User-Agent"] = (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/136.0.0.0 Safari/537.36"
    )
except Exception as exc:
    print(f"Warning: Could not set User-Agent: {exc}", file=sys.stderr)

print(f"robin_stocks version: {getattr(rh, '__version__', 'unknown')}", file=sys.stderr)

# ------------------------------------------------------------------ #
# Headless MFA / challenge support                                     #
# ------------------------------------------------------------------ #
# robin_stocks calls Python's built-in input() when Robinhood sends a
# device-verification challenge (SMS/email code) or requests an MFA
# code interactively.  In a headless subprocess (proc_open with stdin
# closed) input() would raise EOFError immediately.
#
# We monkey-patch input() so that when robin_stocks prompts for a code
# we pause for RH_MFA_WAIT seconds, giving the user time to approve
# the push notification on their phone, then return the TOTP code (if
# available) or an empty string (for push-style challenges that only
# need approval, not a typed code).

_original_input = input

def _headless_input(prompt=""):
    """Replacement for input() that waits for MFA push approval."""
    print(f"[MFA] Prompt intercepted: {prompt}", file=sys.stderr)
    if mfa_wait > 0:
        print(
            f"[MFA] Waiting {mfa_wait}s for push-notification approval ...",
            file=sys.stderr,
        )
        time.sleep(mfa_wait)
    # If a TOTP code is available, supply it; otherwise send empty
    # string (acceptable for push-only challenges).
    code = mfa_code or ""
    print(f"[MFA] Responding with {'TOTP code' if mfa_code else 'empty string'}.", file=sys.stderr)
    return code

import builtins
builtins.input = _headless_input

# ------------------------------------------------------------------ #
# Stable device token                                                  #
# ------------------------------------------------------------------ #
# robin_stocks generates a random device_token on every login call.
# Robinhood associates device tokens with verified devices — using a new
# random token each time forces a fresh device-verification challenge and
# may cause "Unable to log in with provided credentials" rejections.
#
# We monkey-patch generate_device_token() to return a stable token that
# is derived from the username and persisted alongside the pickle file.
# Once Robinhood recognises this device token, subsequent logins reuse it.

import robin_stocks.robinhood.authentication as _rh_auth
import hashlib, uuid

def _stable_device_token() -> str:
    """Return a stable device token, persisted to a file next to the pickle."""
    token_dir = pickle_dir or os.path.join(os.path.expanduser("~"), ".tokens")
    os.makedirs(token_dir, exist_ok=True)
    token_file = os.path.join(token_dir, f"device_token_{username}.txt")
    if os.path.isfile(token_file):
        token = open(token_file).read().strip()
        if token:
            print(f"Using persisted device token from {token_file}", file=sys.stderr)
            return token
    # Derive a deterministic UUID from the username so the same account
    # always gets the same device token even if the file is lost.
    token = str(uuid.uuid5(uuid.NAMESPACE_DNS, f"robinhood-{username}"))
    with open(token_file, "w") as f:
        f.write(token)
    print(f"Generated and saved new device token to {token_file}", file=sys.stderr)
    return token

_rh_auth.generate_device_token = _stable_device_token

# ------------------------------------------------------------------ #
# Patch robin_stocks 3.4.0 login bug                                   #
# ------------------------------------------------------------------ #
# robin_stocks 3.4.0 has a bug where it tries to pickle data['token_type']
# unconditionally after the verification workflow, even when the response
# doesn't contain an access_token. We wrap the login function to catch
# this and also add diagnostic logging.

_original_login = rh.login

def _patched_login(**kwargs):
    """Wrap rh.login() to fix 3.4.0 pickle bug and add diagnostics."""
    import robin_stocks.robinhood.helper as _helper
    from robin_stocks.robinhood.urls import login_url
    import robin_stocks.robinhood.authentication as _auth
    import pickle as _pickle

    # Call the original but intercept request_post to capture responses.
    _orig_request_post = _helper.request_post
    _last_responses = []

    def _logging_request_post(url, payload=None, timeout=16, json=False, jsonify_data=True):
        result = _orig_request_post(url, payload, timeout, json, jsonify_data)
        print(f"[DEBUG] request_post {url} → {result if isinstance(result, dict) else type(result).__name__}", file=sys.stderr)
        _last_responses.append(result)
        return result

    _helper.request_post = _logging_request_post
    # Also patch in the authentication module's namespace.
    _auth.request_post = _logging_request_post

    try:
        result = _original_login(**kwargs)
    except KeyError as e:
        # robin_stocks 3.4.0 bug: KeyError('token_type') when response
        # doesn't contain access_token after verification workflow.
        print(f"[DEBUG] Caught KeyError in login: {e}", file=sys.stderr)
        print(f"[DEBUG] Last responses: {[list(r.keys()) if isinstance(r, dict) else r for r in _last_responses]}", file=sys.stderr)
        # Check if any response had access_token.
        for resp in reversed(_last_responses):
            if isinstance(resp, dict) and 'access_token' in resp:
                print("[DEBUG] Found access_token in a prior response, saving session.", file=sys.stderr)
                from robin_stocks.robinhood.helper import update_session, set_login_state
                token = f"{resp['token_type']} {resp['access_token']}"
                update_session('Authorization', token)
                set_login_state(True)
                # Save pickle manually.
                p_dir = kwargs.get('pickle_path') or os.path.join(os.path.expanduser("~"), ".tokens")
                p_name = "robinhood" + kwargs.get('pickle_name', '') + ".pickle"
                p_path = os.path.join(p_dir, p_name)
                with open(p_path, 'wb') as f:
                    _pickle.dump({
                        'token_type': resp['token_type'],
                        'access_token': resp['access_token'],
                        'refresh_token': resp.get('refresh_token', ''),
                        'device_token': _stable_device_token(),
                    }, f)
                result = resp
                break
        else:
            result = None
    finally:
        _helper.request_post = _orig_request_post
        _auth.request_post = _orig_request_post

    return result

rh.login = _patched_login

# ------------------------------------------------------------------ #
# Authenticate                                                         #
# ------------------------------------------------------------------ #

try:
    # pickle_name scopes the session file to this account so multiple
    # Robinhood accounts can coexist on the same server.
    pickle_name = f"ut_robinhood_{username.replace('@', '_').replace('.', '_')}"

    # Log the authentication attempt for diagnostics.
    pickle_path_check = os.path.join(
        pickle_dir or os.path.join(os.path.expanduser("~"), ".tokens"),
        "robinhood" + pickle_name + ".pickle",
    )
    print(f"Pickle path: {pickle_path_check}", file=sys.stderr)
    print(f"Pickle exists: {os.path.isfile(pickle_path_check)}", file=sys.stderr)
    print(f"MFA code provided: {bool(mfa_code)}", file=sys.stderr)
    print(f"MFA wait: {mfa_wait}s", file=sys.stderr)

    # robin_stocks 3.4.0 uses bare print() in authentication.py for status
    # messages. Temporarily redirect stdout → stderr so those messages don't
    # contaminate the JSON output on stdout.
    _real_stdout = sys.stdout
    sys.stdout = sys.stderr

    login_result = rh.login(
        username=username,
        password=password,
        expiresIn=86400,        # 24 hours
        scope="internal",
        store_session=True,     # persist the session pickle
        mfa_code=mfa_code,
        pickle_name=pickle_name,
        # Override the default pickle storage path if configured.
        **({"pickle_path": pickle_dir} if pickle_dir else {}),
    )

    # Restore stdout so our JSON output goes to the right place.
    sys.stdout = _real_stdout

    if not login_result or "access_token" not in login_result:
        # Provide actionable diagnostics depending on the response.
        if login_result is None:
            msg = (
                "ERROR: Login returned None. This usually means the HTTP "
                "request to Robinhood failed entirely (network/firewall "
                "issue or robin_stocks could not reach the API)."
            )
        elif isinstance(login_result, dict):
            msg = (
                f"ERROR: Login response missing access_token. "
                f"Keys present: {list(login_result.keys())}. "
                f"Detail: {login_result.get('detail', 'N/A')}"
            )
        else:
            msg = f"ERROR: Login returned unexpected type {type(login_result).__name__}: {login_result}"
        print(msg, file=sys.stderr)
        sys.exit(2)

except Exception as exc:
    sys.stdout = _real_stdout
    print(f"ERROR: Authentication failed: {exc}", file=sys.stderr)
    traceback.print_exc(file=sys.stderr)
    sys.exit(2)

# ------------------------------------------------------------------ #
# Fetch stock orders                                                   #
# ------------------------------------------------------------------ #

try:
    if start_date:
        print(f"Incremental import: fetching orders from {start_date} onward.", file=sys.stderr)
    else:
        print("Full import: fetching all orders.", file=sys.stderr)

    if account_ids:
        # Fetch orders for each specified account ID and merge them.
        orders = []
        for aid in account_ids:
            print(f"Fetching orders for account {aid} ...", file=sys.stderr)
            account_orders = rh.get_all_stock_orders(account_number=aid, start_date=start_date)
            if account_orders:
                orders.extend(account_orders)
        print(f"Fetched {len(orders)} total orders across {len(account_ids)} account(s).", file=sys.stderr)
    else:
        # Default: fetch all orders from the primary account.
        orders = rh.get_all_stock_orders(start_date=start_date)

    if orders is None:
        print("ERROR: order fetch returned None.", file=sys.stderr)
        sys.exit(3)

except Exception as exc:
    print(f"ERROR: Failed to fetch stock orders: {exc}", file=sys.stderr)
    traceback.print_exc(file=sys.stderr)
    sys.exit(3)

# ------------------------------------------------------------------ #
# Resolve ticker symbols and account names                             #
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

# Account name cache — maps account URL → human-readable name.
# The account URL in order data looks like:
# https://api.robinhood.com/accounts/XXXXXXXX/
# We extract the account number from the URL and call
# load_account_profile() to get the account type.

account_cache: dict[str, str] = {}

def resolve_account_name(account_url: str) -> str:
    """Return a human-readable account name for a Robinhood account URL."""
    if not account_url:
        return ""
    if account_url not in account_cache:
        # Extract account number from URL.
        parts = [p for p in account_url.rstrip("/").split("/") if p]
        account_number = parts[-1] if parts else ""
        try:
            profile = rh.load_account_profile(account_number=account_number)
            acct_type = (profile.get("type") or "").replace("_", " ").title()
            # Build a readable name like "Individual (ABC123)" or "Roth IRA (DEF456)".
            if acct_type:
                name = f"{acct_type} ({account_number})"
            else:
                name = account_number
            account_cache[account_url] = name
            print(f"Resolved account {account_number} → {name}", file=sys.stderr)
        except Exception as exc:
            print(f"Could not resolve account {account_number}: {exc}", file=sys.stderr)
            account_cache[account_url] = account_number
    return account_cache[account_url]


for order in orders:
    instrument_url = order.get("instrument", "")
    order["symbol"] = resolve_symbol(instrument_url)
    account_url = order.get("account", "")
    order["account_name"] = resolve_account_name(account_url)

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
