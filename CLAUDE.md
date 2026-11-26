# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

GreenGarden Shop: a Laravel 12 shop selling ornamental plants. It covers orders, payments (COD, VNPay, bank transfer), coupons, membership tiers, an inventory ledger, purchasing, reviews and Q&A, news articles and an admin-managed home page. The UI, domain messages, code comments, docs and commit messages are all in **Vietnamese**. Keep that convention, and reply to the owner in Vietnamese.

The brand is **GreenGarden** everywhere a customer can see it (a test fails if "GreenLife" reappears there). Technical ids intentionally keep the old name: DB `green_life` / `green_life_test`, demo emails `@greenlife.test`, `/var/www/greenlife`, file names in `deploy/`.

## Commands

The real requirement is **PHP ≥ 8.4.1**: `composer.lock` pins Symfony 8.1. MySQL ≥ 8.0.16 is also required. **Tests run on real MySQL, never SQLite.** Business rules depend on CHECK constraints, row locks and generated UNIQUE columns, and the migration aborts on any other engine.

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # demo data: admin@greenlife.test / khach@greenlife.test, password 123456 (seeder refuses to run in production)
php artisan serve                     # + php artisan schedule:work  + php artisan queue:work

php artisan test                                            # full suite (~525 tests, ~3 min). Needs DB green_life_test; user/pass come from .env
php artisan test tests/Feature/Services/PaymentServiceTest.php
php artisan test --filter=test_late_payment_on_cancelled_order_tells_customer_about_refund
vendor/bin/pint --test                                      # formatting (CI blocks on failure)
vendor/bin/phpstan analyse --memory-limit=1G                # Larastan level 6, must stay at 0 errors (CI blocks)
php artisan shop:reconcile                                  # 10 data invariants; exit 1 on any mismatch
```

Browser E2E (manual, not in CI): `tests/Browser/roles.e2e.cjs` (20 steps across customer / staff / admin, including scanning pot codes at packing, a defect report, QR labels and a 390px no-horizontal-overflow check of admin pages). Setup instructions are in the file header (needs `php artisan storage:link` for uploaded images). Use `.cjs`, because `package.json` sets `"type": "module"`. Add `E2E_STUB_CDN=1` on machines without internet: CSP is still checked.

`phpunit.xml` pins `APP_LOCALE=vi` and `APP_NAME="GreenGarden Shop"`, so tests pass even if a local `.env` still says `en` / `Laravel`; the app itself shows English validation messages until `.env` is fixed.

CI (`.github/workflows/ci.yml`) runs PHP 8.4 + a MySQL 8 service. Steps: Pint → tests → `migrate:fresh --seed` + `shop:reconcile` → config/route/view/event cache smoke → `composer audit`, plus a separate Larastan job.

## Architecture

**Thin controllers → services own all writes.** Controllers validate, call a service in `app/Services`, then catch `BusinessException` (whose message is Vietnamese and user-facing) and turn it into a flash message. Never write to orders, payments, stock or tiers from a controller, view, or model event.

**Invariants live in the DB as well as in code** (see `docs/adr/001`):
- CHECK constraints are added with `App\Support\DbCheck`. Money equations are written additively, e.g. `total + discounts = subtotal + shipping_fee`, so they never underflow UNSIGNED columns.
- Generated STORED columns with UNIQUE enforce "at most one". Examples: `payments.success_charge_key` (at most one successful charge per order) and `user_addresses.default_key` (at most one default address).
- The DB connection forces `time_zone=+00:00`.
- The schema is frozen by default: a schema change needs the owner's explicit approval. Approved changes so far are **additive new migration files** only (`2026_11_22_000100` plant detail columns, `2026_11_23_000100` `home_slides`, `2026_11_25_000100` `users.permissions`, `2026_11_25_000200` coupon gifts, `2026_11_25_000300` flash sales, `2026_11_25_000400` stock counts, `2026_11_25_000500` shipping zones + `plants.weight_grams`, `2026_11_26_000100` `contact_channels`, `2026_11_26_000200` `plant_units` + `plant_unit_events` (backfills codes for stock on hand), `2026_11_26_000300` defect reports + `stock_movements.defect_report_item_id`, `2026_11_26_000400` `stock_count_scans` + `stock_counts.unit_event_mark`); never edit an existing migration. Record any new one in `docs/H8-nghiem-thu.md` (criterion C2).

**Concurrency** (`docs/adr/003`): the isolation level is READ COMMITTED, and correctness comes from explicit `lockForUpdate()` calls taken in a **fixed order**:
- Sales: `users → orders → plants (id ASC, via InventoryService::lockPlants) → flash_sale_items (id ASC) → coupons → payments`.
- Purchasing: `goods_receipts → purchase_requests → plants → purchase_request_items`.
- Individual pots (`plant_units`) are always locked after `orders`/`plants`, id ASC: ship `orders → plant_units`; receive return `orders → plants → plant_units`; approve defect report `defect_reports → plants → plant_units`; complete stock count `stock_counts → plants → plant_units`.
- Every write flow runs inside `DB::transaction(fn, 3)`, which retries on deadlock.
- `MembershipService` locks `users` before `orders` and must not be called nested inside an order transaction. That is why `confirmRefund` calls `refreshUser` *after* its own transaction.
- Hidden locks count too: inserting a child row takes an S-lock on its FK parent. `CouponService::update` (admin edits a coupon's plant scope → `coupon_plants`) therefore locks `plants` via `lockPlants` **before** the coupon row; the old controller code did it the other way round and deadlocked with checkout.
- Reviews lock `users → plant_reviews → plants`.

**Order state machine**: `OrderStatus::allowedTransitions()` is the source of truth. Every status change goes through `Orders/OrderStateWriter`, which also writes the history row.

```
pending_payment → pending → confirmed → shipping → completed → returning → returned
   (any state before shipping) → cancelled ;  shipping → returning ;  returning → completed (customer withdraws the return)
```

Who may cancel is decided in `OrderService::authorizeCancel`:
- Customer: only `pending_payment`/`pending`, and only their own order.
- Staff: any state before shipping.
- System: only once payment has expired.

Overdue unpaid orders are cancelled by `orders:expire-unpaid`, which runs every minute. They are also cancelled lazily by `expireIfOverdue` when the customer opens the order.

**Inventory is a ledger** (`docs/adr/002`):
- `stock_movements` is append-only, with real FKs to the source documents.
- `plants.stock` is only a cache of the ledger sum. It may only be changed through `InventoryService::move()` inside `Plant::writingLedger()`; any other write throws.
- **Every pot has its own code** (`plant_units`, 8-char Crockford base32 via `Support\UnitCode`, printed as a QR label; history in append-only `plant_unit_events`). `InventoryService::move()` keeps pots in step with the ledger: opening/purchase receipt create pots, sale return brings the order line's pots back (issuing new codes if the order shipped before pots existed), and `damage`/`adjustment` **require** the exact locked pots (`units:`). Placing/cancelling an order does not touch pots; pots leave the shelf only when staff **scan them at packing** (`OrderService::ship(..., $unitCodes)`; `null` auto-picks FIFO and is only for tests/internal calls — the admin route requires `unit_codes`). Invariant (`shop:reconcile` #10 and `assertLedgerConsistent`): in-stock pots = `plants.stock` + quantity of `pending_payment/pending/confirmed` order lines. Labels: `admin.units.labels` (50×30 mm thermal, `public/js/vendor/qrcode-generator`); lookup: `admin.units.index`; scanner JSON: `admin.units.check`; shared scanner UI `admin/partials/scanner` + `public/js/scanner.js` (USB scanner = keyboard + Enter, phone camera via BarcodeDetector or self-hosted jsQR; camera needs HTTPS and `Permissions-Policy camera=(self)`).
- To fix a stock mismatch, use a stock count. Never `UPDATE plants.stock`. Stock counts are an admin-only flow (`StockCountService`, Admin → Phiếu kiểm kê) done by **scanning pots** (`stock_count_scans`): the sheet snapshots ledger quantities and `unit_event_mark`; on "Chốt phiếu", in-stock pots with no event after the mark that were not scanned become `lost` (`adjustment` −n) and scanned `lost` pots come back (+n), linked to `stock_movements.stock_count_item_id`. Pots received/shipped/returned during the count are never counted as missing.
- Damaged goods go through **defect reports** (`DefectReportService`, Admin → Phiếu báo lỗi): staff with the inventory permission scan defective pots (type, note, ≤3 photos); an admin other than the creator (DB CHECK `chk_dr_four_eyes`) approves → one `damage` −1 movement per pot linked via `defect_report_item_id`. "Báo cáo hàng lỗi" (`admin.defects.report`, reports permission) sums all damage movements by type / plant / supplier of the pot's goods receipt.

**Pricing** (`docs/adr/004`):
- `PricingService::quote()` is the single formula, used for both the cart preview and order placement. It never locks and never writes.
- Money is integer VND (BIGINT). Discounts are spread across order lines with `Support\Allocator` (largest remainder), so line totals always match the order total.
- A coupon that cannot be combined with the tier discount is compared against it, and the better option for the customer wins.
- At checkout the client sends `expected_total`; if it differs from the server quote → `PriceChangedException`.

**Payments** (`docs/adr/005`):
- The `payments` table holds both charge and refund rows. `orders.paid_amount`, `refunded_amount` and `payment_status` are *derived* via `PaymentService::recalc()`/`paymentAttributes()`. Never set them by hand.
- VNPay IPN: the raw payload is logged first to `payment_webhook_logs`, then the HMAC-SHA512 signature and the amount are verified, and processing is idempotent.
- Money arriving after an order is already cancelled never reopens the order: it creates a pending refund and emails the customer.
- Refunds are confirmed manually by an admin.
- The `PaymentGateway` binding lives in `AppServiceProvider`. `FakeGateway` is only used in local/testing; anywhere else a missing VNPay config yields `DisabledGateway`.

**Membership** (`docs/adr/006`):
- Tiers depend on lifetime spend: money actually paid, minus refunds, excluding shipping.
- An order's spend is recorded only after the 7-day return window, by the hourly job `membership:record-spend`.
- Tiers are upgrade-only. A manual downgrade sets a floor that automatic upgrades respect.
- `users.lifetime_spend` (shown as "Tổng chi tiêu" in admin) is this *tier* spend, so it stays 0 until orders are completed, 7 days have passed and the scheduler has run. It is not "total purchases"; owners have mistaken it for a bug.

**Notifications** (`docs/adr/007`):
- Always send through `App\Support\Notifier::send()`. It sends only after the DB commit (`DB::afterCommit`), goes through the database queue, and swallows/reports failures.
- Every money milestone has an email; add a new one as an `OrderNotification` subclass.

**Roles**:
- `users.role` is `customer|staff|admin`.
- Routes in `admin.*` use the `admin` middleware (staff + admin). Staff areas are further grouped by `admin:<permission>` (`App\Enums\StaffPermission`: reports, orders, catalog, inventory, purchasing, reviews, content, flash_sales). `users.permissions` (JSON) lists a staff member's groups; `NULL` means all staff groups (accounts created before permissions existed). Admins always pass. Admins manage staff at Admin → Nhân viên (`StaffService`). `RoutePermissionMatrixTest` checks every permission-grouped route automatically.
- Money and policy actions additionally use `admin:admin`: confirming bank transfers, refunds, approving or rejecting purchase requests, stock counts, coupons, tiers, customers, staff. Purchase requests are created by staff only (`PurchaseService` refuses admins).
- Purchase requests follow a 4-eyes rule: the creator cannot approve their own request.
- Middleware priority runs `EnsureUserIsActive` → `AdminMiddleware` *before* route-model binding. As a result, unauthorized users get the same response whether or not the id exists (no id probing).
- Any 403 on a web page (session + not JSON) is turned into a redirect by `App\Support\NoAccessRedirect` (registered in `bootstrap/app.php`): customers/guests → `home`, staff/admin → their admin landing (dashboard, or the first permitted section), with flash `error` = `NoAccessRedirect::DENIED`. JSON calls and session-less routes (`/storage/...`) still get 403. In tests use `->assertNoAccess($route)` (macro in `tests/TestCase.php`) and `$this->isDenied($response)`; JSON calls keep `assertForbidden()`.
- Customer resources are owner-only via `OrderPolicy`, plus owner checks in controllers.

Business parameters (shipping fee, payment hold time, return window, bank info, seller info printed on invoices, home "hot products" window, trusted proxies, display timezone) live in `config/shop.php`. Change policy there, not in code. Scheduled jobs and artisan commands (`shop:reconcile`, `shop:create-admin`, `reviews:rebuild`, `membership:recalculate-all`, …) are closures in `routes/console.php`.

**Other domain rules that are easy to break:**
- Reviews (`ReviewService`): only a customer with a *completed* order line for that plant may review. A submitted or edited review is published immediately and counted in `plants.rating_sum/count`; staff hide it with "Từ chối". A rejected review that is edited (even after deleting it) goes back to `pending`, so customers cannot re-publish what the shop removed.
- Orders: customers may edit `orders.note` only while `pending_payment`/`pending` (`OrderService::updateNote`, `OrderService::NOTE_EDITABLE`). Invoices (`orders.invoice`, `admin.orders.invoice`, view `invoices/order`) are read-only pages and must not call `expireIfOverdue`.
- Addresses use the post-01/07/2025 divisions: province → ward from `public/data/vn-dvhc-2025.json` (34 provinces, 3,321 wards), read by `App\Support\VnDivisions` on the server and by `public/js/address.js` in the browser. There is no district level any more: `AddressRules::prepare()` stores the province name in the required `district` column, and `full_address` hides that duplicate. Old 3-level rows are kept as they are.
- Admin coupon edits go through `CouponService::update`; once a coupon has a redemption only `CouponService::EDITABLE_WHEN_LOCKED` fields may change.
- Flash sales (`FlashSaleService`): staff create and submit, admins approve, reject or cancel. Admins cannot create a sale, and the same plant cannot be in two approved sales that overlap in time. While a sale runs, `PricingService` uses `sale_price` as the line's unit price (tier discount and coupon still apply on top), but only if the remaining quota covers the line's whole quantity **and** `sale_price` is below the plant's current list price; otherwise it uses the normal price (quota case adds a notice). The one rule is `PricingService::unitPrice()` (cart page uses it too) and `FlashSaleService::activeItems()` already drops items not cheaper than the list price (`CHEAPER_THAN_LIST`), so a list price lowered below a running sale never overcharges and never consumes a slot; editing a plant's price under an approved sale shows a warning. Approving a sale locks its plants (`flash_sales → plants`) before the overlap check, so two admins cannot approve overlapping sales for the same plant. Placing an order locks `flash_sale_items` and increments `sold_count`, and `order_items.flash_sale_item_id` records the slot. Cancelling releases it. `shop:reconcile` checks `sold_count` against non-cancelled order lines.
- Coupon wallet ("Mã của tôi", `account.coupons`): `coupons.owner_user_id` marks a personal coupon. `CouponService::assertUsable` rejects anyone else, and otherwise it is a normal coupon (usage_limit 1, released on cancel). `CouponGiftService` issues them from `coupon_gift_rules`, via the hourly job `coupons:issue-gifts`. Only completed orders past the return window, completed after the rule was created, qualify. `UNIQUE(gift_rule_id, source_order_id)` prevents double gifts. Admins can also gift manually from the customer page.
- Dashboard revenue (`Admin/DashboardController::NET_GOODS`) is goods after discounts minus refunds of completed orders, excluding shipping, bucketed by `completed_at` in `shop.display_timezone`. The daily chart and the revenue card share that one expression.
- Shipping fee (`ShippingFeeCalculator`, Admin → Phí vận chuyển, admin-only): orders that reach the free-shipping threshold (general or per tier) still pay 0. Otherwise the fee depends on the zone of the delivery province (`shipping_zones` + `shipping_zone_provinces`; unassigned provinces fall back to the default zone) and on the package weight: `base_fee` covers the first `base_weight_grams`, then `extra_fee_per_kg` is charged per extra kg, rounded up. Weight is Σ `plants.weight_grams` × qty, with `shop.shipping.default_weight_grams` used when a plant has no weight. With no zones configured, the old flat `shop.shipping.base_fee` applies. `PricingService::quote(..., $province)` receives the province. The checkout page re-quotes through `checkout.shipping-quote` when the province changes, and `ShippingZoneSeeder` holds demo zones (not part of `DatabaseSeeder`).
- "Hot products" on the home page are the best sellers over `shop.home.hot_days`, counting only `pending/confirmed/shipping/completed` orders of active plants (`HomeController::hotPlants`).
- Social bubble: `contact_channels` (Zalo / Facebook / TikTok, Admin → Kênh liên hệ, content permission), host allow-list per channel in `ContactChannel::HOSTS`; rendered by `partials/social-bubble` (View composer, `<details>` + inline SVG, no JS/CDN).

**Content and uploads:**
- Articles (`Admin/ArticleController`, `ArticleService`) are Markdown rendered by `Article::renderedContent()` with `html_input=strip` and unsafe links blocked. Never print article content raw. Scheduled posts are `status=published` with a future `published_at`; `Article::published()` hides them until then.
- Uploaded images (article covers, inline images, home slides) go through `App\Services\ImageStorage`: JPG/PNG/WEBP/GIF only (no SVG), ≤ 4 MB, server-generated UUID names on the `public` disk. The DB stores relative `/storage/...` paths, so `php artisan storage:link` is recommended. Without the link the `public` disk serves `/storage/...` itself (`'serve' => true` in `config/filesystems.php`; the private `local` disk has `serve` off so it no longer claims that path and answers 403), which works but is slower and uncached. The article editor has a server-side "Xem trước" (`admin.articles.preview`, `Article::renderMarkdown`). Replacing or deleting a cover or slide deletes the old file; inline article images are not cleaned up.

**Front end:** there is no build step. Bootstrap 5 and Font Awesome come from CDNs, but layout-critical CSS lives in `public/css/app.css` (design tokens, admin shell, mobile tables), so admin pages must stay usable when the CDN fails. `public/css/invoice.css` is self-contained for printing. Own scripts go in `public/js/` (`address.js`, `article-editor.js`, `slider.js`, `scanner.js`, `packing.js`); third-party JS is self-hosted in `public/js/vendor/` (see its README). `slider.js` drives both the home slider (`[data-gg-slider]`) and the "Sản phẩm hot" strip (`[data-gg-carousel]`); both always autoplay (even with reduced motion, then without smooth scrolling) and the slider has a pause button. Dashboard pies use `admin/partials/donut` with fixed colours from `DashboardController::PALETTE` (colour follows the category / payment method, not its rank) and a CSS `conic-gradient` fallback when Chart.js fails to load. The admin layout has `@stack('scripts')`. Flash messages (`success`/`warning`/`error`, plus the admin `$errors` summary) render as floating toasts (`partials/toasts` + `public/js/toasts.js`, no CDN) instead of alerts that push the page; `toasts.js` also restores the scroll position after a POST form that redirects back to the same URL, and exposes `window.ggToast(type, text)`. CSP is set in `app/Http/Middleware/SecurityHeaders.php`: scripts from `'self'` and `cdn.jsdelivr.net` only (Chart.js 4.5.1 for the dashboard), `connect-src 'self'`. Blade helpers: `@money` (`Support\Format::money`) and `@vndt` (UTC → `shop.display_timezone`). Translations are in `lang/vi`.

## Rules the test suite enforces

These tests read the router and code automatically, so new code is checked without extra wiring:
- **No GET route may write to the DB** (`tests/Feature/Audit/AcceptanceCrawlTest`). It crawls every GET route × 4 roles and also asserts no 500s. A new GET route with route parameters needs a fixture added there and in `RoutePermissionMatrixTest` (existing keys include `order`, `plant`, `coupon`, `article`, `slide`, …). Only the routes in `GET_MAY_WRITE` are allowed to write.
- **Every `admin.*` route** is permission-checked per role (`RoutePermissionMatrixTest`). A new money or policy route must use `admin:admin`.
- **Main pages ≤ 15 SQL queries**, and the count must not grow with data (`QueryBudgetTest`). `Model::preventLazyLoading()` is on outside production. Eager-load and paginate list pages.
- **Order state matrix** (`OrderStateMatrixTest`): every allowed action must leave `shop:reconcile` clean; every forbidden action must have no side effects.
- **Bug fixes need a regression test** that fails on the old code (`tests/Feature/Regression/`). **New flows that write stock or money need a race scenario** in `tests/Feature/Concurrency/ConcurrencyTest`. Races run as real PHP processes via `tests/Concurrency/worker.php`.
- Test helpers live in `tests/Concerns/BuildsOrders.php`: `customer()`, `admin()`, `place()`, `ipn()`, `stock()`, `assertLedgerConsistent()` (ledger + pot invariant), `packCodes()` (pot codes to ship an order through the admin route), `shipping()` (a valid new-style address). The base `TestCase` seeds, including demo plant details, articles and 5 home slides. Tests that upload files use `Storage::fake('public')`.

## Docs

- `docs/adr/`: architecture decisions and why the alternatives were rejected. Read the relevant ADR before changing one of these areas.
- `docs/H5-ra-soat-luong.md`: the flow and permission audit.
- `docs/H7-van-hanh.md`: production runbook (env checklist, VNPay IPN URL, cron/worker, backup/restore including `storage/app/public` uploads, deploy/rollback, `php.ini` upload limits, updating the province/ward file, what to do when reconcile fails). Scripts are in `deploy/`; `deploy.sh` also creates the `public/storage` link.
- `docs/H8-nghiem-thu.md`: acceptance against criteria C1–C9, a table of changes made after acceptance, and what has **not** been verified yet (real VNPay sandbox, SMTP, real CDN, real servers).
