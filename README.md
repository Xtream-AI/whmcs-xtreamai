# Xtream AI Panel for WHMCS

Open source (MIT) WHMCS provisioning module for the
[Xtream AI Streaming Panel](https://xtreamai.net/). It ships as a WHMCS
addon plus a server module, with no Composer install step and no
dependency on external services beyond the panel itself.

## What it does

**Line provisioning (server module).** Creates IPTV lines on the panel
when a product is activated in WHMCS, and keeps them in sync throughout
the lifecycle:

- CreateAccount, Suspend, Unsuspend, Renew, Terminate, ChangePassword.
- Line updates (bouquets, notes; admin-only: `max_connections`,
  `exp_date`, `is_restreamer`, `allowed_ips`, `allowed_ua`,
  `is_isplock`).
- Product changes: when a WHMCS service switches products, the module
  calls the panel `update` endpoint when only config options changed
  (bouquets, notes, `max_connections`). Swapping to a different panel
  package (different `package_id`) requires manual re-provisioning and
  the operation is refused with a clear error.
- Next-due-date sync on create and renew (skipped for one-time and free
  billing cycles).
- Client-area card with credentials, M3U URL and active connections.
- Admin service tab with a read-only panel/line summary and a
  **Sync line to panel** button that pushes the current product's
  bouquets, notes and `max_connections` to the panel line without a
  renewal.
- Product editor filters packages by type instantly, offers a bouquet
  multi-picker, and shows the reseller credits balance.

**Sub-Reseller provisioning (server module).** Creates a sub-reseller
account on the panel:

- Account created on the client's email at activation, with an optional
  initial credit balance from the `credits` config option.
- Credits added again on each renewal.
- Suspend, Unsuspend and Terminate require a manual action on the panel
  today because the panel API does not expose a reseller status field.
  The module surfaces a clear error and preserves the WHMCS to panel
  link when those actions are triggered, so an operator can complete
  them in the panel and no state is lost.
- ChangePassword resets the sub-reseller password.

**Addon module.** Admin UI to run the whole thing:

- Panels: add, edit and remove panel connections; connection test for
  saved and unsaved credentials.
- Dashboard: credits, panel health and counters.
- Sub-Resellers view (admin key only): list and adjust credits.
- Lines browser with search and status filter.
- Read-only Catalog view (streams and VOD).
- Module Logs (WHMCS `tblmodulelog`) with date, action and a short
  summary.
- Credential generators: username and password generators (auto-generate
  toggle, prefix, length, character type, live preview) and the line
  notes template with documented tags and a live example.

## Who it's for

Resellers and panel administrators. Which panel API key you use decides
which parts of the module light up:

| Operation | Reseller key | Admin key |
|---|---|---|
| Line products (create / suspend / unsuspend / renew / password / terminate) | Yes (owner inferred from the key) | Yes, with `Admin owner member_id` set on the panel entry |
| Catalog (packages, bouquets, streams, VOD), `me` | Yes | Yes |
| Create sub-reseller | Yes | Yes |
| Sub-Reseller product lifecycle (reset password/credits) | No (403) | Yes |
| Addon "Sub-Resellers" view and dashboard reseller counters | No (403) | Yes |

Recommended scopes when creating the key on the panel:

- **Line products (both Reseller and Admin keys):** `lines:read`,
  `lines:write`, `packages:read`, `bouquets:read`; optionally
  `streams:read` and `vods:read` to enable the Catalog view.
- **Sub-Reseller products (Admin key only):** the Line scopes above
  plus `resellers:read`, `resellers:write`, `subresellers:write`.

`/me` is accessible to any authenticated key (it is used by the
Test Connection button and needs no scope).

Line-only deployments run on a reseller key. Sub-Reseller products
require an admin key.

## Requirements

- WHMCS **8.0 or newer** (hard floor: earlier releases ship an older
  Illuminate that lacks `updateOrInsert`).
- PHP 7.2 or newer.
- PHP extensions: `ext-curl`, `ext-json` (`ext-mbstring` is optional).
- MySQL or MariaDB, as used by WHMCS.
- Xtream AI Panel 2.1.2 or newer (`PANEL_API_ENABLED=true` in the
  panel configuration). The Public API is required.

## Obtaining an API key

1. Log in to your Xtream AI Panel.
2. Open **Settings → Panel API Keys** (resellers find the same tab
   under their own Settings page, provided the admin enabled the
   `can_create_api_keys` permission for their member group in the
   panel).
3. Create a key with the scopes listed in "Who it's for" above.
4. Copy the token (starts with `pk_live_`). It is shown once.

Keep the token safe: paste it into the WHMCS addon in the next step.

## Install

1. Copy the two module directories into your WHMCS root:
   ```bash
   cp -r modules/servers/xtreamai   /path/to/whmcs/modules/servers/
   cp -r modules/addons/xtreamai    /path/to/whmcs/modules/addons/
   ```
2. In WHMCS admin: **System Settings → Addon Modules** → activate
   **Xtream AI Panel**.

## Configure

1. **Addons → Xtream AI Panel → Panels → Add Panel.** Set:
   - Name, API URL, optional M3U URL, Access key.
   - SSL verification (on by default).
   - **Key type:** *Reseller* or *Admin*.
   - **Admin owner member_id:** required when Key type is Admin. It is
     the panel member id that will own the lines created through this
     panel entry.
2. Press **Test Connection** and save.
3. In WHMCS, open **Products/Services → your product → Module Settings**
   and pick **Xtream AI Panel** in the Module Name dropdown.
4. Fill the config options: Panel, Package Type, Package, Bouquets,
   Account Type, Credits (for Sub-Reseller products), Max Connections
   (optional, admin key only), Sub-Reseller Member Group ID (required
   for Sub-Reseller products on Admin keys; numeric id of the panel
   member group new Sub-Reseller accounts will belong to).
5. Save the product.

The **Max Connections** field caps the number of concurrent connections
per line. Leave it at `0` to keep the package default. Non-zero values
apply only when the panel entry uses an **Admin** key; reseller keys
silently ignore this field.

## Provisioning modes

Set the mode per product with the **Account Type** config option:

- **Line** (default): each service becomes one IPTV line on the panel.
  Package and bouquets come from the panel; Suspend/Unsuspend toggle the
  line status; Renew moves the panel expiry and syncs WHMCS's next due
  date; Terminate deletes the line on the panel.
- **Sub-Reseller:** each service becomes a sub-reseller account on the
  panel. The `credits` config option seeds the initial balance and is
  added again on each renewal. ChangePassword resets the reseller
  password. Suspend, Unsuspend and Terminate require a manual action on
  the panel today because the panel API does not expose a reseller
  status field; the module surfaces a clear error for those actions and
  preserves the WHMCS to panel link so the operator can complete them
  in the panel without losing state.

**Product upgrades and downgrades.** When you change a service to a
different WHMCS product, the module handles it via the panel's `update`
endpoint. If the new product uses the same panel package, the module
pushes the new bouquets, notes and `max_connections` to the line
without recreating it. If the new product uses a different panel
package, the module refuses the change with a clear message: the panel
API does not support swapping packages on a live line, so you terminate
the service and re-provision with the new product.

Keep the WHMCS billing cycle aligned with the panel package duration.
Renewals sync WHMCS's next due date to the panel expiry, so a 30-day
package on a monthly cycle drifts a day or two per renewal and a 1-year
package on a monthly cycle jumps a year ahead.

## Security notes

- Panel API tokens are encrypted at rest with the WHMCS `encrypt()`
  helper and never rendered, logged, or included in error messages.
- API tokens are never written to the module log.
- Every admin POST action requires the WHMCS CSRF token.
- All admin and client-area output is escaped
  (`htmlspecialchars` / `{$var|escape}`).
- TLS verification is on by default per panel entry; disabling it shows
  a visible warning in the admin UI.
- Random credentials use `random_int()`.

## Repository layout

```
modules/
  servers/xtreamai/           server module (provisioning)
    xtreamai.php
    templates/overview.tpl    client area template
  addons/xtreamai/            addon (admin UI and shared library)
    xtreamai.php
    lib/
      bootstrap.php           autoloader (WhmcsXtreamAI)
      Settings.php            mod_xtreamai_settings
      PanelStore.php          mod_xtreamai_panels (tokens encrypted)
      ServiceStore.php        mod_xtreamai_services
      PanelApi.php            facade over the panel API
      PanelHttpClient.php     lightweight API client
      PanelApiRequestException.php
```

User documentation (non-technical):
[`docs/USER-GUIDE.md`](docs/USER-GUIDE.md) ·
[`docs/GUIA-USUARIO.md`](docs/GUIA-USUARIO.md) (español).

## License

MIT (see `LICENSE`).
