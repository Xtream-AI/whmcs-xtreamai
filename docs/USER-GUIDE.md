# User Guide — Xtream AI Panel for WHMCS

This guide is for people who have **no** experience installing or configuring WHMCS modules. You don't need to know how to code. Just follow the steps in order.

The module is called **Xtream AI Panel**. It is free and open source (MIT license).

---

## 1. What this is and what you need before you start

**What the module does (in a few words):**

It lets you sell IPTV lines and sub-reseller accounts from your WHMCS. Your customer pays and orders the service in WHMCS, and the module creates the access automatically in your Xtream AI panel. You collect the money through WHMCS; the module handles creating, renewing, suspending, and cancelling the access in the panel.

**What you need to have ready (nothing technical):**

1. **Administrator access to your WHMCS.** This is the user you use to log in to the WHMCS admin area (where you create products and invoices).
2. **An account on an Xtream AI panel with its API key.**
   - The *API key* is a "secret key" that identifies your panel account. It works like a long password that the module uses to talk to the panel for you.
   - You find it inside your Xtream AI panel, in **Settings → Panel API Keys**. Create a new key there and copy the token (it starts with `pk_live_` and is shown only once). Resellers find the same tab under their own Settings page.
3. **Know how to upload files to your hosting.** Using the cPanel File Manager or an FTP program (FileZilla, for example) is enough. Nothing more is required.

---

## 2. Installation step by step

1. **Download the module package** (the `whmcs-xtreamai-X.Y.Z.tar.gz` file).
2. **Extract it** on your computer (double-click, or `tar -xzf whmcs-xtreamai-X.Y.Z.tar.gz` in a terminal). You will see a folder called `modules` and, inside it, two folders:
   - `modules/servers/xtreamai`
   - `modules/addons/xtreamai`
3. **Upload those two folders to your WHMCS.** Use the cPanel File Manager or FTP. They must end up inside the `modules` folder of your WHMCS:
   - `modules/servers/xtreamai` → into your WHMCS `modules/servers/` folder
   - `modules/addons/xtreamai` → into your WHMCS `modules/addons/` folder

**How to check they are in the right place:**

Open your hosting File Manager and look for these paths. The first part may vary depending on your installation, but the ending must be the same:

```
/home/youruser/public_html/modules/servers/xtreamai/xtreamai.php
/home/youruser/public_html/modules/addons/xtreamai/xtreamai.php
```

If you can see those two `xtreamai.php` files in place, the installation is complete.

---

## 3. Activate the module

1. In the WHMCS menu, go to **System Settings → Addon Modules**.
2. Look for **Xtream AI Panel** in the list.
3. Click **Activate**.

**What happens when you activate:**

- The module creates its own tables (its data files) automatically.

---

## 4. Updating the module

The addon checks GitHub once a day for a newer release. When there is one, the
**Dashboard** shows a card that says **Version X available**, with the release
name, a short excerpt of the release notes and a link to the release page. The
**Check for updates** button forces that check at any time.

**Update now** does the whole update for you:

1. It downloads the release file from GitHub and checks its SHA256 checksum. If
   the checksum does not match, it stops and changes nothing.
2. It extracts the archive and checks that it contains both module folders and
   that its version is the one the release announces.
3. It replaces `modules/servers/xtreamai` and `modules/addons/xtreamai`. The
   previous version of each folder is kept next to it, named
   `xtreamai.bak-<version>-<date>` (only the newest backup of each folder is
   kept), and its own temporary files are deleted.

At the end you see **Updated to X. Reload the page.** The page does **not**
reload itself: press F5 (or the reload button) on the addon page to run the new
version.

**When the button is missing.** **Update now** needs the PHP extensions
`curl` and `phar`, a writable temporary directory and write permission on the
two module folders and their parent folders. When the server does not allow
that, the card says why and shows the manual instructions instead: download
`whmcs-xtreamai-<version>.tar.gz` from the
[Releases page](https://github.com/Xtream-AI/whmcs-xtreamai/releases) and copy
the two folders it contains (`modules/servers/xtreamai` and
`modules/addons/xtreamai`) over the existing ones.

Your panels, API keys, product settings and the WHMCS-to-line links are stored
in the WHMCS database, so updating the files never touches them.

---

## 5. Your first panel

The "panel" is the Xtream AI server the module will work with.

1. In the WHMCS menu, go to **Addons → Xtream AI Panel**.
2. Click the **Panels** tab.
3. Click **Add Panel**. You will see a form. Fill it in like this:

| Field | What it is | What to enter |
|---|---|---|
| **Name** | An internal name so you can recognize it. | For example: `My main panel`. |
| **API URL** | The web address of your panel. | For example: `https://panel.example.com` (no trailing slash). |
| **M3U URL** | *Optional.* The M3U link your client will see to play the IPTV. You can use `{username}` and `{password}` inside it and the module replaces them with each client's credentials (URL-encoded). | For example: `http://panel.example.com:8080/get.php?username={username}&password={password}&type=m3u_plus&output=ts`. Leave it empty if you don't use it. |
| **EPG URL** | *Optional.* The EPG (XMLTV) link your client will see for the TV guide. It accepts the same `{username}` and `{password}` placeholders. | For example: `http://panel.example.com:8080/xmltv.php?username={username}&password={password}`. Leave it empty if you don't use it. |
| **Access key** | Your API key (the panel's secret key). It is stored **encrypted**. | Paste it here. |
| **Key type** | Whether the key you just pasted is a **Reseller** key or an **Admin** key. | Choose **Reseller** for line-only setups. Choose **Admin** if you plan to sell Sub-Reseller products, or if you want WHMCS product upgrades and downgrades to change the panel package of a live line. |
| **Admin owner member_id** | The panel member id that will own the lines created through this panel entry. Only required when **Key type** is **Admin**. | Enter the numeric member id. Leave empty for a Reseller key. |
| **SSL verification** | Whether to check your panel's security certificate. | Leave it **on**, unless your panel has a broken certificate. |
| **Panel status** | Whether this panel is active. | Leave it on so you can use it. |

4. Click **Test Connection**.

**What the Test button result means:**

- If everything works, you will see **Connected**, sometimes with a summary of your group or credits.
- If something fails, you will see an error message. Here are the most common cases:

| Typical error | What it means | What to do |
|---|---|---|
| **Panel authentication failed.** | The API key is not valid for that panel. | Check that you copied the API key correctly (no spaces). |
| **Could not reach the panel.** | Could not connect to the panel address. | Check the API URL is typed correctly. |
| **Panel URL is required.** | You didn't enter the panel address. | Enter the API URL and test again. |
| **API key is required.** | You didn't enter the API key. | Enter your API key and test again. |
| **Panel API token is not configured.** | The saved panel has no API key. | Open the panel in "Edit" and save its API key. |

5. When you see **Connected**, click **Add Panel** to save it.

---

## 6. Your first product

Now create the product you want to sell.

1. In WHMCS, go to **Products/Services** and create a **new product**.
2. Go to the **Module Settings** tab.
3. In **Module Name**, choose **Xtream AI Panel**.
4. You will see these options. Fill them in like this:

| Option | What it is | Example / value |
|---|---|---|
| **Panel** | Which panel will create the access. | Choose the panel you added earlier. |
| **Package Type** | The panel's package type. | `Official` or `Trial`. |
| **Package** | The specific panel package. The list **updates itself** when you change the type. | Choose one, for example `1 Month (1 month)`. |
| **Bouquets** | The channels/content packages the line will have. | Tick them with the checkboxes. |
| **Account Type** | What kind of access is created. | `Line (default)` for a normal IPTV line, or `Sub-Reseller` for a reseller account. |
| **Credits** | Starting credits. **Only** used for `Sub-Reseller`. | For example `100`. For `Line`, leave it at `0`. |
| **Max Connections** | Maximum concurrent connections per line. Only takes effect when the panel uses an **Admin** key; **Reseller** keys ignore this. | Leave `0` to use the package's own count (`0` never means unlimited). Any value between `1` and `100` to override. The number is absolute and means the same everywhere: when the line is created, on every renewal, when you press Sync, and on a product change. To let the customer choose, see "Letting customers buy extra connections" right below. |
| **Suspend action** | What happens on the panel when the service is suspended in WHMCS, for example because an invoice was not paid. Only used by `Line` products (`Sub-Reseller` products ignore it). | `Disable the line on the panel` (default): the line is turned off while the service is suspended and turned back on when you unsuspend it. `Leave the line untouched, let it expire`: the panel is never touched, so the line keeps working until its own expiry date and then expires by itself. See "What happens when a service is suspended" in section 8. |
| **Customer username** | Who chooses the username. Shown for `Line` and `Sub-Reseller` products, hidden on `Credit top-up`. | `Generated by the module (default)`: the module invents it, as always. `Customer types it in the "Line username" or "Reseller username" custom field`: the customer types it at checkout and the module uses it if it is valid and free. See "Letting the customer choose the username" in this section. |
| **Customer password** | Who chooses the password. Shown for `Line` and `Sub-Reseller` products, hidden on `Credit top-up` (module 1.10.0 and later). | `Generated by the module (default)`: the module creates it with your password settings. `Customer types it in the "Panel password" custom field`: the customer types it at checkout and the module uses it if it follows the rules. See "Letting the customer choose the password" in this section. |

### Letting customers buy extra connections

You do not need one product per connection count. Add a WHMCS **Configurable Option** to the product (System Settings → Configurable Options, then assign the group to the product) and the module reads it by name. The name is case-insensitive and the part after `|` is ignored, so `extra_connections|Extra Connections` shows "Extra Connections" to the customer and still works.

| Option name | How the module uses it | Typical setup |
|---|---|---|
| `extra_connections` (or `Extra Connections`, `additional_connections`) | Added on top of the base count. The base is the product's **Max Connections** if it is not `0`, otherwise the connection count of the panel package. | A **Quantity** option from `0` to `4` with a price per unit. On a 1-connection package the customer gets 1 to 5 connections, and `0` costs nothing extra. |
| `max_connections` (or `Connections`) | Replaces the base count outright. `0` falls back to Max Connections, then to the package. | A **Dropdown** with `1`, `2`, `3`... |

The result is sent when the line is created, on every renewal, when you press **Sync bouquets, notes & connections**, and whenever the customer changes the option later through **Upgrade/Downgrade Options** (WHMCS runs the module's package change after the upgrade invoice is paid). Moving the slider back to `0` returns the line to the package count. Like Max Connections, this needs an **Admin** key on the panel; with a Reseller key the panel decides the connection count.
| **Sub-Reseller Member Group ID** | Numeric id of the panel member group new Sub-Reseller accounts will belong to, taken from the **Member Groups** page of the panel. Required when **Account Type** is `Sub-Reseller` and the panel key is **Admin**: without it Create stops with a clear message and creates nothing. Reseller keys ignore it and inherit the group from their sub-reseller setup. | For example `4`. |

### Letting the customer choose the username

By default the module invents the username of every account. If you want the customer to type it, open the product's **Module Settings** and set **Customer username** to `Customer types it in the "Line username" or "Reseller username" custom field`. The option appears on **Line** and **Sub-Reseller** products and is hidden on **Credit top-up**; on Sub-Reseller products it arrives with module 1.10.0.

Then add a **Custom Field** to the product (Products/Services → your product → Custom Fields):

1. Click **Add New Custom Field**.
2. **Field Name:** `Line username` on a Line product, or `Reseller username` on a Sub-Reseller one. On a Line product the names `Username` and `Panel username` work too, on a Sub-Reseller product `Sub-Reseller username`, `Panel username` and `Username`. A WHMCS field name may also carry a visible label after a pipe, as in `Line username|Username for the panel`, and from module 1.9.4 both halves count.
3. **Field Type:** `Text Box`.
4. Tick **Show on Order Form** so the customer sees it at checkout. Tick **Required** if you do not want orders without a username.
5. In **Validation** paste `^[A-Za-z0-9_-]{3,32}$`, so WHMCS rejects an invalid username in the cart before the order is placed, without asking the panel.
6. Save.

What happens then:

- From module 1.9.1, when the customer adds the product to the cart and again on the checkout page, the module checks the username in the field before any payment: if another line on the panel already uses it, the cart answers `The username "X" is already taken. Choose another one.`, and if the value does not fit the format and your custom field has no **Validation** rule, it answers `The username "X" is not valid: use 3 to 32 letters, digits, dashes or underscores.` The cart checks only when the field has a value; if the panel cannot be reached at that moment the order is not blocked and the username is checked again when the service is created. From module 1.9.4 the cart finds the field in either half of a name like `Line username|Username for the panel`, and a product whose only custom field is this one is checked whatever that field is called.
- When WHMCS creates the service, the module takes the username the customer typed, checks that it is valid (3 to 32 letters, digits, dashes or underscores) and checks on the panel that nobody else is using it.
- If it is free, the account is created with that username and the password is generated with your credential settings, exactly as usual (or taken from the customer's own field when **Customer password** is on, see "Letting the customer choose the password" below).
- If it is taken, the order stays pending with `The username "X" is already taken on this panel. Ask the customer to choose another one.` Nothing is created, and the module **never** attaches the existing line to your customer: a username typed by a customer can only create a new line, so nobody can take over somebody else's line.
- If it is invalid, the order stops with `The username "X" is not valid: use 3 to 32 letters, digits, dashes or underscores.`

The check happens in the cart and again when WHMCS creates the service, not only when the customer orders, so a username can be free in the cart and taken a few minutes later. When that happens, edit the username on the service and press **Create** again, or ask the customer for another one. If you leave the custom field empty, or leave the option on `Generated by the module (default)`, nothing changes: usernames keep being generated as before.

**On a Sub-Reseller product** the two moments are the same, with one difference: the "already taken" lookup against the panel runs only when the panel entry uses an **Admin** key. The cart then answers `The reseller username "X" is already taken. Choose another one.` and the create step answers `The reseller username "X" is already taken on this panel. Ask the customer to choose another one.`, and the order stays pending. With a **Reseller** key the module does not look the name up itself: the cart accepts it and the panel refuses a duplicate when the account is created, so the order stays pending with the panel's own message. An invalid value answers `The username "X" is not valid: use 3 to 32 letters, digits, dashes or underscores.`, and a panel that does not answer never blocks the cart. Credit top-up products ignore the option, and it is hidden on them.

### Letting the customer choose the password

Module 1.10.0 and later, on **Line** and **Sub-Reseller** products. By default the module creates the password with your **Password Generator** settings. If you want the customer to type it, set **Customer password** to `Customer types it in the "Panel password" custom field` and add a **Custom Field** to the product (Products/Services → your product → Custom Fields):

1. **Field Name:** `Panel password` (the names `Password`, `Line password` and `Reseller password` work too, and both halves of a name like `Panel password|Password for the panel` count).
2. **Field Type:** `Password` is the recommended type, so WHMCS keeps the value as a secret on the order form and on the service.
3. Tick **Show on Order Form** so the customer sees it at checkout. Tick **Required** only if every order must carry a password.
4. Save.

The module accepts 8 to 32 characters, with no spaces and without any of `% & ? # / \ +`; any other character is fine, including `@ $ = : ; ! * ( ) ' ~ , . - _`. A value that breaks the rules stops the order form with `The password is not valid: use 8 to 32 characters without spaces and without % & ? # / \ +`, and the same message stops the Create step and leaves the order pending. If you leave the custom field empty, or leave the option on `Generated by the module (default)`, passwords keep being generated as before. The module never shows the password and never writes it to the Module Logs.

**Where the password lives.** As soon as the account is created the module empties that custom field on the WHMCS service, so the password stays only in the service's Password field, like a generated one, and that is where the customer sees it. Until the service exists (a pending order, a payment not confirmed) the value is still in the custom field, and if your WHMCS **Order Confirmation** email shows custom fields the password can travel in that email: use a **Password** field and review that email template if you turn the option on.

**What happens when WHMCS creates the service:**

When a customer orders (and the payment is confirmed), WHMCS does this automatically:

1. Generates a **username** and a **password** (or uses the ones the customer typed at checkout when the **Customer username** and **Customer password** options are on).
2. Creates the **line** (or the sub-reseller account) in your Xtream AI panel.
3. Saves the username and password so the customer can see them in their area.

You don't need to do anything else at that point.

---

## 7. What your customer sees

When the customer opens their WHMCS client area and opens their service, they see a card with:

- **Username** — their username, with a **Copy** button.
- **Password** — their password, with a **Show** button and a **Copy** button.
- **Status** — the line's status (Active, Suspended…).
- **Expiry Date** — when the line expires (for normal lines).
- **Credits** — their credits (only for Sub-Reseller accounts).
- **Connection URL** — their M3U link, if you set it on the panel.
- **EPG URL** — their EPG (XMLTV) link, if you set it on the panel.
- **Active Connections** — their active connections right now (what they are watching, from which IP, and for how long).

If the line isn't ready yet, they will see a notice telling them to wait for provisioning to finish.

---

## 8. Day-to-day use

These are the actions you will take as an administrator and what they do in the panel:

| Action | Where you click in WHMCS | What happens in the panel |
|---|---|---|
| **Suspend** | On the customer's service, the suspend button. | For a **line**, the line is disabled and the customer can no longer watch, unless the product's **Suspend action** is `Leave the line untouched, let it expire`, in which case the panel is not called at all (see below). For a **sub-reseller**, the panel API does not expose a reseller status field, so the module surfaces a clear error and keeps the WHMCS to panel link intact so you can disable the account in the panel yourself. |
| **Unsuspend** | On the customer's service, the unsuspend button. | For a **line**, the line is enabled again, unless the product's **Suspend action** is `Leave the line untouched, let it expire`, in which case the panel is not called (if you disabled the line by hand in the panel, it stays disabled). For a **sub-reseller**, the module surfaces the same clear error for the same reason and preserves the WHMCS to panel link so you can re-enable the account in the panel yourself. |
| **Renew** | When the invoice / service is renewed. | The line is renewed and its expiry date is updated. The panel applies the package again on every renewal, so right after the renew the module pushes the connection count of the product again (Max Connections plus any connections configurable option) instead of leaving the package's own count on the line. If that extra step fails, the renewal is still reported as successful and the detail is written to Module Logs: the line is already renewed on the panel and repeating the renewal would charge it twice. Every renewal of the same cycle carries the same idempotency key, so a double click or a WHMCS retry inside that cycle returns the panel's answer instead of extending the line a second time. If the line was disabled or blocked on the panel before the renewal, the renewal goes ahead (the panel enables the line again) and the panel tab and the module log tell you so. |
| **Terminate** | On the customer's service, the terminate/cancel button. | For a **line**, the line is deleted from the panel. For a **sub-reseller**, the module surfaces a clear error because the panel API cannot disable the reseller and it preserves the WHMCS to panel link so you can disable the account in the panel yourself without losing state. |
| **Change password** | On the customer's service, the change password option. | The password is changed in the panel and updated for the customer. |
| **Sync bouquets, notes & connections** | On the customer's service (admin area), the **Sync bouquets, notes & connections** button. | The current product's bouquets, notes and connection count (Max Connections plus any connections configurable option) are pushed to the line in the panel. Use this after you edit the product's config options without changing the product. It does **not** renew the line, it does not spend credits and it does not change the panel status or the panel expiry; it saves the status and expiry the panel answers with on the service and records a summary under **Last module action**. |
| **Refresh from panel** | On the customer's service (admin area), the **Refresh from panel** button. | Reads the line from the panel right now instead of using the copy saved by the last check, and updates every field of the panel tab. |
| **Link existing line** | On the customer's service (admin area), the **Link existing line** button. | Links the service to a line that already exists on the panel, the one whose username you typed in the service's **Username** field. It creates, changes and deletes nothing on the panel and it does not touch the next due date. It saves the username and password of the line on the service so the customer sees them in their area. See "Linking a line that already exists" below. |
| **Set panel expiry to WHMCS next due date** | On the customer's service (admin area), the button with that name. | Copies the WHMCS next due date to the panel line, at 12:00 UTC of that day. The panel only accepts the expiry date from an admin key: with a Reseller key the module refuses with a clear error and sends nothing to the panel. |
| **Set WHMCS next due date to panel expiry** | On the customer's service (admin area), the button with that name. | The opposite: the WHMCS next due date is set to the panel expiry read from the line. Works with a Reseller key too. |

These buttons are available for **Line** products only: Sub-Reseller and Credit top-up products do not show them.

**Product upgrade or downgrade.** When you change the WHMCS product of a service, or the customer changes a configurable option such as extra connections, the module handles it automatically:

- **Same panel package, different bouquets or connections:** the module pushes the new values to the existing line in the panel.
- **Different panel package, panel with an Admin key:** the module applies the new package to the same line. The customer keeps their username, password and expiry date, nothing is charged in credits, and the new product's bouquets and notes are applied at the same time. The connection count is resolved exactly as when the line is created (Max Connections plus any connections configurable option) and sent as one absolute number. The restreamer flag follows the new package.
- **Different panel package, panel with a Reseller key:** the module refuses the change with a clear message. To move that customer to a different panel package, terminate the current service and re-provision the new product, or switch the panel entry to an Admin key.

**The bouquets of the new product have to belong to the new package.** If one of them does not, the panel refuses the change and tells you which ids are wrong: nothing is applied to the line and the service stays on its previous panel package. Fix the product's Bouquets field and try again, or leave it empty so the line gets every bouquet of the new package.

Package changes need your panel to have been updated on or after **2026-09-14**. On an older panel the change is **not** applied: the line keeps its original package, only the bouquets, notes and connections are pushed, and WHMCS still reports success and records the new product.

### The panel tab of a service (admin area)

When you open a service in the admin area, the module adds a block with the real state of that line on the panel. This is the place to look before you touch anything, because the customer area and the invoices show WHMCS data, not panel data:

| Field | What it tells you |
|---|---|
| **Panel** | Which panel entry this service uses. |
| **Panel line ID** and **Panel username** | The line's id and username on the panel. |
| **Line status** | `Active`, `Expired` (the line is past its panel expiry), `Disabled` (the line is switched off) or `Blocked by panel` (the panel administration blocked it). A block wins over the switch. |
| **Active connections** | How many connections are open right now. |
| **Panel expiry** | When the line expires on the panel. |
| **WHMCS next due date** | When WHMCS thinks the next payment is due. It is a separate thing: editing it in WHMCS does not touch the panel. |
| **Panel checked** | When the panel was last read and whether it was read just now (`live`) or taken from the copy saved in the last 90 seconds (`cached`). If the panel cannot be reached, the field tells you why and shows the time of the last data that could be read. The page keeps working. |
| **Last module action** | The last thing the module did on this service and when, for example a renewal or a sync. |
| **Warning** | Only when something needs your attention, for example when the WHMCS next due date and the panel expiry do not match. |

**When the two dates do not match**, the warning says which date is which. That is what the buttons below the block are for:

- **Sync bouquets, notes & connections** sends the bouquets, the notes and the connection count of the product to the line. It does not renew, it does not spend credits and it does not change the status or the expiry on the panel.
- **Refresh from panel** reads the panel again right now.
- **Set panel expiry to WHMCS next due date** copies the WHMCS next due date to the panel. It needs an **Admin** key on the panel entry; with a Reseller key it tells you so and changes nothing.
- **Set WHMCS next due date to panel expiry** does the opposite, with the date the panel has.

**Dates.** The panel stores the expiry as an exact moment (UTC) and WHMCS stores the next due date as a plain day, so the module always works with complete days in UTC. When it copies a WHMCS date to the panel it uses 12:00 UTC of that day: that way the date does not shift a day depending on where you or your server are.

### Linking a line that already exists

Use this when the customer already had a line on your panel before WHMCS, so you do not want the module to create a second one.

1. Create the service in WHMCS (or accept the pending order) **without letting the module create anything**. When you accept the order, uncheck **Run Module Create** in the WHMCS accept order screen.
2. Type the username of that existing line in the service's **Username** field and press **Save Changes**.
3. Press **Link existing line** in the service's Module Commands row.

The module looks for that username on the panel and links the service to the line it finds. It also saves the username and password of the line on the service, so the customer sees their credentials in the client area, and it writes `Linked to existing line #<id> (<username>), expires <date>` in **Last module action**.

**Nothing is created, changed or deleted in the panel**, and the WHMCS next due date is not touched: if the two dates do not match, use **Set WHMCS next due date to panel expiry** or **Set panel expiry to WHMCS next due date**. After linking, Renew, Sync, Refresh, Change Password and the client area work on that line like on any other.

If the button cannot do its job it tells you why:

| Message | What it means |
|---|---|
| `This service is already linked to line #<id> (<username>).` | The service already has its line. Nothing to do. |
| `Type the panel username in the Username field of this service, save, then press Link existing line.` | The Username field of the service is empty. |
| `No line with username "X" was found on this panel.` | No line on that panel uses that username. Check the spelling on both sides. |
| `Link existing line is only available for Line products.` | The service belongs to a Sub-Reseller or Credit top-up product, which have no line. |

**Many services at once.** If you have a lot of services to link, do not do it one by one: use **Bulk tools → Index panel lines** and then **Link existing services**, as explained in section 9. The button is for the occasional service.

### What happens when a service is suspended

The **Suspend action** option of the product decides it, and it is the option you want to look at when a customer asks you not to cut the line for an unpaid invoice:

- **Disable the line on the panel**: the default. Suspending the service disables the line in the panel, so the customer stops watching right away, and unsuspending it enables the line again. This is what the module has always done.
- **Leave the line untouched, let it expire**: suspending the service changes nothing in the panel: the line keeps watching until its own expiry date and then expires by itself, with no action from you. Unsuspending the service does not touch the panel either, so a line you disabled by hand in the panel stays disabled.

In both cases WHMCS still marks the service as suspended or active (invoices, automation and the client area work as usual); the difference is only on the panel. The option is per product and only affects **Line** products: Sub-Reseller products always keep trying to change the reseller status on the panel.

---

## 9. Bulk tools

The **Bulk tools** tab (**Addons → Xtream AI Panel → Bulk tools**) does four jobs that would otherwise mean editing services one by one. They run in batches in your browser (100 lines per request when indexing, 100 services when linking, 5 when syncing or aligning expiries, because each service is one call to the panel) and show a progress bar, a counter for each result and one row per service. They are safe to run again: nothing is ever duplicated and nothing is deleted from the panel. If a run stops halfway (the panel went away, the browser tab was closed), just run it again: indexing starts over from scratch, and linking refuses to run until the index has been completed once.

A run that stops keeps its position in the browser, so it can be resumed after reloading the page or even after logging in again: open **Bulk tools** on the same panel, click the same button and the run carries on from the last service it did. While a run is active the page also refreshes the WHMCS security token every four minutes, which keeps your admin session alive during the long runs.

The **Panel** dropdown at the top decides which panel everything below works on. Change it and the page reloads on that panel.

### 9.1 Index panel lines

This reads the lines that already exist on the panel and keeps a local copy of them: line id, username, expiry, status, and the WHMCS service number found in the line notes.

1. Choose the panel in the **Panel** dropdown.
2. In the first card, click **Index lines**.
3. Wait for the progress bar to finish. The counters tell you how many lines were read and how many are in the local index now.

Run this before the other tools. It only reads from the panel, so it changes nothing there. If you run it again, the index of that panel is rebuilt from scratch.

### 9.2 Link existing services

This connects WHMCS services that have no panel line recorded yet to the lines that already exist. It looks for the **service number in the line notes** first (the **Line Notes Template** of General Settings, `WHMCS:{service_id}` by default) and falls back to the **username**.

1. Choose the panel and run **Index panel lines** first.
2. Tick **Include Pending, Terminated and Cancelled services** only if you want those services linked too. Normally they are left out.
3. Click **Link services** and watch the results table fill in.

For every service you get one row with the service id, the client, the username and the outcome:

| Outcome | What it means |
|---|---|
| `linked_by_tag` | The notes of a panel line contain this service number. The most reliable match. |
| `linked_by_username` | The service username matches the line username. No tag was found. |
| `not_found` | No line matched. The service was not touched. |
| `ambiguous_tag` | Several lines carry this service number and none of them has the service's username. Nothing was linked, so the wrong line is never picked: set the username on the WHMCS service (or fix the notes on the panel) and run again. The message lists the line ids. |
| `skipped_sub_reseller` | The product is a Sub-Reseller product: there is no line to link. |
| `skipped_other_panel` | The product points at another panel, so it was left for a run on that panel. |
| `error` | Something failed. The message column explains what. |

Nothing is created or deleted on the panel: this tool only restores the link between WHMCS and the line. For one or two services you do not need this card: the **Link existing line** button on the service page (section 8) links a single service to the line whose username you type on it.

### 9.3 Sync all services

This runs the same action as the **Sync bouquets, notes & connections** button on each service, but for all of them at once: the bouquets, the notes and the connection count (Max Connections plus any connections configurable option such as `extra_connections`) of each product are recalculated and pushed to its line on the panel. The panel status and expiry that the panel answers with are saved on each service as well.

1. Choose the panel. (Linking is not required to have run in the same session, but the services do need a linked line.)
2. Tick **Include Suspended services** if suspended services should be updated too. Without it, services whose WHMCS status is Suspended are skipped, and the counter **Skipped (Suspended)** tells you how many were left out in each batch. The checkbox remembers what you chose the next time you open Bulk tools.
3. Set **Parallel requests** (1 to 4, 3 by default) in the same row: that is how many services are updated at the same time. Each request takes its own share of the services, so the total time is roughly divided by this number. Leave it at 1 if the panel or the server prefers one call at a time.
4. Click **Sync services**.

**Warning:** this writes to **every active linked line** of the selected panel, with the configuration of its product. Run it when the product configuration is final. Use it after you change a connection configurable option (or a product's Max Connections) so the new count reaches every customer line. Services whose product is a Sub-Reseller are reported as `skipped_sub_reseller`, and the results table marks every other service as `synced` or `error`.

The progress bar tells you **how many requests are running and how many services have been processed**, for example "3 workers, 120 services processed", and the counters and the results table collect the outcome of all of them. If one request fails after its retries the run stops with an error, and the message names the last service already done in each request. Clicking **Sync services** again with the **same** number in **Parallel requests** resumes every request where it stopped, without syncing a service twice. If you change the number, the next run starts from the beginning: the shares are calculated from that number, so they would not match the previous run, and the progress bar says so.

### 9.4 Align panel expiry to WHMCS

This runs the same action as the **Set panel expiry to WHMCS next due date** button on each service, but for all of them at once: the expiry of every linked line of the panel is set to the next due date of its service in WHMCS, at **12:00 UTC** of that day. The panel only accepts the expiry with an **Admin** key, so if the panel entry uses a Reseller key the run stops before the first service, tells you so and nothing is sent to the panel.

1. Choose the panel.
2. Tick **Include Suspended services** if suspended services should be aligned too. Without it those services are skipped and the counter **Skipped (Suspended)** tells you how many were left out in each batch.
3. Set **Parallel requests** (1 to 4, 3 by default) in the same row, as in the previous card: how many services are updated at the same time. Keep the same value to resume a run that stopped.
4. Click **Align expiry dates**.

For every service you get one row with the service id, the client, the username and the outcome:

| Outcome | What it means |
|---|---|
| `aligned` | The expiry of the panel line was set to the WHMCS next due date. The message shows the date. |
| `skipped_no_due_date` | The service has no next due date in WHMCS (empty or `0000-00-00`), so there is nothing to copy. The service was not touched. |
| `skipped_suspended` | The service is Suspended in WHMCS and **Include Suspended services** was not ticked. |
| `skipped_sub_reseller` | The product is a Sub-Reseller product: there is no line expiry to set. |
| `error` | The module refused or failed for that service. The message column explains what, for example a next due date it could not use or a line it could not find. |

**Warning:** this overwrites the expiry of **every linked active line** of the selected panel with the next due date WHMCS has for that service. If a date is wrong in WHMCS, the panel line will be wrong too. Before a run over many services, press **Refresh from panel** on a few of them in the service tab and compare the two dates; a run that stops keeps its position, and clicking the button again with the same number in **Parallel requests** resumes it where it stopped.

---

## 10. Sub-Reseller products, explained simply

**What they are for:** a Sub-Reseller product gives your customer their **own reseller account** on the panel, with their **own credits**. This lets your customer resell lines on their own.

**How to set them up:** when creating the product, in the Module Settings tab:

- Set **Account Type** to `Sub-Reseller`.
- In **Credits**, enter how many credits they receive on creation (and on each renewal).

You don't need to choose Package or Bouquets for this type: the module ignores them and uses the credits instead.

**The Sub-Resellers screen in the addon:** in **Addons → Xtream AI Panel → Sub-Resellers** you will see a list of your sub-resellers with their username, email, status, and **credits**. To adjust someone's credits:

1. Enter a number in the **± credits** field (with `+` to add or `-` to subtract).
2. Enter an optional **Reason**.
3. Click **Apply**.

### Selling credit top-ups

A **Credit top-up** product creates nothing new: when it is paid, it **adds credits** to a Sub-Reseller account that already exists on the same panel. Use it when your resellers buy credit packages from your website.

1. Create a product as usual and open its **Module Settings** tab.
2. Set **Account Type** to `Credit top-up (existing Sub-Reseller)`.
3. In **Credits**, type how many credits each order adds. If you want to sell several package sizes (100, 500, 1000) from one product, add a WHMCS **Configurable Option** named `credits` instead (a quantity, or a dropdown with the three sizes): what the customer picks is what gets added, and it replaces the **Credits** field.
4. Add a **Custom Field** to the product named `Reseller username`, ticked **Required** and **Show on Order Form**: the customer types the panel account to top up, and the module credits it even if that account was created by hand in the panel and never went through WHMCS (`Panel username` and `Sub-Reseller username` are accepted names too; from module 1.9.4 a name in the form `Reseller username|Reseller username on the panel` counts in either half, and a top-up product with a single custom field uses it whatever its name). This step is recommended on every top-up product: without the field the module only finds the account when the customer has exactly one Sub-Reseller service linked on that panel.
5. Save the product.

The **billing cycle** decides how often the credits are added: **One Time** means a single purchase, and a monthly cycle adds the same amount every month automatically.

For the order to work the module has to resolve which panel account to top up: the customer's **Sub-Reseller** service that is active in WHMCS and linked to the same panel as the top-up product, or the username the customer typed in the `Reseller username` custom field, which the module looks up on the panel. Services created by the module are linked automatically; an account that already existed is linked once with **Bulk tools → Link**. Like every Sub-Reseller feature, credit top-ups need an **Admin** key on the panel entry.

The **Top-up scope** option decides who the `Reseller username` field accepts. `Any reseller on the panel` is the default, and what products saved before 1.8.2 use: the customer can send the credits to any reseller account on the panel, so a top-up can be a gift to an account that is not theirs. Set it to `Only this client's linked Sub-Reseller accounts` if a top-up must always land on an account the same customer owns in WHMCS: the module then accepts only usernames that match one of the customer's Sub-Reseller services linked on that panel and refuses everything else, without asking the panel, with `The reseller username "<value>" does not match any of this client's linked Sub-Reseller accounts on this panel.` In both scopes, a username that belongs to a panel administrator is refused with `The reseller username "<value>" belongs to a panel administrator and cannot receive a top-up.`

From module 1.9.1 the cart also validates the `Reseller username` field, when the product is added and again on the checkout page, so the customer sees the mistake before paying. With **Top-up scope** at `Any reseller on the panel`, a username that no account on the panel carries stops the order form with `The reseller username "X" was not found. Check the spelling and try again.`, and one that belongs to a panel administrator stops it with `The reseller username "X" cannot receive a top-up.` With **Top-up scope** at `Only this client's linked Sub-Reseller accounts`, a customer who is signed in and types a username that matches none of their linked Sub-Reseller accounts sees `The reseller username "X" does not match any of your Sub-Reseller accounts.`; a visitor who is not signed in is not checked in the cart and the order is refused when the service is created, exactly as before. The cart checks only when the field has a value and needs an **Admin** key on the panel entry, and when the panel does not answer it does not block the order: the account is resolved again when the service is created.

**What the reseller sees:** a card called **Credit Top-Up** with the Sub-Reseller account (and a Copy button), the credits per order, the current balance and the status. There is no password, no M3U link and no EPG link on that card: the account keeps the ones it already had.

---

## 11. All the addon screens, one by one

Inside **Addons → Xtream AI Panel** you have these tabs:

**Dashboard** — the summary. It shows cards with: **Credits**, **Panels** (how many panels there are and how many are healthy), **Sub-Resellers**, and **Lines**. At the top it also shows the update card when a newer version of the module exists (section 4). Below that, the status of each panel and some quick links.

**Panels** — the list of your panels with their status, SSL, last check, and actions (Test, Edit, Activate/Deactivate, Delete). The **Add Panel** / **Edit Panel** form is also here.

**Sub-Resellers** — the list of sub-resellers and their credits, with the credit adjustment form.

**Lines** — to search for lines. You can filter by **username** (the "Username contains…" field) and by **status** (All statuses / Enabled / Disabled). The **Status** column of each row shows the same four values as the service tab: `Active`, `Expired`, `Disabled` and `Blocked by panel`.

**Catalog** — to see what is on your panel: **Live Streams** (live channels) and **VOD** (movies and series). Each has its own search box.

**Bulk tools** — the four operations that work on many services at once: **Index panel lines**, **Link existing services**, **Sync all services** and **Align panel expiry to WHMCS**. See section 9.

**Module Logs** — a history of what the module has done (each call to the panel API), with date, action and a short summary. From module 1.9.2 and with module logging enabled in WHMCS, a cart check leaves two `xtreamai` entries: `checkout_hook` (the call reached the module, with the products in the cart and the field ids the order form sent) and `checkout_validate` (the field the module recognised and how many errors it returned); neither stores the username the customer typed, only its length.

**General Settings** — here you configure how usernames and passwords are created:

- **Username Generator**: **Auto Generate**, **Prefix** (optional), **Length**, and **Character Type**. It has a live **Preview**.
- **Password Generator**: the same, with **Auto Generate**, **Length**, **Character Type**, and its **Preview**.
- **Line Notes Template**: a text added as a note to each new line. You can use these tags, which the module fills in automatically:

| Tag | What it becomes |
|---|---|
| `{service_id}` | The service number in WHMCS. |
| `{client_id}` | The client's ID number. |
| `{client_name}` | The client's name. |
| `{client_email}` | The client's email. |
| `{client_phonenumber}` | The client's phone number. |
| `{product_name}` | The product name. |

**Example:** if the template is `WHMCS:{service_id}` and the service is number 135, the note will become `WHMCS:135`.

When you're done, click **Save Settings**.

---

## 12. Common problems

| Message you might see | What it means | What to do |
|---|---|---|
| **Panel authentication failed.** | The panel's API key is wrong. | Check the API key in Panels → Edit and test again with Test. |
| **The reseller does not have enough credits or user slots.** | Your panel account ran out of credits or out of slots to create more lines. | Add credits or slots in your panel (or to your reseller account). |
| **No panel found. Add and activate a panel in Addons → Xtream AI Panel.** | The product has no panel assigned, or there are no active panels. | Add and activate a panel in Addons → Xtream AI Panel, then choose it in the product. |
| **No package selected for this product.** | The product has no package chosen. | In the product's Module Settings tab, choose a Package. |
| **No package selected for service #135 (product "IPTV Line"): set the package in the product's Module Settings.** | A renewal was attempted for a service whose product has no panel package. | Open the product's Module Settings tab, choose a Package and renew again. |
| **Setting the panel expiry requires an admin panel key.** | You pressed **Set panel expiry to WHMCS next due date** on a panel entry whose key is a Reseller key. Nothing was sent to the panel. | Renew the service, change the expiry on the panel and then use **Set WHMCS next due date to panel expiry**, or switch the panel entry to an Admin key. |
| **Panel check failed: …** | The panel could not be read when you pressed **Refresh from panel**. | Check the panel connection with the Test button and make sure the API key has permissions. The service tab keeps working with the data it already had. |
| **Addon not installed. Install and activate the Xtream AI Panel addon first.** | The addon is not installed or activated. | Activate it in System Settings → Addon Modules. |
| **Invalid security token. Please try again.** | Your admin session expired or the page loaded incorrectly. | Reload the page and repeat the action. |
| **This service has no panel line yet. Provision it first.** | The service has no line created in the panel yet. | Create the service (or wait for WHMCS to finish creating it). |
| **Could not load panel data…** | Could not read the panel information (packages, bouquets, etc.). | Check the panel connection with the Test button and make sure the API key has permissions. |
| **Panel URL is required.** / **API key is required.** | Missing data when testing the connection. | Enter the API URL and the API key and test again. |
| **The username "X" is already taken on this panel. Ask the customer to choose another one.** | The product uses the **Customer username** option and the username the customer typed belongs to a line that already exists on the panel. Nothing was created, and the module never attaches that existing line to your customer. | Ask the customer for another username, write it in the `Line username` field of the WHMCS service (or in the service's Username field) and press **Create** again. See "Letting the customer choose the username" in section 6. |
| **The username "X" is not valid: use 3 to 32 letters, digits, dashes or underscores.** | The username the customer typed does not respect the allowed characters or length. Nothing was created. | Ask the customer for a valid username, correct it on the WHMCS service and press **Create** again. |
| **The reseller username "X" is already taken on this panel. Ask the customer to choose another one.** | On a Sub-Reseller product with the **Customer username** option, the username the customer typed belongs to an account that already exists on the panel. The module looked it up because the panel entry uses an **Admin** key. Nothing was created and the order stays pending. | Ask the customer for another username, correct the `Reseller username` field on the WHMCS service and press **Create** again. With a **Reseller** key the duplicate is refused by the panel itself and the order carries the panel's message. |
| **Set the Sub-Reseller Member Group ID in this product's Module Settings: with an Admin panel key the panel needs the numeric id of the member group the new account belongs to.** | A Sub-Reseller product was ordered while its panel entry uses an **Admin** key, and the product has no **Sub-Reseller Member Group ID** filled in. The module stops before it creates anything, so nothing was created and the order stays pending. | Open the product's **Module Settings** and fill **Sub-Reseller Member Group ID** with the numeric id of the member group the new accounts should belong to; you will find it on the **Member Groups** page of the panel. Save the product and press **Create** again. Reseller keys do not need it: they inherit the group from their sub-reseller setup. |
| **The password is not valid: use 8 to 32 characters without spaces and without % & ? # / \ +** | On a product with the **Customer password** option, the value in the `Panel password` field breaks one of the rules. Nothing was created and the order stays pending. | Ask the customer for a password that fits the rules, correct it on the WHMCS service and press **Create** again, or leave the field empty so the module generates one. |
| **Type the panel username in the Username field of this service, save, then press Link existing line.** | You pressed **Link existing line** on a service whose **Username** field is empty, so the module does not know which line to look for. | Type the username of the line as it is on the panel, press **Save Changes** and press **Link existing line** again. |
| **No line with username "X" was found on this panel.** | You pressed **Link existing line** and the panel has no line with that username. Nothing was linked. | Check the username on your panel under **Users** and in the service's Username field; correct it, save and press the button again. If the customer has no line yet, press **Create** instead so the module creates one. |
| **This service is already linked to line #<id> (<username>).** | You pressed **Link existing line** on a service that already points at a line on the panel. Nothing was changed. | Nothing to do: use **Refresh from panel** to see the current state of that line. |
| **Link existing line is only available for Line products.** | You pressed **Link existing line** on a Sub-Reseller or Credit top-up service, which has no line of its own. | Nothing to do: those product types have no panel line to link. |
| **This client has no active Sub-Reseller service on this panel to top up. Add a required custom field named "Reseller username" to the top-up product so the customer types the panel account, or order the Sub-Reseller product first.** | You are selling a Credit top-up product and the customer has no Sub-Reseller account active and linked on that panel, and the product has no `Reseller username` field for the customer to type the panel account in, so the module has nothing to top up. The order stays pending in WHMCS. | Add a custom field named `Reseller username` to the top-up product, ticked **Required** and **Show on Order Form**, order the Sub-Reseller product for that customer, or link the account they already have with **Bulk tools → Link** on that panel, and press Create on the top-up service again. |
| **This client has several Sub-Reseller accounts on this panel (a, b). Add a required custom field named "Reseller username" to the top-up product so the customer chooses the account.** | The customer owns more than one Sub-Reseller account on that panel and the module will not guess which one should receive the credits. The names in brackets are the accounts it found. | Add a custom field named `Reseller username` to the top-up product, ticked **Required** and **Show on Order Form**, so the customer picks the account at checkout. |
| **The reseller username "<value>" was not found on this panel.** | The username the customer typed in the `Reseller username` field matches neither a linked Sub-Reseller service of that customer on this panel nor any account on the panel: usually a typo, or the username does not exist on that panel. Nothing was credited. | Check the username on your panel under **Users** and correct it on the WHMCS service (or ask the customer for the exact username), then press Create again on the top-up service. |
| **The reseller username "<value>" does not match any of this client's linked Sub-Reseller accounts on this panel.** | The product's **Top-up scope** is `Only this client's linked Sub-Reseller accounts` and the username the customer typed is not one of the customer's Sub-Reseller services linked on this panel. The module refuses before it asks the panel, so nothing was credited. | Link the account that should receive the credits to the customer's service with **Bulk tools → Link** on that panel, or set the product's **Top-up scope** to `Any reseller on the panel`, then press Create again on the top-up service. |
| **The reseller username "<value>" belongs to a panel administrator and cannot receive a top-up.** | The username is a panel administrator account: a member of the panel's administrator group, or the account set as **Admin owner member_id** on the panel entry. Administrator accounts have no reseller credit balance to top up, so the module refuses in either Top-up scope. | Ask the customer for the username of their own Sub-Reseller account and correct it on the WHMCS service, then press Create again on the top-up service. |

---

## 13. Frequently asked questions

**Do I need to be a panel administrator?**
For Line products, no. For Sub-Reseller products, yes: the panel key must be an admin key. Changing the panel package of a line that is already running (a WHMCS product upgrade or downgrade) also needs an admin key; with a reseller key you terminate the service and re-provision it.

**Are my passwords safe?**
Yes. API keys are stored **encrypted** with WHMCS's own encryption, and they never appear in the logs or in error messages.

**Can I have several panels?**
Yes. Add as many as you want in Panels, and choose which one each product uses.

**I migrated from another module, how do I connect my existing services?**
Go to **Addons → Xtream AI Panel → Bulk tools**, choose your panel in the dropdown, click **Index lines** in the first card and, when it finishes, click **Link services** in the second card. Each service is matched to its line by the WHMCS service number stored in the line notes (template `WHMCS:{service_id}`) or, if there is no tag, by the username: the link is restored without creating, changing or deleting anything on the panel. Afterwards you can run **Sync services** in the third card so every line picks up the bouquets and the connection count of its product.

**Can my customers choose their own username?**
Yes, on Line and Sub-Reseller products. Set **Customer username** on the product to `Customer types it in the "Line username" or "Reseller username" custom field` and add a custom field to that product: `Line username` on a Line product, or `Reseller username` on a Sub-Reseller one (Text Box, ticked **Show on Order Form**). The customer types the username at checkout and the module uses it when it is valid and free on the panel. The module also checks the field in the cart, when the product is added and again on checkout, so a username that is taken is normally refused before the customer pays; when the order does get through with a taken username it stays pending with a clear message and you tell the customer to choose another one. On a Sub-Reseller product the lookup against the panel needs an **Admin** key on the panel entry; with a **Reseller** key the cart accepts the name and the panel refuses a duplicate when the account is created. The module never links a line that already exists from a customer order, so nobody can take over somebody else's line. See "Letting the customer choose the username" in section 6.

**Can my customers choose their own password too?**
Yes, on Line and Sub-Reseller products, from module 1.10.0. Set **Customer password** on the product to `Customer types it in the "Panel password" custom field` and add a custom field named `Panel password` to that product: type **Password** is the recommended one, tick **Show on Order Form**, and tick **Required** only if you want to force it. The module accepts 8 to 32 characters with no spaces and without `% & ? # / \ +`, and refuses anything else in the cart and when it creates the service. Once the account exists the module empties that field on the service and the password stays only in the service's Password field, but until then the value sits in the custom field, so keep the **Password** type and check that your WHMCS Order Confirmation email does not print custom fields. See "Letting the customer choose the password" in section 6.

**I have customers with lines created before WHMCS. How do I make WHMCS renew those lines?**
Link each service to its line once. For a single service: create or accept the WHMCS service without running the module's Create (uncheck **Run Module Create** when you accept the order), type the panel username in the service's **Username** field, press **Save Changes** and then press **Link existing line**. From that moment Renew, Sync, Refresh, Change Password and the client area work on that line. For many services at once, use **Bulk tools → Index panel lines** followed by **Link existing services**. Linking does not change the WHMCS next due date: align the two dates with **Set WHMCS next due date to panel expiry** on the service, or with **Align panel expiry to WHMCS** in Bulk tools. See section 8.

**Can my customers choose how many connections they want?**
Yes. Add a WHMCS Configurable Option named `extra_connections` (a quantity from 0 upwards, priced per unit) to the product and the module adds it on top of the package's connections, on order and every time the customer changes it later. See "Letting customers buy extra connections" in section 6.

**My resellers buy credits from my website. Can an order add credits instead of creating a new account?**
Yes. Sell them a **Credit top-up** product: when the order is paid, the module adds the credits to an existing Sub-Reseller account on the same panel, and creates nothing. Add a configurable option named `credits` if you want to sell different package sizes, and a recommended custom field named `Reseller username` so the customer types the panel account to top up: the module looks that username up on the panel, so it also works for accounts that were created by hand and never went through WHMCS. See "Selling credit top-ups" in section 10.

**Can a customer send credits to an account that is not theirs?**
By default, yes: with the `Reseller username` field on the product and **Top-up scope** left at `Any reseller on the panel`, the customer can type any reseller account on the panel and the credits go there. The credits are paid for, so the worst outcome is a gift to another account. If you want top-ups limited to the customer's own accounts, set **Top-up scope** to `Only this client's linked Sub-Reseller accounts`: the module then accepts only usernames that match one of the customer's Sub-Reseller services linked on that panel and refuses anything else with `The reseller username "<value>" does not match any of this client's linked Sub-Reseller accounts on this panel.` In both scopes, a username that belongs to a panel administrator is refused with `The reseller username "<value>" belongs to a panel administrator and cannot receive a top-up.`

**Can a line keep working when the service is suspended for an unpaid invoice?**
Yes. In the product's Module Settings, set **Suspend action** to `Leave the line untouched, let it expire`. Suspending the service then does nothing to the panel: the line keeps working until its own expiry date and expires by itself. This is a per-product setting, so you can keep the default (`Disable the line on the panel`) everywhere else. See section 8.

**Does it work with my PHP version?**
Yes, with **PHP 7.2 or higher**.

**Is there a license key?**
No, the module is free and open source (MIT).

**How do I update the module?**
Open **Addons → Xtream AI Panel → Dashboard**. When a newer version exists, the card at the top shows its release notes and an **Update now** button that downloads, verifies and installs it; if your server does not allow that, the same card shows the manual instructions. The previous files are kept next to the module as `xtreamai.bak-<version>-<date>`. See section 4.

---

## 14. Uninstalling

1. In **System Settings → Addon Modules**, find **Xtream AI Panel** and click **Deactivate**. Your data is **kept**, in case you want to reactivate it later.
2. To remove it completely, delete the two folders using the File Manager or FTP:
   - `modules/servers/xtreamai`
   - `modules/addons/xtreamai`

That's it.
