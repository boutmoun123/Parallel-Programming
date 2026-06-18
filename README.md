# High-Performance E-Commerce Backend Engine

Laravel backend for an academic parallel programming project. The project focuses on a safe checkout path under concurrent users, Redis-backed coordination, background queues, batch processing, stress testing, benchmarking, and request-level performance monitoring.

## Architecture

- Framework: Laravel 13, PHP 8.3.
- API auth: Laravel Sanctum.
- Persistence: Laravel database migrations and Eloquent models.
- Queue backend for demo: Redis.
- Cache and distributed locks for demo: Redis.
- Critical modules:
  - `app/Modules/Orders`
  - `app/Modules/Products`
  - `app/Modules/Wallets`
  - `app/Modules/Infrastructure`
  - `app/Modules/DailySalesReports`
  - `app/Jobs`

## Demo Environment

Use Redis for the academic demo:

```env
CACHE_STORE=redis
DISTRIBUTED_LOCK_STORE=redis
CAPACITY_STORE=redis
QUEUE_CONNECTION=redis
```

Start Redis:

```bash
docker compose up -d redis
```

Run Laravel:

```bash
php artisan migrate:fresh --seed
php artisan serve
php artisan queue:work --tries=3
```

## Concurrency and Race Condition Handling

Checkout is protected by two layers:

- Redis distributed lock per order in `OrderCheckoutService::checkoutForUser`.
- Database transaction with pessimistic row locks in `OrderCheckoutService::checkoutInsideTransaction`.

The checkout transaction locks:

- the order row, preventing duplicate checkout of the same order
- all purchased products in stable product id order, preventing concurrent stock corruption
- the wallet row through `WalletService::chargeUserWallet`

The system checks `stock_quantity` and `quantity_counter` before decrementing. If stock or wallet balance is insufficient, the transaction fails before stock is reduced.

## Resource Management

`CapacityControlMiddleware` limits concurrent critical operations. The demo groups are configured in `config/capacity.php`:

- `critical-operations`
- `checkout`

When the system reaches the configured limit, the API returns `503` with capacity headers and `Retry-After`.

## Queues

Non-critical post-payment work is queued:

- `IssueOrderInvoiceJob`
- `SendPaymentSuccessNotificationJob`
- `GenerateDailySalesReportJob`

The jobs use `ShouldQueue`, `afterCommit()`, and `$tries = 3`, so they run after the database transaction commits and can retry on failure.

## Batch Processing

Daily sales reports are generated in chunks by `DailySalesReportBatchService::generateForDate`.

Run manually:

```bash
php artisan reports:generate-daily-sales 2026-06-17
php artisan queue:work --tries=3
```

The scheduler also dispatches the job daily at `23:55` from `routes/console.php`.

## Load Distribution

This project currently provides application-level load distribution simulation:

- `AssignServerNodeMiddleware` assigns each request to a server node.
- `LoadBalancerService` uses a least-loaded strategy with random tie-breaking.
- Server load changes are protected by database transactions and `lockForUpdate`.
- `RequestLogService` stores which simulated node handled the request.

Reason for selected strategy:

- It is deterministic enough to explain academically.
- It demonstrates how requests can be distributed across multiple logical nodes.
- It integrates with request timing and load logs.

Remaining limitation:

- `docker-compose.yml` currently starts Redis only. It does not yet run multiple Laravel app containers behind an actual Nginx upstream. For a production-style deployment proof, add `app1`, `app2`, and `nginx` services with an Nginx `upstream` block.

## Redis Caching

Product catalog reads use cache keys with TTL:

- `products:active:list`
- `products:details:{id}`
- `products:most-ordered`

The cache is invalidated when products are created, updated, deleted, or when checkout changes stock.

Wallet and daily report summaries also use cache with invalidation after mutations.

## Locking Strategy

The project uses pessimistic locking for sensitive updates:

- `ProductService::updateQuantityWithLock`
- `ProductService::reserveQuantityWithLock`
- `ProductService::decrementStockAfterPaymentWithLock`
- `OrderCheckoutService::checkoutInsideTransaction`
- `WalletService::lockedWalletForUser`

Redis locks are used for distributed coordination, while database row locks protect ACID updates.

## ACID Transactions

Checkout succeeds completely or fails completely. The transaction includes:

- idempotency check
- wallet charge
- stock decrement
- inventory movement creation
- order completion
- payment creation

Rollback proof is covered by tests for insufficient wallet balance and duplicate idempotency keys.

## Stress Testing

The k6 script ramps to at least 100 concurrent virtual users and includes product reads, order creation, order-item creation, checkout, reports, and benchmark result writes.

Run:

```bash
k6 run stress-test-100-all-operations.js
php artisan stress:validate-integrity
```

Expected integrity output:

```text
Post-stress data integrity validation
-------------------------------------
[PASS] product stock_quantity is never negative
[PASS] product quantity_counter is never negative
[PASS] no duplicate payments.idempotency_key (0 duplicate key groups)
[PASS] every completed order has a completed payment (0 completed orders without payment)
[PASS] inventory sale movements match stock decrements (0 invalid sale movements)
[PASS] no overselling or impossible available counter (0 products have quantity_counter > stock_quantity)
Integrity validation passed.
```

## Benchmarking

Run product cache benchmark:

```bash
php artisan benchmark:products-cache
```

Expected output example:

```text
Products cache benchmark
------------------------
Cold database read: 18 ms
Warm cache average: 2 ms
Benchmark result stored.
```

Run checkout benchmark:

```bash
php artisan benchmark:checkout
```

Expected output example:

```text
Checkout benchmark
------------------
Iterations: 5
Average response time: 35 ms
Max response time: 52 ms
Benchmark result stored.
```

Academic before/after comparison:

| Scenario | Before Optimization | After Optimization | Evidence |
|---|---:|---:|---|
| Product listing | Cold DB read measured by first `benchmark:products-cache` request | Warm Redis cache average measured over repeated requests | `benchmark_results` row with operation `products-cache` |
| Post-payment work | Invoice and notification would run in the checkout request | Jobs are dispatched after commit and processed by queue worker | `IssueOrderInvoiceJob`, `SendPaymentSuccessNotificationJob` |
| Checkout bottleneck | High contention can corrupt stock without locks | Redis lock plus DB row locks serialize checkout safely | `OrderCheckoutService`, `stress:validate-integrity` |

## AOP Performance Monitoring

Laravel middleware is used as the cross-cutting monitoring mechanism:

- `AssignServerNodeMiddleware` measures request time.
- `RequestLogService::createAutomaticRequestLog` stores endpoint, method, operation name, status code, and response time.
- API resources and controllers expose `response_time_ms` where relevant.

## Live Demo Steps

1. Start Redis:
   ```bash
   docker compose up -d redis
   ```
2. Prepare the database:
   ```bash
   php artisan migrate:fresh --seed
   ```
3. Run the API and queue worker:
   ```bash
   php artisan serve
   php artisan queue:work --tries=3
   ```
4. Demonstrate product cache:
   ```bash
   php artisan benchmark:products-cache
   ```
5. Demonstrate checkout benchmark:
   ```bash
   php artisan benchmark:checkout
   ```
6. Run stress test:
   ```bash
   k6 run stress-test-100-all-operations.js
   ```
7. Prove data integrity after stress:
   ```bash
   php artisan stress:validate-integrity
   ```
8. Run tests:
   ```bash
   php artisan test
   ```

## Important Security and Integrity Rule

Cart and order item requests accept only `product_id` and `quantity` for product data. The backend loads `product_name`, `unit_price`, and stock data from the `products` table. Client-supplied `product_name`, `unit_price`, and `subtotal` are prohibited by validation.
