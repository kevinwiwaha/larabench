# Laravel Database Benchmark Implementation

## ✅ Implementation Complete

All components from the benchmark guide have been successfully implemented.

## 📁 Files Created

### Migrations (3 files)
- ✅ `database/migrations/2024_01_01_000001_create_products_table.php`
- ✅ `database/migrations/2024_01_01_000002_create_orders_table.php`
- ✅ `database/migrations/2024_01_01_000003_add_created_at_index_to_users_table.php`

### Models (3 files)
- ✅ `app/Models/Product.php` (new)
- ✅ `app/Models/Order.php` (new)
- ✅ `app/Models/User.php` (updated with orders relationship)

### Seeders (4 files)
- ✅ `database/seeders/UserSeeder.php` (1,000 users)
- ✅ `database/seeders/ProductSeeder.php` (2,000 products)
- ✅ `database/seeders/OrderSeeder.php` (1,000 orders)
- ✅ `database/seeders/DatabaseSeeder.php` (updated)

### Controllers (2 files)
- ✅ `app/Http/Controllers/ProductController.php` (READ-heavy)
- ✅ `app/Http/Controllers/OrderController.php` (WRITE-heavy)

### Routes
- ✅ `routes/api.php` (API endpoints)
- ✅ `bootstrap/app.php` (updated to register API routes)

## 🚀 Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

**Using preset files (recommended)**:

For **PostgreSQL**:
```bash
cp .env.pgsql .env
php artisan key:generate
```

For **MariaDB**:
```bash
cp .env.mariadb .env
php artisan key:generate
```

> 📝 See `ENV_CONFIG_REFERENCE.md` for complete `.env` configuration examples and troubleshooting.

### 3. Run Migrations and Seeders

```bash
# Run migrations and seed data
php artisan migrate:fresh --seed
```

Expected output:
- Created 1,000 users
- Created 2,000 products
- Created 1,000 orders
- **Total: 4,000 rows**

### 4. Start the Server

```bash
php artisan serve
```

Then visit http://localhost:8000 to see:
- Current database driver (PostgreSQL or MariaDB)
- Database statistics (users, products, orders, stock)
- Available API endpoints

Or use Laravel Sail, Valet, or configure with Nginx/Apache.

### 5. Test the Endpoints

**READ-heavy endpoint:**
```bash
# Basic listing
curl http://localhost:8000/api/products

# With price sorting
curl http://localhost:8000/api/products?sort=price_asc

# With filters
curl "http://localhost:8000/api/products?min_price=50&max_price=200"

# With search
curl "http://localhost:8000/api/products?search=product"
```

**WRITE-heavy endpoint:**
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"product_id":1,"quantity":2}'
```

## 📊 Database Schema

### Users Table
- id (bigint, primary key)
- name (varchar)
- email (varchar, unique, indexed)
- password (varchar)
- created_at (timestamp, **indexed**)
- updated_at (timestamp)

### Products Table
- id (bigint, primary key)
- sku (varchar, unique, indexed)
- name (varchar, **indexed**)
- description (text, nullable)
- price (decimal, **indexed**)
- stock (integer)
- created_at (timestamp)
- updated_at (timestamp)
- **Composite index**: (price, created_at)

### Orders Table
- id (bigint, primary key)
- user_id (bigint, foreign key, **indexed**)
- product_id (bigint, foreign key, **indexed**)
- quantity (integer)
- unit_price (decimal)
- total_price (decimal)
- status (varchar, **indexed**)
- created_at (timestamp, **indexed**)
- updated_at (timestamp)
- **Composite indexes**: 
  - (user_id, created_at)
  - (status, created_at)

## 🎯 Performance Features

### Deterministic Seeding
Each seeder uses a fixed Faker seed:
- UserSeeder: `seed(12345)`
- ProductSeeder: `seed(54321)`
- OrderSeeder: `seed(99999)`

This ensures **identical data** on both PostgreSQL and MariaDB for fair comparison.

### Bulk Inserts
All seeders use bulk inserts (batch size: 500) instead of individual `create()` calls:
- **~50x faster** than row-by-row inserts
- Reduces database round trips
- Minimizes index update overhead

### Strategic Indexes
- Single-column indexes on frequently filtered/sorted columns
- Composite indexes on common query patterns
- Foreign keys automatically indexed
- All indexes compatible with both PostgreSQL and MariaDB

### Query Optimization
- Price range queries use indexed columns
- Sorting uses indexed columns (price, created_at)
- Pagination uses efficient LIMIT/OFFSET
- No N+1 queries in seeders (pre-cached prices)

## 🔧 Production Optimization

Before benchmarking, run:

```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear any query logs
php artisan cache:clear
```

## 📈 Benchmark with Vegeta

This project uses **Vegeta** for load testing. All scripts and targets are pre-configured.

### Quick Start

```bash
# Run complete benchmark (PostgreSQL + MariaDB)
bash run_vegeta_benchmark.sh
```

### Manual Testing

**Read-heavy test**:
```bash
vegeta attack -targets=vegeta_targets/products_read.txt -rate=100 -duration=60s | vegeta report
```

**Write-heavy test (distributed)**:
```bash
vegeta attack -targets=vegeta_targets/orders_distributed.txt -rate=100 -duration=60s | vegeta report
```

**Write-heavy test (hot products)**:
```bash
vegeta attack -targets=vegeta_targets/orders_hot.txt -rate=100 -duration=60s | vegeta report
```

### View Results

```bash
# Generate HTML plot
vegeta plot vegeta_results/20240120_143022/pgsql_baseline.bin > report.html

# View JSON metrics
vegeta report -type=json < vegeta_results/20240120_143022/pgsql_baseline.bin
```

See `LOAD_TEST_VEGETA.md` for complete guide.

---

## 🖥️ VM Deployment

For production-like benchmarking on separate VMs (2 CPU, 2GB RAM each):

1. **Setup Guide**: See `VM_SETUP_GUIDE.md` for complete VM setup instructions

**VM Architecture** (3 VMs):
- **VM A** (192.168.1.10): Laravel + PostgreSQL 15 + Nginx + Node Exporter + Cloudflare Tunnel (idle)
- **VM B** (192.168.1.11): Laravel + MariaDB 10.11 + Nginx + Node Exporter + Cloudflare Tunnel (idle)
- **VM C** (192.168.1.12): Prometheus + Grafana (monitoring)
- **Load Testing**: Vegeta (from any machine)

**Included in VM setup**:
- Complete Nginx configuration (reverse proxy)
- Node Exporter for Prometheus monitoring (port 9100)
- Cloudflare Tunnel installed (idle mode, for resource estimation)
- All configurations optimized for 2GB RAM
- Performance tuning and troubleshooting

Complete step-by-step instructions in `VM_SETUP_GUIDE.md`.

## 🔍 Verification

Check that data was seeded correctly:

```bash
php artisan tinker
```

Then run:
```php
User::count();      // Should return 1001 (1000 + benchmark user)
Product::count();   // Should return 2000
Order::count();     // Should return 1000

// Verify relationships work
User::first()->orders->count();
Product::first()->orders->count();
Order::first()->user->name;
Order::first()->product->name;

// Verify indexes exist
DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'products';"); // PostgreSQL
DB::select("SHOW INDEX FROM products;"); // MariaDB
```

## 🎓 Next Steps

1. **Deploy to both VMs** (PostgreSQL and MariaDB)
2. **Configure identical .env** files (except DB connection)
3. **Run migrations and seeders** on both
4. **Verify row counts** match on both databases
5. **Run warm-up queries** before benchmarking
6. **Execute benchmarks** multiple times
7. **Collect metrics** and compare results

## 📝 Notes

- All code is **cross-compatible** with PostgreSQL and MariaDB
- No vendor-specific SQL or features used
- Uses standard Laravel Eloquent and Query Builder
- Deterministic data ensures fair comparison
- Performance-optimized seeders and queries
- Production-ready configuration included

## 🐛 Troubleshooting

**Issue**: Foreign key constraint errors during seeding  
**Solution**: Ensure seeders run in order: Users → Products → Orders

**Issue**: Slow seeding  
**Solution**: Bulk inserts are already implemented. Check database connection and hardware.

**Issue**: Different row counts on PostgreSQL vs MariaDB  
**Solution**: Re-run `php artisan migrate:fresh --seed` on both to ensure clean state.

**Issue**: API routes not found (404)  
**Solution**: Verify `bootstrap/app.php` includes `api: __DIR__.'/../routes/api.php'`

For more details, see `.cursor/guide.md`.

