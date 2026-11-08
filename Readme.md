# Uwu Cafe Order (•⩊•)

A small PHP/MySQL web app for a café: customers browse the menu and place orders, staff process them, and admins manage products and see sales statistics, with an Excel export and a command-line API.

Built as a web development practical (PHP, MySQLi, Bootstrap 5).

## Features

**Guests**
- Browse the menu with prices, average ratings and product images
- Order as a guest (name only)
- Live order total and a confirmation prompt before an order is sent

**Patrons**
- Everything a guest can do
- Discount on ordering
- Order history with a status badge for each order
- Rate each product of a finished order with a 1-5 cup rating (can be changed later)

**Staff**
- Everything a patron can do
- Bigger discount on ordering
- Access the user database, create a user (patron role only)
- Access the order queue for all the un-assigned orders  and self-assigned orders, showing the customer type (guest/patron/staff) and the status
- Bulk actions: **Take order** (assigns it to you and sets it to active), **Complete order**, **Archive order**

**Admins**
- Everything staff can do, except place an order (thus no discount)
- Add, edit and delete products
- Access the user database and create/edit/delete a user (any role)
- Access the order queue for all the orders without any restriction, showing the customer type (guest/patron/staff) and the status
- **Sales stats dashboard** (`stats.php`): date and status filters, KPI cards, charts (revenue over time, orders by status, top products, guest vs member vs staff, average rating) and a paginated orders table
- **Excel export** (.xlsx) with three sheets: orders, order lines, summary
- **Command-line API** with bearer tokens (see [API](#api))

## Order lifecycle

```
pending  ->  active  ->  done  ->  archived
(new)        (taken)     (completed)
```

- Taking an order assigns the staff member and cannot be done twice for the same order.
- Staff can only complete orders they took; admins can complete any.
- Customers can rate products once the order is `done` or `archived`.
- Revenue figures only count `done` and `archived` orders, using `orders.total`.

## Requirements

- PHP 7.4 or newer with `mysqli` (mysqlnd), `zip`, `gd`, `xml` and `mbstring`
- MySQL 8.0.16+ or MariaDB 10.2+ (the rating `CHECK` constraint is only enforced from these versions)
- Apache with `mod_rewrite` and `AllowOverride All` for the project folder
- [Composer](https://getcomposer.org/) (for PhpSpreadsheet, used by the Excel export)

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/huguesdreyfus/webdev_practical9.git
   cd webdev_practical9
   ```

2. **Install the dependencies**
   ```bash
   composer install
   ```

3. **Create the database** and import the schema (`database/db_cafe.sql`).

4. **Configure the connection** by editing the database credentials in `config.php`.

5. **Serve the folder with Apache**, for example by placing it in `/var/www/html/` and opening `http://localhost/webdev_practical9/`.

6. **Create an admin account.** Register a user through `register.php`, then set its `role` to the admin role in the `users` table (role values are defined in `roles.php`).

## Database

| Table | Purpose | Key columns |
|---|---|---|
| `users` | Accounts | `id`, `username`, `role` |
| `products` | Menu items | `id`, `category`, `name`, `price` (in thousands of IDR), `image_link` |
| `orders` | One row per order | `id`, `user_id` (NULL for guests), `customer_name`, `total`, `status` (`pending`, `active`, `done`, `archived`; default `pending`), `staff_id` (NULL until taken), `created_at` |
| `order_items` | Lines of an order | `id`, `order_id`, `product_id`, `quantity`, `unit_price`, `discount_percent` |
| `ratings` | Product ratings | `id`, `user_id`, `order_id`, `product_id`, `value` (1-5), unique on (`user_id`, `order_id`, `product_id`) |
| `api_tokens` | API access tokens | `id`, `user_id`, `name`, `token_hash`, `created_at`, `expires_at`, `last_used_at`, `revoked_at` |

Foreign keys from `order_items` and `ratings` to `orders` should use `ON DELETE CASCADE` so that deleting an order also removes its lines and ratings.

Create the tokens table with:

```sql
CREATE TABLE api_tokens (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    name         VARCHAR(100) NOT NULL,
    token_hash   CHAR(64) NOT NULL UNIQUE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME NULL,
    last_used_at DATETIME NULL,
    revoked_at   DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

Recommended indexes for the stats filters:

```sql
ALTER TABLE orders ADD INDEX idx_orders_created (created_at), ADD INDEX idx_orders_status (status);
```

## API

The API lets an admin download the stats from a terminal. Only a hash of each token is stored in the database.

### 1. Allow the Authorization header (Apache)

Make sure this line is in `.htaccess`, otherwise Apache may strip the header:

```apache
SetEnvIfNoCase Authorization "(.+)" HTTP_AUTHORIZATION=$1
```

### 2. Create a token (on the server)

```bash
php api_token.php create <admin_username> "my laptop" 90   # 90 days; 0 = never expires
php api_token.php list
php api_token.php revoke <token_id>
```

The token is printed once and cannot be shown again.

### 3. Call it

```bash
export UWU_TOKEN='uwu_...'
URL='https://your-server/api_stats.php'

# JSON summary for October 2026
curl -s -H "Authorization: Bearer $UWU_TOKEN" "$URL?from=2026-10-01&to=2026-10-31"

# Only the KPIs (needs jq)
curl -s -H "Authorization: Bearer $UWU_TOKEN" "$URL?from=2026-10-01&to=2026-10-31" | jq .kpis

# Weekly grouping, including every order with its items
curl -s -H "Authorization: Bearer $UWU_TOKEN" "$URL?group=week&orders=1"

# Excel file
curl -fS -H "Authorization: Bearer $UWU_TOKEN" "$URL?from=2026-10-01&to=2026-10-31&format=xlsx" -o stats.xlsx
```

| Parameter | Values |
|---|---|
| `from`, `to` | `YYYY-MM-DD`, both optional and inclusive |
| `status` | `all` (default), `pending`, `active`, `done`, `archived` |
| `group` | `day` (default), `week`, `month` |
| `format` | `json` (default) or `xlsx` |
| `orders` | `1` adds the full list of orders with their items to the JSON |

| HTTP status | Meaning |
|---|---|
| 200 | Success |
| 400 | Invalid parameter (the message says which) |
| 401 | Missing, invalid, expired or revoked token |
| 403 | The token's owner is not an admin |
| 405 | Method other than GET |

Use HTTPS when the server is not on your own machine, since a bearer token sent over plain HTTP can be read on the way.

## Project structure

| File | Purpose |
|---|---|
| `index.php` | Menu and order form |
| `place_order.php`, `order_success.php` | Order creation and confirmation |
| `history.php`, `rating_update.php` | Customer order history and ratings |
| `orders.php`, `order_update_status.php`, `delete_order.php` | Order management for staff and admins |
| `create.php`, `edit.php`, `delete.php` | Product management (admin) |
| `users.php`, `delete_user.php` | User administration |
| `login.php`, `logout.php`, `register.php` | Authentication |
| `stats.php`, `stats_export.php` | Stats dashboard and Excel export (admin) |
| `stats_common.php`, `stats_data.php` | Shared filters and queries |
| `api_stats.php`, `api_auth.php`, `api_token.php` | Command-line API and token management |
| `auth.php`, `roles.php`, `config.php`, `navbar.php` | Sessions and permissions, role constants, database connection, shared navigation |

## Security notes

- All SQL uses prepared statements, with whitelisted values for anything that goes into a query string (sort order, grouping, status).
- State-changing forms carry a CSRF token that the server verifies.
- Output is escaped with `htmlspecialchars()`.
- Permissions are enforced on the server in every handler, not only by hiding buttons.
- Text in the Excel export is written as plain string cells, so a guest name like `=HYPERLINK(...)` cannot run as a formula.
- API tokens are random 256-bit values stored as SHA-256 hashes, accepted only in the `Authorization` header, and admin status is re-checked on every request.
- `.htaccess` blocks web access to `vendor/` and the Composer files.
- Never commit `config.php` with real credentials, and never paste a real API token into a file you commit.