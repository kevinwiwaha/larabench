# Load Testing Guide: PostgreSQL vs MariaDB

Complete step-by-step guide for benchmarking Laravel with PostgreSQL and MariaDB using the atomic stock decrement approach.

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [Database Preparation](#database-preparation)
4. [Load Testing Tools](#load-testing-tools)
5. [Test Scenarios](#test-scenarios)
6. [Running the Tests](#running-the-tests)
7. [Collecting Metrics](#collecting-metrics)
8. [Analyzing Results](#analyzing-results)
9. [Troubleshooting](#troubleshooting)

---

## 1. Prerequisites

### Required Software

- ✅ PHP 8.1 or higher
- ✅ Composer
- ✅ PostgreSQL 13+ OR MariaDB 10.6+
- ✅ Load testing tool: `wrk` OR `k6`
- ✅ Optional: `htop`, `iostat` for system monitoring

### Hardware Requirements

**Minimum**:
- 4 CPU cores
- 8GB RAM
- SSD storage

**Recommended (for accurate benchmarks)**:
- 8+ CPU cores
- 16GB RAM
- NVMe SSD

---

## 2. Environment Setup

### Step 2.1: Clone and Install

```bash
cd /path/to/larabench
composer install --no-dev --optimize-autoloader
```

### Step 2.2: Configure Environment Files

Create two environment files for testing both databases.

#### PostgreSQL Configuration

Create `.env.pgsql`:

```ini
APP_NAME="Laravel Benchmark PostgreSQL"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=larabench
DB_USERNAME=postgres
DB_PASSWORD=your-password

LOG_CHANNEL=stack
LOG_LEVEL=error
```

#### MariaDB Configuration

Create `.env.mariadb`:

```ini
APP_NAME="Laravel Benchmark MariaDB"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=larabench
DB_USERNAME=root
DB_PASSWORD=your-password

LOG_CHANNEL=stack
LOG_LEVEL=error
```

### Step 2.3: Verify Configuration

```bash
# Test PostgreSQL
cp .env.pgsql .env
php artisan config:clear
php artisan tinker
>>> DB::connection()->getPdo();  // Should connect successfully
>>> exit

# Test MariaDB
cp .env.mariadb .env
php artisan config:clear
php artisan tinker
>>> DB::connection()->getPdo();  // Should connect successfully
>>> exit
```

---

## 3. Database Preparation

### Step 3.1: PostgreSQL Setup

```bash
# Switch to PostgreSQL
cp .env.pgsql .env

# Create database
createdb larabench

# Run migrations and seed
php artisan migrate:fresh --seed

# Verify data
php artisan tinker
>>> User::count();       // Should be 1001
>>> Product::count();    // Should be 2000
>>> Order::count();      // Should be 1000
>>> exit
```

### Step 3.2: MariaDB Setup

```bash
# Switch to MariaDB
cp .env.mariadb .env

# Create database
mysql -u root -p -e "CREATE DATABASE larabench CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations and seed
php artisan migrate:fresh --seed

# Verify data
php artisan tinker
>>> User::count();       // Should be 1001
>>> Product::count();    // Should be 2000
>>> Order::count();      // Should be 1000
>>> exit
```

### Step 3.3: Verify Stock Levels

Important: Check initial stock for benchmark planning.

```bash
php artisan tinker
>>> DB::table('products')->selectRaw('MIN(stock) as min, MAX(stock) as max, AVG(stock) as avg')->first();
# Should show stock range (0 to 500)
>>> exit
```

---

## 4. Load Testing Tools

### Option A: wrk (Recommended for Raw Performance)

#### Installation

**Linux**:
```bash
sudo apt-get install wrk
```

**macOS**:
```bash
brew install wrk
```

**Windows**:
Use WSL2 or download pre-built binary from GitHub.

#### Create Test Scripts

**File**: `wrk_scripts/post_order.lua`

```lua
-- Basic load test script
wrk.method = "POST"
wrk.headers["Content-Type"] = "application/json"

local counter = 1

request = function()
    -- Rotate through users and products
    local user_id = (counter % 1000) + 1
    local product_id = (counter % 2000) + 1
    counter = counter + 1
    
    wrk.body = string.format(
        '{"user_id":%d,"product_id":%d,"quantity":1}',
        user_id,
        product_id
    )
    
    return wrk.format(nil, "/api/orders")
end
```

**File**: `wrk_scripts/post_order_hot.lua`

```lua
-- Hot product scenario (high contention)
wrk.method = "POST"
wrk.headers["Content-Type"] = "application/json"

-- Focus on 5 hot products
local hot_products = {1, 2, 3, 4, 5}
local counter = 1

request = function()
    local user_id = (counter % 1000) + 1
    local product_id = hot_products[(counter % 5) + 1]
    counter = counter + 1
    
    wrk.body = string.format(
        '{"user_id":%d,"product_id":%d,"quantity":1}',
        user_id,
        product_id
    )
    
    return wrk.format(nil, "/api/orders")
end
```

**File**: `wrk_scripts/get_products.lua`

```lua
-- Read-heavy test
wrk.method = "GET"

local counter = 1
local sorts = {"recent", "price_asc", "price_desc"}

request = function()
    local sort = sorts[(counter % 3) + 1]
    counter = counter + 1
    
    return wrk.format(nil, "/api/products?sort=" .. sort .. "&per_page=20")
end
```

---

### Option B: k6 (Recommended for Detailed Metrics)

#### Installation

```bash
# Linux/macOS
brew install k6

# Or download from https://k6.io/docs/getting-started/installation/
```

#### Create Test Scripts

**File**: `k6_scripts/load_test.js`

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');

export const options = {
    stages: [
        { duration: '30s', target: 20 },  // Ramp up to 20 users
        { duration: '2m', target: 20 },   // Stay at 20 users
        { duration: '30s', target: 50 },  // Ramp to 50 users
        { duration: '2m', target: 50 },   // Stay at 50 users
        { duration: '30s', target: 0 },   // Ramp down to 0
    ],
    thresholds: {
        'http_req_duration': ['p(95)<100'],  // 95% of requests under 100ms
        'errors': ['rate<0.1'],              // Error rate under 10%
    },
};

export default function () {
    const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
    
    // Random user and product
    const userId = Math.floor(Math.random() * 1000) + 1;
    const productId = Math.floor(Math.random() * 2000) + 1;
    
    const payload = JSON.stringify({
        user_id: userId,
        product_id: productId,
        quantity: 1,
    });
    
    const params = {
        headers: {
            'Content-Type': 'application/json',
        },
    };
    
    const response = http.post(`${BASE_URL}/api/orders`, payload, params);
    
    const success = check(response, {
        'status is 201': (r) => r.status === 201,
        'status is 200-299 or 500': (r) => r.status >= 200 && r.status < 300 || r.status === 500,
    });
    
    errorRate.add(!success);
    
    sleep(0.1); // Small delay between requests
}
```

**File**: `k6_scripts/spike_test.js`

```javascript
import http from 'k6/http';
import { check } from 'k6';

export const options = {
    stages: [
        { duration: '10s', target: 100 },  // Spike to 100 users
        { duration: '1m', target: 100 },   // Stay at 100
        { duration: '10s', target: 0 },    // Quick ramp down
    ],
};

export default function () {
    const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
    
    // Focus on hot products
    const hotProducts = [1, 2, 3, 4, 5];
    const userId = Math.floor(Math.random() * 1000) + 1;
    const productId = hotProducts[Math.floor(Math.random() * hotProducts.length)];
    
    const payload = JSON.stringify({
        user_id: userId,
        product_id: productId,
        quantity: 1,
    });
    
    const response = http.post(
        `${BASE_URL}/api/orders`,
        payload,
        { headers: { 'Content-Type': 'application/json' } }
    );
    
    check(response, {
        'status is 201 or 500': (r) => r.status === 201 || r.status === 500,
    });
}
```

---

## 5. Test Scenarios

### Scenario 1: Baseline Performance (Low Contention)

**Goal**: Measure maximum throughput with distributed load

**Configuration**:
- 40 concurrent connections
- Random products (low collision rate)
- 60 second duration

**Expected**: Highest throughput, minimal stock conflicts

---

### Scenario 2: Hot Product Stress (High Contention)

**Goal**: Test concurrent UPDATE performance on same rows

**Configuration**:
- 40 concurrent connections
- 5 hot products (high collision rate)
- 60 second duration

**Expected**: Lower throughput, stock conflicts, tests atomic UPDATE

---

### Scenario 3: Spike Test (Sudden Load)

**Goal**: Test database behavior under sudden traffic spike

**Configuration**:
- Ramp from 0 to 100 connections in 10 seconds
- Stay at 100 for 1 minute
- Ramp down

**Expected**: Tests connection pooling, lock queue handling

---

### Scenario 4: Read-Heavy Mixed Workload

**Goal**: Measure read performance impact

**Configuration**:
- 50% GET /api/products
- 50% POST /api/orders
- 40 concurrent connections

**Expected**: Balance between reads and writes

---

## 6. Running the Tests

### Pre-Test Checklist

Before each test:

```bash
# 1. Stop unnecessary services
sudo systemctl stop apache2  # If running
sudo systemctl stop nginx    # If running

# 2. Clear Laravel caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache

# 3. Reset database (if needed)
php artisan migrate:fresh --seed

# 4. Start application server
php artisan serve --host=0.0.0.0 --port=8000 &

# 5. Verify server is running
curl http://localhost:8000/api/products

# 6. Note the PID for later
ps aux | grep "php artisan serve"
```

---

### Test Execution

#### PostgreSQL Tests

```bash
# ============================================================
# TEST 1: PostgreSQL - Baseline Performance
# ============================================================

echo "Starting PostgreSQL Baseline Test..."
cp .env.pgsql .env
php artisan config:clear
php artisan migrate:fresh --seed

# Start server
php artisan serve --host=0.0.0.0 --port=8000 &
SERVER_PID=$!
sleep 5

# Run test
wrk -t4 -c40 -d60s -s wrk_scripts/post_order.lua http://localhost:8000/api/orders \
    | tee results/pgsql_baseline_$(date +%Y%m%d_%H%M%S).txt

# Stop server
kill $SERVER_PID
sleep 2

# ============================================================
# TEST 2: PostgreSQL - Hot Product Stress
# ============================================================

echo "Starting PostgreSQL Hot Product Test..."
php artisan migrate:fresh --seed

php artisan serve --host=0.0.0.0 --port=8000 &
SERVER_PID=$!
sleep 5

wrk -t4 -c40 -d60s -s wrk_scripts/post_order_hot.lua http://localhost:8000/api/orders \
    | tee results/pgsql_hot_$(date +%Y%m%d_%H%M%S).txt

kill $SERVER_PID
sleep 2

# ============================================================
# TEST 3: PostgreSQL - Read Performance
# ============================================================

echo "Starting PostgreSQL Read Test..."
php artisan serve --host=0.0.0.0 --port=8000 &
SERVER_PID=$!
sleep 5

wrk -t4 -c40 -d60s -s wrk_scripts/get_products.lua http://localhost:8000/api/products \
    | tee results/pgsql_read_$(date +%Y%m%d_%H%M%S).txt

kill $SERVER_PID
```

#### MariaDB Tests

```bash
# ============================================================
# TEST 1: MariaDB - Baseline Performance
# ============================================================

echo "Starting MariaDB Baseline Test..."
cp .env.mariadb .env
php artisan config:clear
php artisan migrate:fresh --seed

php artisan serve --host=0.0.0.0 --port=8000 &
SERVER_PID=$!
sleep 5

wrk -t4 -c40 -d60s -s wrk_scripts/post_order.lua http://localhost:8000/api/orders \
    | tee results/mariadb_baseline_$(date +%Y%m%d_%H%M%S).txt

kill $SERVER_PID
sleep 2

# ============================================================
# TEST 2: MariaDB - Hot Product Stress
# ============================================================

echo "Starting MariaDB Hot Product Test..."
php artisan migrate:fresh --seed

php artisan serve --host=0.0.0.0 --port=8000 &
SERVER_PID=$!
sleep 5

wrk -t4 -c40 -d60s -s wrk_scripts/post_order_hot.lua http://localhost:8000/api/orders \
    | tee results/mariadb_hot_$(date +%Y%m%d_%H%M%S).txt

kill $SERVER_PID
sleep 2

# ============================================================
# TEST 3: MariaDB - Read Performance
# ============================================================

echo "Starting MariaDB Read Test..."
php artisan serve --host=0.0.0.0 --port=8000 &
SERVER_PID=$!
sleep 5

wrk -t4 -c40 -d60s -s wrk_scripts/get_products.lua http://localhost:8000/api/products \
    | tee results/mariadb_read_$(date +%Y%m%d_%H%M%S).txt

kill $SERVER_PID
```

---

### Using k6

```bash
# PostgreSQL test
cp .env.pgsql .env
php artisan config:clear
php artisan migrate:fresh --seed
php artisan serve &
sleep 5

k6 run --env BASE_URL=http://localhost:8000 k6_scripts/load_test.js \
    | tee results/pgsql_k6_$(date +%Y%m%d_%H%M%S).txt

killall php

# MariaDB test
cp .env.mariadb .env
php artisan config:clear
php artisan migrate:fresh --seed
php artisan serve &
sleep 5

k6 run --env BASE_URL=http://localhost:8000 k6_scripts/load_test.js \
    | tee results/mariadb_k6_$(date +%Y%m%d_%H%M%S).txt

killall php
```

---

## 7. Collecting Metrics

### Application Metrics

After each test, collect these metrics:

```bash
# Check final order count
php artisan tinker
>>> Order::count();

# Check stock depletion
>>> DB::table('products')->where('stock', 0)->count();

# Check minimum stock
>>> DB::table('products')->min('stock');

# Sample some orders
>>> Order::latest()->take(10)->get(['id', 'product_id', 'quantity', 'created_at']);

>>> exit
```

### Database Metrics

#### PostgreSQL

```sql
-- Connection stats
SELECT count(*) as active_connections 
FROM pg_stat_activity 
WHERE state = 'active';

-- Transaction stats
SELECT * FROM pg_stat_database WHERE datname = 'larabench';

-- Table stats
SELECT * FROM pg_stat_user_tables WHERE relname IN ('products', 'orders');

-- Lock stats
SELECT * FROM pg_locks WHERE granted = false;
```

#### MariaDB

```sql
-- Connection stats
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Max_used_connections';

-- InnoDB stats
SHOW ENGINE INNODB STATUS;

-- Table stats
SELECT * FROM information_schema.innodb_trx;
SELECT * FROM information_schema.innodb_locks;
```

### System Metrics

Monitor during tests:

```bash
# CPU and Memory
htop

# Disk I/O
iostat -x 1

# Network
netstat -an | grep :8000 | wc -l  # Active connections
```

---

## 8. Analyzing Results

### wrk Output Interpretation

Example output:

```
Running 60s test @ http://localhost:8000/api/orders
  4 threads and 40 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency    15.23ms    8.45ms  89.12ms   75.32%
    Req/Sec   654.23     89.12     892.00     68.45%
  156,234 requests in 60.00s, 45.67MB read
  Socket errors: connect 0, read 0, write 0, timeout 0
  Non-2xx or 3xx responses: 234
Requests/sec:   2,603.90
Transfer/sec:    780.45KB
```

**Key Metrics**:
- **Requests/sec**: 2,603.90 (throughput)
- **Avg Latency**: 15.23ms
- **P95 Latency**: ~50ms (estimated from Stdev)
- **Errors**: 234 requests (likely stock depleted)
- **Success Rate**: 99.85%

### Comparison Template

Create `results/comparison.md`:

```markdown
# Benchmark Results Comparison

## Test Environment
- Date: 2024-01-20
- Hardware: 8 cores, 16GB RAM, NVMe SSD
- PHP: 8.2
- Laravel: 10.x
- PostgreSQL: 15.x
- MariaDB: 10.11

## Baseline Performance (60s, 40 connections, random products)

| Metric | PostgreSQL | MariaDB | Winner |
|--------|-----------|---------|--------|
| Requests/sec | 2,603 | 2,489 | PostgreSQL (+4.6%) |
| Avg Latency | 15.23ms | 16.08ms | PostgreSQL |
| P95 Latency | ~50ms | ~58ms | PostgreSQL |
| Errors | 234 | 267 | PostgreSQL |
| Success Rate | 99.85% | 99.83% | Tie |

## Hot Product Stress (60s, 40 connections, 5 products)

| Metric | PostgreSQL | MariaDB | Winner |
|--------|-----------|---------|--------|
| Requests/sec | 1,845 | 1,623 | PostgreSQL (+13.7%) |
| Avg Latency | 21.67ms | 24.65ms | PostgreSQL |
| Stock Conflicts | 12% | 18% | PostgreSQL |

## Read Performance (60s, 40 connections)

| Metric | PostgreSQL | MariaDB | Winner |
|--------|-----------|---------|--------|
| Requests/sec | 5,234 | 4,987 | PostgreSQL (+5.0%) |
| Avg Latency | 7.63ms | 8.02ms | PostgreSQL |

## Key Findings

1. **Overall Winner**: PostgreSQL showed consistently better performance
2. **Concurrency**: PostgreSQL's MVCC handled contention better
3. **Stock Updates**: Atomic UPDATE performed well on both
4. **Read Performance**: Both databases handled reads efficiently

## Recommendations

- For this workload: PostgreSQL recommended
- MariaDB acceptable if <2,000 req/sec needed
- Atomic stock approach works well on both databases
```

---

## 9. Troubleshooting

### Issue: Connection Refused

```bash
# Check if server is running
ps aux | grep "php artisan serve"

# Check port availability
lsof -i :8000

# Start server manually
php artisan serve --host=0.0.0.0 --port=8000
```

### Issue: High Error Rate (>10%)

**Possible causes**:
1. Stock depleted (expected)
2. Database connection pool exhausted
3. Validation failures

**Debug**:

```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check database connections
# PostgreSQL:
psql -U postgres -c "SELECT count(*) FROM pg_stat_activity;"

# MariaDB:
mysql -u root -p -e "SHOW PROCESSLIST;"
```

### Issue: Slow Performance

```bash
# Check system load
uptime

# Check database CPU
top -p $(pgrep postgres)  # or mysqld

# Check query performance
php artisan tinker
>>> DB::enableQueryLog();
>>> // Run a request
>>> DB::getQueryLog();
```

### Issue: Stock Goes Negative

```bash
# This should NEVER happen with atomic approach
php artisan tinker
>>> DB::table('products')->where('stock', '<', 0)->count();
# Should be 0

# If it happens, check transaction isolation
>>> DB::select('SHOW transaction_isolation');  # PostgreSQL
>>> DB::select('SELECT @@transaction_isolation');  # MariaDB
```

---

## 🎯 Quick Start Script

Save as `run_benchmark.sh`:

```bash
#!/bin/bash

# Quick benchmark script
mkdir -p results wrk_scripts k6_scripts

echo "=== Laravel Benchmark: PostgreSQL vs MariaDB ==="
echo ""

# PostgreSQL
echo "Testing PostgreSQL..."
cp .env.pgsql .env
php artisan config:clear
php artisan migrate:fresh --seed --force
php artisan serve --host=0.0.0.0 --port=8000 &
SERVER_PID=$!
sleep 5

wrk -t4 -c40 -d60s -s wrk_scripts/post_order.lua http://localhost:8000/api/orders \
    | tee results/pgsql_result.txt

kill $SERVER_PID
sleep 5

# MariaDB
echo "Testing MariaDB..."
cp .env.mariadb .env
php artisan config:clear
php artisan migrate:fresh --seed --force
php artisan serve --host=0.0.0.0 --port=8000 &
SERVER_PID=$!
sleep 5

wrk -t4 -c40 -d60s -s wrk_scripts/post_order.lua http://localhost:8000/api/orders \
    | tee results/mariadb_result.txt

kill $SERVER_PID

echo ""
echo "=== Benchmark Complete ==="
echo "Results saved in results/ directory"
```

Run it:

```bash
chmod +x run_benchmark.sh
./run_benchmark.sh
```

---

## 📊 Expected Timeline

- **Setup**: 30 minutes
- **Per test run**: 2-5 minutes
- **Complete benchmark suite**: 1-2 hours
- **Analysis**: 30 minutes
- **Total**: ~3 hours

---

## ✅ Checklist

Before starting:

- [ ] Both databases installed and running
- [ ] Load testing tool installed (wrk or k6)
- [ ] Environment files configured
- [ ] Test scripts created
- [ ] Results directory created
- [ ] System monitoring tools ready

Good luck with your benchmarks! 🚀

