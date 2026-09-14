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

## 4. Your first panel

The "panel" is the Xtream AI server the module will work with.

1. In the WHMCS menu, go to **Addons → Xtream AI Panel**.
2. Click the **Panels** tab.
3. Click **Add Panel**. You will see a form. Fill it in like this:

| Field | What it is | What to enter |
|---|---|---|
| **Name** | An internal name so you can recognize it. | For example: `My main panel`. |
| **API URL** | The web address of your panel. | For example: `https://panel.example.com` (no trailing slash). |
| **M3U URL** | *Optional.* The M3U link your client will see to play the IPTV. | You can leave it empty if you don't use it. |
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

## 5. Your first product

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
| **Max Connections** | Maximum concurrent connections per line. Only takes effect when the panel uses an **Admin** key; **Reseller** keys ignore this. | Leave `0` to use the package's own count (`0` never means unlimited). Any value between `1` and `100` to override. The number is absolute and means the same everywhere: when the line is created, when you press Sync, and on a product change. To let the customer choose, see "Letting customers buy extra connections" right below. |

### Letting customers buy extra connections

You do not need one product per connection count. Add a WHMCS **Configurable Option** to the product (System Settings → Configurable Options, then assign the group to the product) and the module reads it by name. The name is case-insensitive and the part after `|` is ignored, so `extra_connections|Extra Connections` shows "Extra Connections" to the customer and still works.

| Option name | How the module uses it | Typical setup |
|---|---|---|
| `extra_connections` (or `Extra Connections`, `additional_connections`) | Added on top of the base count. The base is the product's **Max Connections** if it is not `0`, otherwise the connection count of the panel package. | A **Quantity** option from `0` to `4` with a price per unit. On a 1-connection package the customer gets 1 to 5 connections, and `0` costs nothing extra. |
| `max_connections` (or `Connections`) | Replaces the base count outright. `0` falls back to Max Connections, then to the package. | A **Dropdown** with `1`, `2`, `3`... |

The result is sent when the line is created, when you press **Sync line to panel**, and whenever the customer changes the option later through **Upgrade/Downgrade Options** (WHMCS runs the module's package change after the upgrade invoice is paid). Moving the slider back to `0` returns the line to the package count. Like Max Connections, this needs an **Admin** key on the panel; with a Reseller key the panel decides the connection count.
| **Sub-Reseller Member Group ID** | Numeric id of the panel member group new Sub-Reseller accounts will belong to. Only used when **Account Type** is `Sub-Reseller` and the panel key is **Admin**; reseller keys inherit the group from their sub-reseller setup. | For example `4`. |

**What happens when WHMCS creates the service:**

When a customer orders (and the payment is confirmed), WHMCS does this automatically:

1. Generates a **username** and a **password**.
2. Creates the **line** (or the sub-reseller account) in your Xtream AI panel.
3. Saves the username and password so the customer can see them in their area.

You don't need to do anything else at that point.

---

## 6. What your customer sees

When the customer opens their WHMCS client area and opens their service, they see a card with:

- **Username** — their username, with a **Copy** button.
- **Password** — their password, with a **Show** button and a **Copy** button.
- **Status** — the line's status (Active, Suspended…).
- **Expiry Date** — when the line expires (for normal lines).
- **Credits** — their credits (only for Sub-Reseller accounts).
- **Connection URL** — their M3U link, if you set it on the panel.
- **Active Connections** — their active connections right now (what they are watching, from which IP, and for how long).

If the line isn't ready yet, they will see a notice telling them to wait for provisioning to finish.

---

## 7. Day-to-day use

These are the actions you will take as an administrator and what they do in the panel:

| Action | Where you click in WHMCS | What happens in the panel |
|---|---|---|
| **Suspend** | On the customer's service, the suspend button. | For a **line**, the line is disabled and the customer can no longer watch. For a **sub-reseller**, the panel API does not expose a reseller status field, so the module surfaces a clear error and keeps the WHMCS to panel link intact so you can disable the account in the panel yourself. |
| **Unsuspend** | On the customer's service, the unsuspend button. | For a **line**, the line is enabled again. For a **sub-reseller**, the module surfaces the same clear error for the same reason and preserves the WHMCS to panel link so you can re-enable the account in the panel yourself. |
| **Renew** | When the invoice / service is renewed. | The line is renewed and its expiry date is updated. |
| **Terminate** | On the customer's service, the terminate/cancel button. | For a **line**, the line is deleted from the panel. For a **sub-reseller**, the module surfaces a clear error because the panel API cannot disable the reseller and it preserves the WHMCS to panel link so you can disable the account in the panel yourself without losing state. |
| **Change password** | On the customer's service, the change password option. | The password is changed in the panel and updated for the customer. |
| **Sync line to panel** | On the customer's service (admin area), the **Sync line to panel** button. | The current product's bouquets, notes and connection count (Max Connections plus any connections configurable option) are pushed to the line in the panel. Use this after you edit the product's config options without changing the product. |

**Product upgrade or downgrade.** When you change the WHMCS product of a service, or the customer changes a configurable option such as extra connections, the module handles it automatically:

- **Same panel package, different bouquets or connections:** the module pushes the new values to the existing line in the panel.
- **Different panel package, panel with an Admin key:** the module applies the new package to the same line. The customer keeps their username, password and expiry date, nothing is charged in credits, and the new product's bouquets and notes are applied at the same time. The connection count is resolved exactly as when the line is created (Max Connections plus any connections configurable option) and sent as one absolute number. The restreamer flag follows the new package.
- **Different panel package, panel with a Reseller key:** the module refuses the change with a clear message. To move that customer to a different panel package, terminate the current service and re-provision the new product, or switch the panel entry to an Admin key.

**The bouquets of the new product have to belong to the new package.** If one of them does not, the panel refuses the change and tells you which ids are wrong: nothing is applied to the line and the service stays on its previous panel package. Fix the product's Bouquets field and try again, or leave it empty so the line gets every bouquet of the new package.

Package changes need your panel to have been updated on or after **2026-09-14**. On an older panel the change is **not** applied: the line keeps its original package, only the bouquets, notes and connections are pushed, and WHMCS still reports success and records the new product.

---

## 8. Bulk tools

The **Bulk tools** tab (**Addons → Xtream AI Panel → Bulk tools**) does three jobs that would otherwise mean editing services one by one. They run in batches in your browser (100 lines per request when indexing, 100 services when linking, 20 when syncing) and show a progress bar, a counter for each result and one row per service. They are safe to run again: nothing is ever duplicated and nothing is deleted from the panel. If a run stops halfway (the panel went away, the browser tab was closed), just run it again: indexing starts over from scratch, and linking refuses to run until the index has been completed once.

The **Panel** dropdown at the top decides which panel everything below works on. Change it and the page reloads on that panel.

### 8.1 Index panel lines

This reads the lines that already exist on the panel and keeps a local copy of them: line id, username, expiry, status, and the WHMCS service number found in the line notes.

1. Choose the panel in the **Panel** dropdown.
2. In the first card, click **Index lines**.
3. Wait for the progress bar to finish. The counters tell you how many lines were read and how many are in the local index now.

Run this before the other two tools. It only reads from the panel, so it changes nothing there. If you run it again, the index of that panel is rebuilt from scratch.

### 8.2 Link existing services

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

Nothing is created or deleted on the panel: this tool only restores the link between WHMCS and the line.

### 8.3 Sync all services

This runs the same action as the **Sync line to panel** button on each service, but for all of them at once: the bouquets, the notes and the connection count (Max Connections plus any connections configurable option such as `extra_connections`) of each product are recalculated and pushed to its line on the panel.

1. Choose the panel. (Linking is not required to have run in the same session, but the services do need a linked line.)
2. Tick **Include Suspended services** if suspended services should be updated too.
3. Click **Sync services**.

**Warning:** this writes to **every active linked line** of the selected panel, one by one, with the configuration of its product. Run it when the product configuration is final. Use it after you change a connection configurable option (or a product's Max Connections) so the new count reaches every customer line. Services whose product is a Sub-Reseller are reported as `skipped_sub_reseller`, and the results table marks every other service as `synced` or `error`.

---

## 9. Sub-Reseller products, explained simply

**What they are for:** a Sub-Reseller product gives your customer their **own reseller account** on the panel, with their **own credits**. This lets your customer resell lines on their own.

**How to set them up:** when creating the product, in the Module Settings tab:

- Set **Account Type** to `Sub-Reseller`.
- In **Credits**, enter how many credits they receive on creation (and on each renewal).

You don't need to choose Package or Bouquets for this type: the module ignores them and uses the credits instead.

**The Sub-Resellers screen in the addon:** in **Addons → Xtream AI Panel → Sub-Resellers** you will see a list of your sub-resellers with their username, email, status, and **credits**. To adjust someone's credits:

1. Enter a number in the **± credits** field (with `+` to add or `-` to subtract).
2. Enter an optional **Reason**.
3. Click **Apply**.

---

## 10. All the addon screens, one by one

Inside **Addons → Xtream AI Panel** you have these tabs:

**Dashboard** — the summary. It shows cards with: **Credits**, **Panels** (how many panels there are and how many are healthy), **Sub-Resellers**, and **Lines**. Below that, the status of each panel and some quick links.

**Panels** — the list of your panels with their status, SSL, last check, and actions (Test, Edit, Activate/Deactivate, Delete). The **Add Panel** / **Edit Panel** form is also here.

**Sub-Resellers** — the list of sub-resellers and their credits, with the credit adjustment form.

**Lines** — to search for lines. You can filter by **username** (the "Username contains…" field) and by **status** (All statuses / Enabled / Disabled).

**Catalog** — to see what is on your panel: **Live Streams** (live channels) and **VOD** (movies and series). Each has its own search box.

**Bulk tools** — the three operations that work on many services at once: **Index panel lines**, **Link existing services** and **Sync all services**. See section 8.

**Module Logs** — a history of what the module has done (each call to the panel API), with date, action and a short summary.

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

## 11. Common problems

| Message you might see | What it means | What to do |
|---|---|---|
| **Panel authentication failed.** | The panel's API key is wrong. | Check the API key in Panels → Edit and test again with Test. |
| **The reseller does not have enough credits or user slots.** | Your panel account ran out of credits or out of slots to create more lines. | Add credits or slots in your panel (or to your reseller account). |
| **No panel found. Add and activate a panel in Addons → Xtream AI Panel.** | The product has no panel assigned, or there are no active panels. | Add and activate a panel in Addons → Xtream AI Panel, then choose it in the product. |
| **No package selected for this product.** | The product has no package chosen. | In the product's Module Settings tab, choose a Package. |
| **Addon not installed. Install and activate the Xtream AI Panel addon first.** | The addon is not installed or activated. | Activate it in System Settings → Addon Modules. |
| **Invalid security token. Please try again.** | Your admin session expired or the page loaded incorrectly. | Reload the page and repeat the action. |
| **This service has no panel line yet. Provision it first.** | The service has no line created in the panel yet. | Create the service (or wait for WHMCS to finish creating it). |
| **Could not load panel data…** | Could not read the panel information (packages, bouquets, etc.). | Check the panel connection with the Test button and make sure the API key has permissions. |
| **Panel URL is required.** / **API key is required.** | Missing data when testing the connection. | Enter the API URL and the API key and test again. |

---

## 12. Frequently asked questions

**Do I need to be a panel administrator?**
For Line products, no. For Sub-Reseller products, yes: the panel key must be an admin key. Changing the panel package of a line that is already running (a WHMCS product upgrade or downgrade) also needs an admin key; with a reseller key you terminate the service and re-provision it.

**Are my passwords safe?**
Yes. API keys are stored **encrypted** with WHMCS's own encryption, and they never appear in the logs or in error messages.

**Can I have several panels?**
Yes. Add as many as you want in Panels, and choose which one each product uses.

**I migrated from another module, how do I connect my existing services?**
Go to **Addons → Xtream AI Panel → Bulk tools**, choose your panel in the dropdown, click **Index lines** in the first card and, when it finishes, click **Link services** in the second card. Each service is matched to its line by the WHMCS service number stored in the line notes (template `WHMCS:{service_id}`) or, if there is no tag, by the username: the link is restored without creating, changing or deleting anything on the panel. Afterwards you can run **Sync services** in the third card so every line picks up the bouquets and the connection count of its product.

**Can my customers choose how many connections they want?**
Yes. Add a WHMCS Configurable Option named `extra_connections` (a quantity from 0 upwards, priced per unit) to the product and the module adds it on top of the package's connections, on order and every time the customer changes it later. See "Letting customers buy extra connections" in section 5.

**Does it work with my PHP version?**
Yes, with **PHP 7.2 or higher**.

**Is there a license key?**
No, the module is free and open source (MIT).

---

## 13. Uninstalling

1. In **System Settings → Addon Modules**, find **Xtream AI Panel** and click **Deactivate**. Your data is **kept**, in case you want to reactivate it later.
2. To remove it completely, delete the two folders using the File Manager or FTP:
   - `modules/servers/xtreamai`
   - `modules/addons/xtreamai`

That's it.
