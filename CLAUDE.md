# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

GreenLife Shop: a Laravel 12 shop selling ornamental plants. It covers orders, payments (COD, VNPay, bank transfer), coupons, membership tiers, an inventory ledger, purchasing, and reviews and Q&A. The UI, domain messages, code comments, docs and commit messages are all in **Vietnamese**. Keep that convention, and reply to the owner in Vietnamese.

## Commands

The real requirement is **PHP ≥ 8.4.1**: `composer.lock` pins Symfony 8.1. MySQL ≥ 8.0.16 is also required. **Tests run on real MySQL, never SQLite.** Business rules depend on CHECK constraints, row locks and generated UNIQUE columns, and the migration aborts on any other engine.

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # demo data: admin@greenlife.test / khach@greenlife.test, password 123456 (seeder refuses to run in production)
php artisan serve                     # + php artisan schedule:work  + php artisan queue:work

php artisan test                                            # full suite (~370 tests, ~100s). Needs DB green_life_test; user/pass come from .env
php artisan test tests/Feature/Services/PaymentServiceTest.php
php artisan test --filter=test_late_payment_on_cancelled_order_tells_customer_about_refund
vendor/bin/pint --test                                      # formatting (CI blocks on failure)
vendor/bin/phpstan analyse --memory-limit=1G                # Larastan level 6, must stay at 0 errors (CI blocks)
php artisan shop:reconcile                                  # 8 data invariants; exit 1 on any mismatch
```

Browser E2E (manual, not in CI): `tests/Browser/roles.e2e.cjs`. Setup instructions are in the file header. Use `.cjs`, because `package.json` sets `"type": "module"`. Add `E2E_STUB_CDN=1` on machines without internet: CSP is still checked.

CI (`.github/workflows/ci.yml`) runs PHP 8.4 + a MySQL 8 service. Steps: Pint → tests → `migrate:fresh --seed` + `shop:reconcile` → config/route/view/event cache smoke → `composer audit`, plus a separate Larastan job.

## Architecture

**Thin controllers → services own all writes.** Controllers validate, call a service in `app/Services`, then catch `BusinessException` (whose message is Vietnamese and user-facing) and turn it into a flash message. Never write to orders, payments, stock or tiers from a controller, view, or model event.

**Invariants live in the DB as well as in code** (see `docs/adr/001`):
- CHECK constraints are added with `App\Support\DbCheck`. Money equations are written additively, e.g. `total + discounts = subtotal + shipping_fee`, so they never underflow UNSIGNED columns.
- Generated STORED columns with UNIQUE enforce "at most one". Examples: `payments.success_charge_key` (at most one successful charge per order) and `user_addresses.default_key` (at most one default address).
- The DB connection forces `time_zone=+00:00`.
- The schema is considered frozen. Schema changes need the owner's approval.

**Concurrency** (`docs/adr/003`): the isolation level is READ COMMITTED, and correctness comes from explicit `lockForUpdate()` calls taken in a **fixed order**:
- Sales: `users → orders → plants (id ASC, via InventoryService::lockPlants) → coupons → payments`.
- Purchasing: `goods_receipts → purchase_requests → plants → purchase_request_items`.
- Every write flow runs inside `DB::transaction(fn, 3)`, which retries on deadlock.
- `MembershipService` locks `users` before `orders` and must not be called nested inside an order transaction. That is why `confirmRefund` calls `refreshUser` *after* its own transaction.

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
- To fix a stock mismatch, use a stock-count adjustment (`countTo`). Never `UPDATE plants.stock`.

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

**Notifications** (`docs/adr/007`):
- Always send through `App\Support\Notifier::send()`. It sends only after the DB commit (`DB::afterCommit`), goes through the database queue, and swallows/reports failures.
- Every money milestone has an email; add a new one as an `OrderNotification` subclass.

**Roles**:
- `users.role` is `customer|staff|admin`.
- Routes in `admin.*` use the `admin` middleware (staff + admin).
- Money and policy actions additionally use `admin:admin`: confirming bank transfers, refunds, approving or rejecting purchase requests, coupons, tiers, customers.
- Purchase requests follow a 4-eyes rule: the creator cannot approve their own request.
- Middleware priority runs `EnsureUserIsActive` → `AdminMiddleware` *before* route-model binding. As a result, unauthorized users get 403 whether or not the id exists (no id probing).
- Customer resources are owner-only via `OrderPolicy`, plus owner checks in controllers.

Business parameters (shipping fee, payment hold time, return window, bank info, trusted proxies) live in `config/shop.php`. Change policy there, not in code. Scheduled jobs and artisan commands (`shop:reconcile`, `shop:create-admin`, `reviews:rebuild`, `membership:recalculate-all`, …) are closures in `routes/console.php`.

## Rules the test suite enforces

These tests read the router and code automatically, so new code is checked without extra wiring:
- **No GET route may write to the DB** (`tests/Feature/Audit/AcceptanceCrawlTest`). It crawls every GET route × 4 roles and also asserts no 500s. A new GET route with route parameters needs a fixture added there and in `RoutePermissionMatrixTest`.
- **Every `admin.*` route** is permission-checked per role (`RoutePermissionMatrixTest`). A new money or policy route must use `admin:admin`.
- **Main pages ≤ 15 SQL queries**, and the count must not grow with data (`QueryBudgetTest`). `Model::preventLazyLoading()` is on outside production. Eager-load and paginate list pages.
- **Order state matrix** (`OrderStateMatrixTest`): every allowed action must leave `shop:reconcile` clean; every forbidden action must have no side effects.
- **Bug fixes need a regression test** that fails on the old code (`tests/Feature/Regression/`). **New flows that write stock or money need a race scenario** in `tests/Feature/Concurrency/ConcurrencyTest`. Races run as real PHP processes via `tests/Concurrency/worker.php`.
- Test helpers live in `tests/Concerns/BuildsOrders.php`: `customer()`, `admin()`, `place()`, `ipn()`, `stock()`, `assertLedgerConsistent()`. The base `TestCase` seeds.

## Docs

- `docs/adr/`: architecture decisions and why the alternatives were rejected. Read the relevant ADR before changing one of these areas.
- `docs/H5-ra-soat-luong.md`: the flow and permission audit.
- `docs/H7-van-hanh.md`: production runbook (env checklist, VNPay IPN URL, cron/worker, backup/restore, deploy/rollback, what to do when reconcile fails). Scripts are in `deploy/`.
- `docs/H8-nghiem-thu.md`: acceptance against criteria C1–C9, including what has **not** been verified yet (real VNPay sandbox, real servers).
