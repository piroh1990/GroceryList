# GroceryList

A minimalist, no-account shared grocery list application built with PHP 8 + MySQL + vanilla JavaScript.  
Share a link → anyone with the link can view and edit the list in near real-time (10-second short-polling).

---

## Directory Layout

```
/
├── public_html/        ← Web root (point your vhost / document root here)
│   ├── index.php       ← Single entry-point (home + list view)
│   ├── api/            ← JSON REST-like endpoints
│   │   ├── create_list.php
│   │   ├── add_item.php
│   │   ├── update_item.php
│   │   ├── delete_item.php
│   │   ├── rename_list.php
│   │   └── get_updates.php
│   └── assets/
│       ├── css/style.css
│       └── js/app.js
│
├── includes/           ← Shared PHP helpers (not web-accessible)
│   ├── db.php          ← PDO singleton
│   └── functions.php   ← Utility functions
│
├── config/             ← Configuration (not web-accessible)
│   └── config.example.php  ← DB credentials & app settings (template)
│
├── scripts/            ← Deployment & maintenance scripts
│   ├── db.schema.sql   ← Full MySQL schema
│   └── deploy.php      ← Runs the schema against your DB
│
└── agent/              ← Background / CLI agent scripts (not web-accessible)
    └── README.md
```

---

## Quick Start

### 1. Clone and configure

```bash
git clone https://github.com/piroh1990/GroceryList.git
cd GroceryList
```

Copy the example config and edit it with your database credentials:

```bash
cp config/config.example.php config/config.php
```

Then edit **`config/config.php`**:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'grocery_app');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('APP_BASE_URL', 'http://yourdomain.com/public_html');
```

### 2. Deploy the database schema

Run the deploy script from the repository root:

```bash
php scripts/deploy.php
```

This creates the `grocery_app` database (if it doesn't exist) and applies the
full schema. It is **safe to re-run** after every schema change – all statements
use `CREATE … IF NOT EXISTS`.

### 3. Configure your web server

Point the document root of your virtual host to the **`public_html/`** directory.

**Apache** – add to `.htaccess` or vhost:

```apache
DocumentRoot /path/to/GroceryList/public_html
```

**Nginx** – set in your server block:

```nginx
root /path/to/GroceryList/public_html;
index index.php;
```

> **Security note:** the `config/`, `includes/`, `scripts/`, and `agent/`
> directories must **not** be web-accessible. When `public_html/` is your document
> root these directories are already outside the web root, so no extra rules are needed.

### 4. Open the app

Navigate to your domain. Click **Create List**, share the URL with anyone, and
start adding items!

---

## Tech Stack

| Layer       | Technology                                      |
|-------------|-------------------------------------------------|
| Backend     | PHP 8.x                                         |
| Database    | MySQL 5.7+ / MariaDB 10.3+                      |
| Frontend    | Vanilla JS (ES6+), CSS3 (Flexbox/Grid), HTML5   |
| Real-time   | AJAX short-polling (10 s interval, configurable)|
| Persistence | Browser `localStorage` (recent lists history)   |

---

## Development Roadmap

- [x] Phase 1 – MVP: HTML/CSS shell, PHP CRUD API, list creation & sharing
- [x] Phase 2 – Polling engine: `get_updates.php` with `last_updated` optimisation
- [x] Phase 3 – UX & history: "Copy link" button, localStorage recent-lists sidebar
- [ ] Phase 4 – PWA: `manifest.json`, service worker, offline queue

---

## License

MIT
