# GroceryList

A minimalist shared grocery list application built with PHP 8 + MySQL + vanilla JavaScript.  
Share a link → anyone with the link can view and edit the list in near real-time (10-second short-polling).  
Optionally **register an account** to own your lists and unlock extra features.

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
│   │   ├── get_updates.php
│   │   ├── register.php    ← User registration
│   │   ├── login.php       ← User login
│   │   ├── logout.php      ← User logout
│   │   ├── me.php          ← Current user info
│   │   └── my_lists.php    ← Lists owned by current user
│   └── assets/
│       ├── css/style.css
│       └── js/app.js
│
├── includes/           ← Shared PHP helpers (not web-accessible)
│   ├── db.php          ← PDO singleton
│   ├── functions.php   ← Utility functions
│   └── auth.php        ← Session-based authentication helpers
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
full schema, including the `users` table and the `owner_id` foreign key on
`grocery_lists`. It is **safe to re-run** after every schema change – all
statements use `CREATE … IF NOT EXISTS` (or `ADD COLUMN IF NOT EXISTS`).

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

## User Accounts (Optional)

The app works perfectly **without an account** – anyone can create and share
lists anonymously, just like before. However, users can optionally **register**
for extra benefits.

### How it works

- Click **Sign in** in the header to open the auth modal.
- Switch between **Sign in** and **Register** tabs.
- Registration requires a username (3–50 alphanumeric/underscore), email, and
  password (6+ chars). Passwords are hashed with `bcrypt`.
- Authentication uses **PHP sessions** (cookie-based).
- Lists created while logged in are automatically associated with your account.
- Anonymous lists (created before signing up) remain accessible to anyone with
  the link.

### Auth API Endpoints

| Endpoint | Method | Description |
|---|---|---|
| `api/register.php` | POST | Create a new account. Body: `{ username, email, password }` |
| `api/login.php` | POST | Sign in. Body: `{ login, password }` (`login` = email or username) |
| `api/logout.php` | POST | Sign out (destroys session) |
| `api/me.php` | GET | Returns current user info or `{ logged_in: false }` |
| `api/my_lists.php` | GET | Returns all lists owned by the authenticated user |

See **[REGISTERED_USERS_PLAN.md](REGISTERED_USERS_PLAN.md)** for the roadmap
of additional features planned for registered users.

---

## Tech Stack

| Layer       | Technology                                      |
|-------------|-------------------------------------------------|
| Backend     | PHP 8.x                                         |
| Database    | MySQL 5.7+ / MariaDB 10.3+                      |
| Frontend    | Vanilla JS (ES6+), CSS3 (Flexbox/Grid), HTML5   |
| Auth        | PHP sessions + bcrypt password hashing           |
| Real-time   | AJAX short-polling (10 s interval, configurable)|
| Persistence | Browser `localStorage` (recent lists history)   |

---

## Development Roadmap

- [x] Phase 1 – MVP: HTML/CSS shell, PHP CRUD API, list creation & sharing
- [x] Phase 2 – Polling engine: `get_updates.php` with `last_updated` optimisation
- [x] Phase 3 – UX & history: "Copy link" button, localStorage recent-lists sidebar
- [x] Phase 4 – User accounts: registration, login, session-based auth, list ownership
- [ ] Phase 5 – Registered-user features: "My Lists" dashboard, list deletion, collaboration roles
- [ ] Phase 6 – PWA: `manifest.json`, service worker, offline queue

---

## License

MIT
