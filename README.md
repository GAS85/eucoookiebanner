# EU Cookie Banner — PrestaShop Module

**Module name:** `eucoookiebanner`  
**Compatible with:** PrestaShop 8.x / 9.x  
**Version:** 0.3.0

---

## Features

- Full-page frosted overlay blocks all interaction until the visitor makes a choice.
- **"I Agree"** — sets a 365-day cookie and dismisses the banner.
- **"Leave Shop"** — redirects the visitor to a configurable URL (default: Google).
- Clicking the **backdrop** or pressing **Escape** also triggers "Leave Shop".
- Admin can set **custom banner text** (HTML supported) from the module settings page.
- Admin can set a **custom "Leave" redirect URL**.
- WCAG-friendly: keyboard trap, ARIA attributes, focus management.
- Body scroll is locked while the banner is visible.

---

## Installation

1. Copy the `eucoookiebanner/` folder into `<prestashop_root>/modules/`.
2. Go to **Back Office → Modules → Module Manager**.
3. Search for **"EU Cookie Banner"** and click **Install**.

---

## Configuration

1. After installation, click **Configure** on the module.
2. Fill in:
   - **Banner Text** — the message shown in the overlay (HTML allowed, e.g. links to your Privacy Policy).
   - **"Leave Shop" Redirect URL** — where visitors are sent if they do not agree (default: `https://www.google.com`).
3. Click **Save**.

---

## How it works

| Action | Result |
|---|---|
| Click **I Agree** | Cookie `eucookie_accepted=1` is set for 365 days; overlay dismissed |
| Click **Leave Shop** | Visitor redirected to configured URL |
| Click **backdrop** | Same as Leave Shop |
| Press **Escape** | Same as Leave Shop |

Once the cookie is set, the banner is never shown again to that visitor (until the cookie expires or is cleared).

---

## Hooks used

| Hook | Purpose |
|---|---|
| `displayHeader` | Injects CSS + JS into `<head>` |
| `displayBeforeBodyClosingTag` | Renders the overlay HTML before `</body>` |

---

## File structure

```
eucoookiebanner/
├── eucoookiebanner.php          ← Module main class
├── config.xml                   ← Module metadata
├── README.md
└── views/
    ├── css/
    │   └── cookie_banner.css    ← Overlay styles
    ├── js/
    │   └── cookie_banner.js     ← Cookie logic & UX
    └── templates/
        └── hook/
            └── cookie_banner.tpl ← Smarty overlay template
```

---

## Customisation tips

- To change the accent colour (purple `#9b7cf8`), edit `cookie_banner.css` and replace `#9b7cf8` / `#b49bff`.
- To change the cookie lifetime, edit the `COOKIE_DAYS` constant in `cookie_banner.js` and `COOKIE_EXPIRE` in `eucoookiebanner.php`.
- The banner text supports full HTML — add a link to your Privacy Policy page directly from the admin panel.
