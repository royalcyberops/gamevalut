# GameVault Shop

Steam-inspired game store demo (college mini-project) — PHP + MySQL, no frameworks.
Sells PHYSICAL game copies (discs / boxed editions) delivered to your address — no digital downloads or keys.
Browse games, add to cart, check out with Card or UPI, track orders. Admin manages orders and catalogue.

## Run (XAMPP)
1. Start Apache + MySQL
2. Copy folder to `C:\xampp\htdocs\steam`
3. Open `http://localhost/steam/tools/setup.php` (creates DB, tables, seeds 22 games)
4. Open `http://localhost/steam/tools/migrate_shop.php` (adds shop tables + admin account; safe to re-run)
5. Open `http://localhost/steam/index.php`

## Test accounts
- Admin: `admin` / `Admin@123` (Staff panel at `admin.php`)
- Customer: register a new account via `register.php`

## Pages
- `index.php` — Home / catalogue (Add to Cart)
- `categories.php` — Browse by category
- `cart.php` — Session cart (qty 1–5, server-side prices)
- `checkout.php` — Delivery details + Card / UPI order
- `dashboard.php` — My orders (cancel while Pending)
- `admin.php` — Staff only: order statuses, revenue stats, add games
- `register.php` / `login.php` / `logout.php` — Auth (bcrypt, CSRF, session hardening)

## Shop flow
1. Add games to cart (`cart.php`, stored in `$_SESSION`)
2. Checkout (`checkout.php`): contact + address + delivery slot, validated server-side
3. Order saved atomically (`orders` + `order_items` in one transaction, totals from DB prices)
4. Status lifecycle: `Pending` → `Confirmed` → `Delivered` (admin advances it), or `Cancelled` (customer while `Pending` / admin anytime)
5. Payment: Card or UPI (validated server-side, only the method is stored).

## Buttons
- Green (`btn-green`) = add / create — Add to Cart, Add Game, Browse category
- Warm amber (`btn-warm`) = primary shop flow — Browse games, Proceed to Checkout, Pay & Place Order, Shop Games
- Royal (`btn-royal`) = auth — Login, Register
- Blue (`btn-blue`) = admin save — Save (order / game edits in `admin.php`)
- Cancel outline (`btn-cancel`) = danger — Remove (cart line), Cancel (order)

## Tables (`gamevault_db`)
- `users` (with `is_admin` flag), `categories`, `games`
- `orders` (contact, address, delivery slot, CARD/UPI, status, total)
- `order_items` (per-game qty + purchase-time unit price)
- `bookings` (legacy reservation table, unused by the shop flow)
