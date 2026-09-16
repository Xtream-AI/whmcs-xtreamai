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
  calls the panel `update` endpoint. When only config options changed
  (bouquets, notes, `max_connections`) it pushes those. When the new
  product points at a different panel package, an **admin** key swaps
  the package on the live line without renewing it and without spending
  credits; a reseller key is refused with a clear error and the service
  has to be terminated and re-provisioned.
- Next-due-date sync on create and renew (skipped for one-time and free
  billing cycles). Renew also re-applies the line's connection count
  (Max Connections plus any connections configurable option) when the
  panel's renew left the line on the package's own count, so a renewal
  never downgrades a customer to the package value.
- Per-product **Suspend action**: whether a WHMCS suspension disables the
  line on the panel (default) or leaves it untouched so it simply
  expires on its own date.
- Client-area card with credentials, M3U URL, EPG URL and active
  connections. Both URLs accept `{username}` and `{password}`
  placeholders, replaced per client (URL-encoded).
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
- Dashboard: credits, panel health, counters and the update banner.
- Sub-Resellers view (admin key only): list and adjust credits.
- Lines browser with search and status filter.
- Read-only Catalog view (streams and VOD).
- Module Logs (WHMCS `tblmodulelog`) with date, action and a short
  summary.
- Credential generators: username and password generators (auto-generate
  toggle, prefix, length, character type, live preview) and the line
  notes template with documented tags and a live example.

### Bulk tools

The addon has a **Bulk tools** view for operations that touch many
services at once. It runs from the browser in batches (100 services for
linking, 5 per request for syncing), shows a progress bar, a counter per
outcome and a result row per service (service id, client, username,
outcome and message). Every operation is idempotent: running it again
never duplicates rows and never breaks a link that already exists.

1. **Index panel lines.** Reads every line of the selected panel and
   stores it locally in `mod_xtreamai_line_index` (line id, username,
   expiry, status and the WHMCS service id parsed from the line notes
   with the **Line Notes Template**, `WHMCS:{service_id}` by default).
   Read-only on the panel. The first batch of a run replaces the
   previous index of that panel.
2. **Link existing services.** Matches WHMCS services that have no panel
   line recorded yet against that index: first by the notes tag
   (`service_tag = tblhosting.id`), then by the panel username. On a
   match it writes the `mod_xtreamai_services` row, records the status
   and expiry, and fills the service username (and password, encrypted)
   when WHMCS has none. Sub-Reseller products and services whose product
   belongs to another panel are skipped and reported as such.
3. **Sync all services.** Calls the server module's `sync` for every
   linked, active service of the panel through WHMCS's local API, so the
   connection count (including the `extra_connections` configurable
   option), bouquets and notes are recomputed exactly as when you press
   **Sync line to panel** on a single service. The **Parallel requests**
   selector next to the **Include Suspended services** checkbox (1 to 4,
   3 by default) sends that many of those calls at the same time: each
   request works on its own share of the services while the progress bar,
   the counters and the results table are shared by all of them. Index
   and Link always run one request at a time. If a parallel run stops
   with an error, running it again with the same number resumes every
   request where it stopped; a different number starts the run over.
   A stopped run can be resumed after a page reload or a new login
   (each panel keeps its position in the browser), and the page
   refreshes the security token every four minutes so a long run does
   not outlive the admin session.

Use the Bulk tools when you migrate services from another WHMCS module
(the panel lines already exist and carry the `WHMCS:<service id>` tag in
their notes, so indexing plus linking rebuilds every link without
creating new lines), and after you change a product config option or a
configurable option that affects many services at once and want the new
connection count applied to all of them.

## Who it's for

Resellers and panel administrators. Which panel API key you use decides
which parts of the module light up:

| Operation | Reseller key | Admin key |
|---|---|---|
| Line products (create / suspend / unsuspend / renew / password / terminate) | Yes (owner inferred from the key) | Yes, with `Admin owner member_id` set on the panel entry |
| Change the panel package of a live line (WHMCS product upgrade or downgrade) | No (terminate and re-provision) | Yes, without renewing the line or spending credits |
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
- Changing the panel package of a live line additionally requires a
  panel updated on or after **2026-09-14**. An earlier panel accepts
  the request, applies only bouquets, notes and `max_connections`, and
  leaves the package as it was, while WHMCS still records the new
  product. Upgrade the panel before you rely on this.

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

### Updating

The addon checks the GitHub releases of this repository once a day and, when
a newer version exists, the dashboard shows a **Version X available** banner
with the release name, an excerpt of the release notes and a link to the
release page. **Check for updates** next to it forces the check.

**Update now** performs the whole update in place:

- Downloads `whmcs-xtreamai-<version>.tar.gz` and its `.sha256` asset from
  the release, verifies the SHA256 checksum and aborts without touching
  anything if it does not match.
- Extracts the archive, verifies that it contains both module folders and
  that the `version` in the addon `whmcs.json` matches the release tag, then
  copies the new files into `xtreamai.new-<version>-<timestamp>` folders next
  to the current ones and replaces `modules/servers/xtreamai` and
  `modules/addons/xtreamai` from there.
- Keeps the previous version of each folder next to it as
  `xtreamai.bak-<version>-<timestamp>` (only the newest backup per folder is
  kept) and removes its own temporary files. Nothing outside those two
  folders is ever deleted.
- Refuses to install the same or an older version.

The button needs `ext-curl` and `ext-phar` (`PharData`), a writable system
temporary directory, and PHP write access to both module folders and their
parent directories. When any of them is missing, the banner shows the manual
instructions instead of the button. The temporary directory does not have to
sit on the same filesystem as the WHMCS installation, because the new files
are staged next to the module folders before the swap.

**Manual fallback:** download the tarball from the
[Releases page](https://github.com/Xtream-AI/whmcs-xtreamai/releases) and
copy the two folders over the existing ones, as in the install step above.
Panels, API keys, product settings and the WHMCS-to-line links live in the
database, so replacing the files keeps everything.

After an update WHMCS keeps serving the files it already loaded for the
running request: the module says `Updated to X. Reload the page.` and the
admin reloads the page (there is no automatic reload).

## Configure

1. **Addons → Xtream AI Panel → Panels → Add Panel.** Set:
   - Name, API URL, optional M3U URL, optional EPG URL, Access key.
     Both URLs accept the `{username}` and `{password}` placeholders,
     replaced with each client's credentials:
     `http://panel.example.com:8080/get.php?username={username}&password={password}&type=m3u_plus&output=ts`
     and
     `http://panel.example.com:8080/xmltv.php?username={username}&password={password}`.
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
   member group new Sub-Reseller accounts will belong to) and Suspend
   action (see "Suspending without touching the panel" below).
5. Save the product.

The **Max Connections** field caps the number of concurrent connections
per line. Leave it at `0` to keep the package value; `0` never means
unlimited, the panel API does not allow unlimited lines. Non-zero
values apply only when the panel entry uses an **Admin** key; reseller
keys silently ignore this field. The value is absolute and behaves the
same everywhere: on creation, on **Sync line to panel**, and on a
product change that swaps the panel package.

**Selling extra connections.** Customers can pick their own connection
count through a WHMCS **Configurable Option** on the product. The module
reads it by name, case-insensitive, ignoring the `|Display name` part:

- `extra_connections` (also `Extra Connections`, `extra-connections`,
  `additional_connections`): added on top of the base. The base is the
  product's **Max Connections** when it is non-zero, otherwise the
  panel package's own connection count. A quantity slider from `0` to
  `4` on a 1-connection package gives lines with 1 to 5 connections,
  and `0` keeps the package value, so the first connection stays part
  of the product price.
- `max_connections` (also `Connections`): replaces the base outright.
  A dropdown of `1`, `2`, `3` gives exactly that many connections. A
  value of `0` falls back to Max Connections, then to the package.

The resulting number is sent on creation, on renew, on **Sync line to
panel** and on every product or configurable-option change, so a
customer moving the slider from 2 back to 0 gets the package count
back. Both option kinds need an **Admin** key on the panel entry.

The panel's renew endpoint applies the package template to the line, so
it writes the package's own connection count and clears the trial flag.
Right after a successful renew the module resolves the connection count
of the product again and pushes it to the line when it differs from the
number the renew returned. The renew call itself reports the expiry date
only, so in practice the count is pushed on every renewal of a product
that resolves to a number above zero. That extra write is best effort:
if it fails, the renewal still reports success and the error is kept in
the module log, because the panel has already renewed the line and WHMCS
must not retry the whole renewal (it would charge the panel twice).
Products without a connection count of their own (`Max Connections` at
`0` and no connections configurable option) send nothing, so the package
value is what stays on the line.

**Suspending without touching the panel.** The **Suspend action** config
option decides what a WHMCS suspension does to a line product:

- **Disable the line on the panel** (default, and the behaviour of every
  older release): the line is disabled on suspend and enabled again on
  unsuspend. The customer stops watching immediately.
- **Leave the line untouched, let it expire:** the module never changes
  the line when the service is suspended or unsuspended. The line keeps
  working until its own panel expiry date and then expires on its own,
  which is what some resellers want for overdue invoices (no angry
  customer cut off mid-month, no manual re-enable afterwards). If you
  disabled a line yourself from the panel, unsuspending the service does
  not enable it again.

The option applies to **Line** products only and it is read per service,
so you can configure it product by product. Sub-Reseller products ignore
it and keep trying to change the reseller status on the panel. WHMCS's
service status is updated in both cases, so invoices, automation and the
client area behave as usual.

## Provisioning modes

Set the mode per product with the **Account Type** config option:

- **Line** (default): each service becomes one IPTV line on the panel.
  Package and bouquets come from the panel; Suspend/Unsuspend toggle the
  line status unless the product's **Suspend action** says to leave the
  line untouched; Renew moves the panel expiry, re-applies the product's
  connection count when the panel reset it to the package value, and
  syncs WHMCS's next due date; Terminate deletes the line on the panel.
- **Sub-Reseller:** each service becomes a sub-reseller account on the
  panel. The `credits` config option seeds the initial balance and is
  added again on each renewal. ChangePassword resets the reseller
  password. Suspend, Unsuspend and Terminate require a manual action on
  the panel today because the panel API does not expose a reseller
  status field; the module surfaces a clear error for those actions and
  preserves the WHMCS to panel link so the operator can complete them
  in the panel without losing state.

**Product upgrades and downgrades.** When you change a service to a
different WHMCS product, or a customer changes a configurable option
such as `extra_connections`, the module handles it via the panel's
`update` endpoint. If the product keeps the same panel package, the
module pushes the new bouquets, notes and `max_connections` to the line
without recreating it.

If the new product uses a different panel package, the panel entry
decides what happens:

- **Admin key:** the module applies the new package to the live line,
  together with the new product's bouquets and notes. The line keeps
  its username, password and expiry date, and the change costs no
  credits. Connections follow the new package unless the product sets
  **Max Connections** or carries a connections configurable option,
  which are resolved exactly as on creation and sent as one absolute
  value. `is_restreamer` follows the new package. The WHMCS
  service is repointed to the new package so later renewals use it.
- **Reseller key:** the module refuses the change with a clear message
  and the service has to be terminated and re-provisioned with the new
  product. Package changes are an admin-only operation on the panel,
  because a reseller could otherwise move a line to a richer package
  without paying for it.

The bouquets configured on the new WHMCS product must belong to the
destination package. If any of them does not, the panel rejects the
change and lists the offending ids, nothing is applied to the line, and
the service keeps its previous panel package. Leave the product's
**Bouquets** field empty to hand the line the full bouquet list of the
new package.

Package changes need a panel updated on or after **2026-09-14**. An
older panel silently keeps the line on its previous package even though
WHMCS reports success and records the new product.

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
      Updater.php             release check and in-app update
      PanelApiRequestException.php
tests/
  run.php                     server module tests, no WHMCS required
```

Run the tests with any PHP 7.2+ binary, from the repository root:

```bash
php tests/run.php
```

The runner declares the few WHMCS helpers the server module uses
(`Capsule`, `logModuleCall`, `encrypt`) and its own `PanelApi`,
`ServiceStore`, `Settings` and `PanelStore` doubles, so no WHMCS
installation, database or panel is needed. It exits with a non-zero
status when a check fails.

User documentation (non-technical):
[`docs/USER-GUIDE.md`](docs/USER-GUIDE.md) ·
[`docs/GUIA-USUARIO.md`](docs/GUIA-USUARIO.md) (español).

## License

MIT (see `LICENSE`).
