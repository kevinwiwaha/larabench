# Load Testing Guide: Using Vegeta

Complete guide for benchmarking Laravel + PostgreSQL vs MariaDB using Vegeta.

---

## 🎯 Why Vegeta?

Vegeta is a versatile HTTP load testing tool with:
- ✅ Simple text-based target configuration
- ✅ Constant request rate (not just max throughput)
- ✅ Detailed latency histograms
- ✅ JSON/text/HTML report outputs
- ✅ Real-time metrics
- ✅ Easy to script and automate

---

## 📦 Installation

### Linux
```bash
wget https://github.com/tsenart/vegeta/releases/download/v12.11.1/vegeta_12.11.1_linux_amd64.tar.gz
tar xzf vegeta_12.11.1_linux_amd64.tar.gz
sudo mv vegeta /usr/local/bin/
```

### macOS
```bash
brew install vegeta
```

### Windows
Download from: https://github.com/tsenart/vegeta/releases

### Verify Installation
```bash
vegeta --version
# Should show: Version: 12.11.1
```

---

## 🚀 Quick Start

### Step 1: Prepare Environment

```bash
# Create directories
mkdir -p vegeta_targets vegeta_results

# Setup PostgreSQL
cp .env.pgsql .env
php artisan config:clear
php artisan migrate:fresh --seed

# Start server
php artisan serve --host=0.0.0.0 --port=8000
```

### Step 2: Create Target Files

**File**: `vegeta_targets/post_order.txt`
```
POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":1,"product_id":1,"quantity":1}
```

### Step 3: Run Basic Test

```bash
# Attack at 100 requests/second for 30 seconds
vegeta attack -targets=vegeta_targets/post_order.txt -rate=100 -duration=30s \
    | vegeta report

# Or save results for later analysis
vegeta attack -targets=vegeta_targets/post_order.txt -rate=100 -duration=30s \
    | tee vegeta_results/results.bin \
    | vegeta report
```

---

## 📝 Target Files

### 1. Distributed Order Creation (Low Contention)

**File**: `vegeta_targets/orders_distributed.txt`
```
POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":1,"product_id":1,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":2,"product_id":2,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":3,"product_id":3,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":4,"product_id":4,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":5,"product_id":5,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":6,"product_id":6,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":7,"product_id":7,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":8,"product_id":8,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":9,"product_id":9,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":10,"product_id":10,"quantity":1}
```

### 2. Hot Product Test (High Contention)

**File**: `vegeta_targets/orders_hot.txt`
```
POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":1,"product_id":1,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":2,"product_id":1,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":3,"product_id":1,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":4,"product_id":1,"quantity":1}

POST http://localhost:8000/api/orders
Content-Type: application/json

{"user_id":5,"product_id":1,"quantity":1}
```

### 3. Product Listing (Read-Heavy)

**File**: `vegeta_targets/products_read.txt`
```
GET http://localhost:8000/api/products?sort=recent

GET http://localhost:8000/api/products?sort=price_asc

GET http://localhost:8000/api/products?sort=price_desc

GET http://localhost:8000/api/products?min_price=50&max_price=200

GET http://localhost:8000/api/products?per_page=50

GET http://localhost:8000/api/products?page=2

GET http://localhost:8000/api/products?search=product

GET http://localhost:8000/api/products
```

---

## 🧪 Test Scenarios

### Scenario 1: Baseline Performance Test

**Goal**: Measure maximum sustainable throughput with low contention

```bash
#!/bin/bash
# File: test_baseline.sh

echo "=== Baseline Performance Test ==="
echo "Testing: Distributed order creation"
echo "Duration: 60 seconds"
echo ""

# Test at different rates
for RATE in 50 100 150 200 250; do
    echo "Testing at ${RATE} req/s..."
    
    vegeta attack \
        -targets=vegeta_targets/orders_distributed.txt \
        -rate=${RATE} \
        -duration=60s \
        -timeout=30s \
        | tee vegeta_results/baseline_${RATE}rps.bin \
        | vegeta report -type=text
    
    echo ""
    sleep 10
done
```

### Scenario 2: Hot Product Stress Test

**Goal**: Test write contention on same rows

```bash
#!/bin/bash
# File: test_hot_product.sh

echo "=== Hot Product Stress Test ==="
echo "Testing: 5 hot products with high contention"
echo ""

for RATE in 50 100 150; do
    echo "Testing at ${RATE} req/s..."
    
    vegeta attack \
        -targets=vegeta_targets/orders_hot.txt \
        -rate=${RATE} \
        -duration=60s \
        -timeout=30s \
        | tee vegeta_results/hot_product_${RATE}rps.bin \
        | vegeta report -type=text
    
    echo ""
    sleep 10
done
```

### Scenario 3: Ramp-Up Test

**Goal**: Find the breaking point

```bash
#!/bin/bash
# File: test_ramp_up.sh

echo "=== Ramp-Up Test ==="
echo "Finding maximum sustainable rate"
echo ""

# Gradually increase rate
for RATE in 50 100 200 300 400 500 750 1000; do
    echo "Testing at ${RATE} req/s..."
    
    vegeta attack \
        -targets=vegeta_targets/orders_distributed.txt \
        -rate=${RATE} \
        -duration=30s \
        -timeout=30s \
        | vegeta report -type=text \
        | tee -a vegeta_results/ramp_up.log
    
    echo ""
    sleep 5
done
```

### Scenario 4: Read-Heavy Workload

**Goal**: Measure read performance

```bash
#!/bin/bash
# File: test_read_heavy.sh

echo "=== Read-Heavy Test ==="
echo "Testing: Product listing with various filters"
echo ""

vegeta attack \
    -targets=vegeta_targets/products_read.txt \
    -rate=500 \
    -duration=60s \
    -timeout=30s \
    | tee vegeta_results/read_heavy.bin \
    | vegeta report -type=text
```

---

## 📊 Complete Benchmark Script

**File**: `run_vegeta_benchmark.sh`

```bash
#!/bin/bash

set -e

echo "================================================"
echo "Laravel Benchmark: PostgreSQL vs MariaDB"
echo "Load Testing Tool: Vegeta"
echo "================================================"
echo ""

# Configuration
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RESULTS_DIR="vegeta_results/${TIMESTAMP}"
mkdir -p "${RESULTS_DIR}"

# Test parameters
RATE=100  # requests per second
DURATION=60s
PORT=8000

# Function to start server
start_server() {
    echo "Starting Laravel server..."
    php artisan serve --host=0.0.0.0 --port=${PORT} > /dev/null 2>&1 &
    SERVER_PID=$!
    sleep 5
    
    # Verify
    if curl -s http://localhost:${PORT} > /dev/null; then
        echo "✓ Server running (PID: ${SERVER_PID})"
    else
        echo "✗ Server failed to start"
        exit 1
    fi
}

# Function to stop server
stop_server() {
    if [ ! -z "$SERVER_PID" ]; then
        echo "Stopping server..."
        kill $SERVER_PID 2>/dev/null || true
        wait $SERVER_PID 2>/dev/null || true
        sleep 2
    fi
}

# Function to run test
run_test() {
    local db=$1
    local test_name=$2
    local targets=$3
    local rate=$4
    
    echo ""
    echo "----------------------------------------"
    echo "TEST: ${db} - ${test_name}"
    echo "Rate: ${rate} req/s, Duration: ${DURATION}"
    echo "----------------------------------------"
    
    local output="${RESULTS_DIR}/${db}_${test_name}"
    
    vegeta attack \
        -targets="${targets}" \
        -rate="${rate}" \
        -duration="${DURATION}" \
        -timeout=30s \
        | tee "${output}.bin" \
        | vegeta report -type=text \
        | tee "${output}.txt"
    
    # Generate additional reports
    vegeta report -type=json < "${output}.bin" > "${output}.json"
    vegeta plot -title="${db} - ${test_name}" < "${output}.bin" > "${output}.html"
    
    echo "✓ Results saved: ${output}.*"
}

# Function to collect stats
collect_stats() {
    local db=$1
    local test=$2
    local output="${RESULTS_DIR}/${db}_${test}_stats.txt"
    
    echo "Collecting database statistics..." | tee "${output}"
    php artisan tinker --execute="
        echo 'Database: ${db}' . PHP_EOL;
        echo 'Orders: ' . \App\Models\Order::count() . PHP_EOL;
        echo 'Products out of stock: ' . DB::table('products')->where('stock', 0)->count() . PHP_EOL;
        echo 'Min stock: ' . DB::table('products')->min('stock') . PHP_EOL;
        echo 'Avg stock: ' . round(DB::table('products')->avg('stock'), 2) . PHP_EOL;
    " | tee -a "${output}"
}

# Cleanup on exit
trap stop_server EXIT INT TERM

echo "Step 1: Testing PostgreSQL"
echo "================================================"

# PostgreSQL - Baseline
cp .env.pgsql .env
php artisan config:clear
php artisan migrate:fresh --seed --force
start_server
run_test "pgsql" "baseline" "vegeta_targets/orders_distributed.txt" "${RATE}"
stop_server
collect_stats "pgsql" "baseline"

# PostgreSQL - Hot Product
echo ""
php artisan migrate:fresh --seed --force
start_server
run_test "pgsql" "hot_product" "vegeta_targets/orders_hot.txt" "${RATE}"
stop_server
collect_stats "pgsql" "hot"

# PostgreSQL - Read
start_server
run_test "pgsql" "read" "vegeta_targets/products_read.txt" "500"
stop_server

echo ""
echo "Step 2: Testing MariaDB"
echo "================================================"

# MariaDB - Baseline
cp .env.mariadb .env
php artisan config:clear
php artisan migrate:fresh --seed --force
start_server
run_test "mariadb" "baseline" "vegeta_targets/orders_distributed.txt" "${RATE}"
stop_server
collect_stats "mariadb" "baseline"

# MariaDB - Hot Product
echo ""
php artisan migrate:fresh --seed --force
start_server
run_test "mariadb" "hot_product" "vegeta_targets/orders_hot.txt" "${RATE}"
stop_server
collect_stats "mariadb" "hot"

# MariaDB - Read
start_server
run_test "mariadb" "read" "vegeta_targets/products_read.txt" "500"
stop_server

echo ""
echo "================================================"
echo "Benchmark Complete!"
echo "================================================"
echo ""
echo "Results directory: ${RESULTS_DIR}"
echo ""
echo "View HTML reports:"
ls ${RESULTS_DIR}/*.html
echo ""
echo "Quick comparison:"
echo ""
echo "PostgreSQL Baseline:"
grep "Success" ${RESULTS_DIR}/pgsql_baseline.txt | head -5
echo ""
echo "MariaDB Baseline:"
grep "Success" ${RESULTS_DIR}/mariadb_baseline.txt | head -5
```

---

## 📈 Understanding Vegeta Output

### Text Report Format

```
Requests      [total, rate, throughput]  6000, 100.02, 98.45
Duration      [total, attack, wait]      60.95s, 59.98s, 970.23ms
Latencies     [min, mean, 50, 90, 95, 99, max]  5.123ms, 15.234ms, 12.456ms, 25.678ms, 34.567ms, 56.789ms, 234.567ms
Bytes In      [total, mean]              1234567, 205.76
Bytes Out     [total, mean]              654321, 109.05
Success       [ratio]                    98.50%
Status Codes  [code:count]               201:5910  500:90
Error Set:
500 Internal Server Error: Insufficient stock
```

**Key Metrics**:
- **Throughput**: Actual requests completed per second (vs requested rate)
- **Success Rate**: Percentage of successful requests
- **Latencies**: Response time distribution
  - **P50**: 50% of requests faster than this
  - **P95**: 95% of requests faster than this (important!)
  - **P99**: 99% of requests faster than this
- **Status Codes**: Breakdown of HTTP responses

### Success Rate Interpretation

- **>99%**: Excellent - system handling load well
- **95-99%**: Good - some stock depletion expected
- **90-95%**: Acceptable - high contention scenario
- **<90%**: Poor - system overloaded or issues

---

## 🎨 Generating Reports

### HTML Plot (Visual)

```bash
vegeta plot vegeta_results/pgsql_baseline.bin > report.html
xdg-open report.html  # Linux
open report.html      # macOS
```

### JSON Report (Programmatic)

```bash
vegeta report -type=json vegeta_results/pgsql_baseline.bin > report.json
```

### Compare Two Tests

```bash
# Generate comparison
vegeta report -type=text vegeta_results/pgsql_baseline.bin > pgsql.txt
vegeta report -type=text vegeta_results/mariadb_baseline.bin > mariadb.txt

# View side by side
diff -y pgsql.txt mariadb.txt
```

---

## 🔍 Advanced Usage

### Test with Different Payloads

```bash
# Generate dynamic targets
for i in {1..100}; do
    USER_ID=$((RANDOM % 1000 + 1))
    PRODUCT_ID=$((RANDOM % 2000 + 1))
    echo "POST http://localhost:8000/api/orders"
    echo "Content-Type: application/json"
    echo ""
    echo "{\"user_id\":${USER_ID},\"product_id\":${PRODUCT_ID},\"quantity\":1}"
    echo ""
done > vegeta_targets/orders_random.txt

# Run test
vegeta attack -targets=vegeta_targets/orders_random.txt -rate=100 -duration=60s | vegeta report
```

### Monitor in Real-Time

```bash
# Live progress
vegeta attack -targets=vegeta_targets/orders_distributed.txt -rate=100 -duration=300s \
    | vegeta encode \
    | vegeta report -every=5s -type=text

# Or with plot
vegeta attack -targets=vegeta_targets/orders_distributed.txt -rate=100 -duration=60s \
    | vegeta plot --title="Real-time" > /tmp/vegeta.html && open /tmp/vegeta.html
```

### Test Until Failure

```bash
#!/bin/bash
# Find breaking point

RATE=50
MAX_RATE=2000
STEP=50

while [ $RATE -le $MAX_RATE ]; do
    echo "Testing at ${RATE} req/s..."
    
    RESULT=$(vegeta attack -targets=vegeta_targets/orders_distributed.txt -rate=${RATE} -duration=30s | vegeta report -type=json)
    SUCCESS_RATE=$(echo $RESULT | jq -r '.success')
    
    if (( $(echo "$SUCCESS_RATE < 0.95" | bc -l) )); then
        echo "Breaking point found at ${RATE} req/s (Success: ${SUCCESS_RATE})"
        break
    fi
    
    RATE=$((RATE + STEP))
    sleep 5
done
```

---

## ✅ Pre-Flight Checklist

Before running benchmarks:

- [ ] Vegeta installed (`vegeta --version`)
- [ ] Target files created in `vegeta_targets/`
- [ ] Results directory created (`vegeta_results/`)
- [ ] Database seeded (`php artisan migrate:fresh --seed`)
- [ ] Server running (`php artisan serve`)
- [ ] Endpoint accessible (`curl http://localhost:8000`)
- [ ] `.env.pgsql` and `.env.mariadb` configured

---

## 🎯 Quick Commands

```bash
# Create directories
mkdir -p vegeta_targets vegeta_results

# Quick test
echo -e "GET http://localhost:8000/api/products" | vegeta attack -rate=50 -duration=10s | vegeta report

# Save results
vegeta attack -targets=targets.txt -rate=100 -duration=60s > results.bin

# View report
vegeta report < results.bin

# Generate HTML
vegeta plot < results.bin > plot.html

# Get JSON metrics
vegeta report -type=json < results.bin | jq .
```

---

## 📊 Expected Results

At 100 req/s for 60s:

**PostgreSQL**:
```
Success       [ratio]      98.5%
Latencies     [mean]       15ms
Throughput                 98 req/s
```

**MariaDB**:
```
Success       [ratio]      97.8%
Latencies     [mean]       18ms
Throughput                 96 req/s
```

Good luck with your load testing! 🚀

