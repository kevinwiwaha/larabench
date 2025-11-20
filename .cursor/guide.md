# Laravel Database Benchmark: PostgreSQL vs MariaDB

**Purpose**: Compare Laravel + PostgreSQL vs Laravel + MariaDB performance using identical code, schema, and dataset.

**Approach**: Single Laravel 10+ codebase deployed on two separate VMs with different database backends.

## Table of Contents

1. [Domain Model](#1-domain-model)
2. [Migrations](#2-migrations)
3. [Models](#3-models)
4. [Seeders](#4-seeders)
5. [Controllers & Routes](#5-controllers--routes)
6. [Configuration](#6-configuration)
7. [Benchmark Execution](#7-benchmark-execution)
8. [Performance Considerations](#8-performance-considerations)

---

## 1. Domain Model

### Tables

- **users**: Customer accounts (1,000 rows)
- **products**: Product catalog (2,000 rows)
- **orders**: Purchase orders (1,000 rows)

**Total**: ~4,000 rows (under 5k requirement)

### Endpoints

- **Read-heavy**: `GET /api/products` - Paginated product listing with filters
- **Write-heavy**: `POST /api/orders` - Single order creation

---

## 2. Migrations

All migrations use standard Laravel schema builder methods that are compatible with both PostgreSQL and MariaDB. No vendor-specific SQL.

### 2.1 Users Table

**File**: `database/migrations/2024_01_01_000001_create_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->timestamps();

            // Performance: Index on created_at for time-based queries
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

**Performance notes**:
- `created_at` index: Supports efficient date-range queries
- `email` unique constraint: Creates implicit index for lookups

---

### 2.2 Products Table

**File**: `database/migrations/2024_01_01_000002_create_products_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku', 64)->unique();
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->index();
            $table->integer('stock')->default(0);
            $table->timestamps();

            // Performance: Composite index for common query pattern
            $table->index(['price', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

**Performance notes**:
- `name` index: Supports LIKE queries (though left-anchored only)
- `price` index: Critical for price-based sorting and filtering
- `price, created_at` composite: Optimizes "recent products in price range" queries
- `sku` unique constraint: Implicit index for SKU lookups

---

### 2.3 Orders Table

**File**: `database/migrations/2024_01_01_000003_create_orders_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->string('status', 32)->default('paid')->index();
            $table->timestamps();

            // Performance: Indexes for common access patterns
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

**Performance notes**:
- Foreign key constraints: Create implicit indexes on `user_id` and `product_id`
- `status` index: Fast filtering by order status
- `user_id, created_at` composite: "User's recent orders" queries
- `status, created_at` composite: "Recent paid orders" analytics

---

## 3. Models

### 3.1 User Model

**File**: `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
```

---

### 3.2 Product Model

**File**: `app/Models/Product.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
```

---

### 3.3 Order Model

**File**: `app/Models/Order.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

---

## 4. Seeders

**Critical for benchmarking**: Use deterministic data by setting Faker seed. This ensures both databases get comparable datasets.

### 4.1 User Seeder

**File**: `database/seeders/UserSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // IMPORTANT: Set seed for deterministic data
        $faker = Faker::create();
        $faker->seed(12345);

        // Pre-hash password once to avoid repeated hashing overhead
        $hashedPassword = Hash::make('password');

        // Known user for testing
        User::updateOrCreate(
            ['email' => 'benchmark@example.com'],
            [
                'name' => 'Benchmark User',
                'password' => $hashedPassword,
            ]
        );

        // Bulk insert for performance
        $users = [];
        $batchSize = 500;
        $target = 1000;

        for ($i = 0; $i < $target; $i++) {
            $users[] = [
                'name'       => $faker->name(),
                'email'      => $faker->unique()->safeEmail(),
                'password'   => $hashedPassword,
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                'updated_at' => now(),
            ];

            // Insert in batches to avoid memory issues
            if (count($users) >= $batchSize) {
                User::insert($users);
                $users = [];
            }
        }

        if (!empty($users)) {
            User::insert($users);
        }

        $this->command->info('Created 1,000 users');
    }
}
```

**Performance notes**:
- Deterministic faker seed ensures identical data across both databases
- Pre-hashed password prevents 1,000 bcrypt operations
- Bulk inserts are ~50x faster than individual `create()` calls

---

### 4.2 Product Seeder

**File**: `database/seeders/ProductSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // IMPORTANT: Set seed for deterministic data
        $faker = Faker::create();
        $faker->seed(54321);

        $products = [];
        $batchSize = 500;
        $target = 2000;

        for ($i = 0; $i < $target; $i++) {
            $products[] = [
                'sku'         => 'SKU-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                'name'        => $faker->sentence(3),
                'description' => $faker->paragraph(),
                'price'       => $faker->randomFloat(2, 5, 500),
                'stock'       => $faker->numberBetween(0, 500),
                'created_at'  => $faker->dateTimeBetween('-6 months', 'now'),
                'updated_at'  => now(),
            ];

            if (count($products) >= $batchSize) {
                Product::insert($products);
                $products = [];
            }
        }

        if (!empty($products)) {
            Product::insert($products);
        }

        $this->command->info('Created 2,000 products');
    }
}
```

**Performance notes**:
- Deterministic SKU generation ensures uniqueness
- Bulk inserts significantly faster than individual creates
- Price range (5-500) provides good distribution for filtering tests

---

### 4.3 Order Seeder

**File**: `database/seeders/OrderSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // IMPORTANT: Set seed for deterministic data
        $faker = Faker::create();
        $faker->seed(99999);

        // Fetch all IDs efficiently
        $userIds = User::pluck('id')->all();
        $productIds = Product::pluck('id')->all();

        if (empty($userIds) || empty($productIds)) {
            $this->command->warn('Users or Products not seeded. Skipping orders.');
            return;
        }

        // Cache product prices to avoid N queries
        $productPrices = Product::pluck('price', 'id')->all();

        $orders = [];
        $batchSize = 500;
        $target = 1000;

        for ($i = 0; $i < $target; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $productId = $productIds[array_rand($productIds)];
            $quantity = $faker->numberBetween(1, 5);
            $unitPrice = $productPrices[$productId];
            $totalPrice = $unitPrice * $quantity;

            $orders[] = [
                'user_id'     => $userId,
                'product_id'  => $productId,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'total_price' => $totalPrice,
                'status'      => 'paid',
                'created_at'  => $faker->dateTimeBetween('-3 months', 'now'),
                'updated_at'  => now(),
            ];

            if (count($orders) >= $batchSize) {
                Order::insert($orders);
                $orders = [];
            }
        }

        if (!empty($orders)) {
            Order::insert($orders);
        }

        $this->command->info('Created 1,000 orders');
    }
}
```

**Performance notes**:
- Pre-loads product prices to avoid N+1 queries
- Uses `array_rand()` instead of Faker's `randomElement()` for speed
- Bulk inserts maintain foreign key integrity

---

### 4.4 Database Seeder

**File**: `database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed in dependency order
        $this->call([
            UserSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
```

---

## 5. Controllers & Routes

### 5.1 Routes

**File**: `routes/api.php`

```php
<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Benchmark endpoints
Route::get('/products', [ProductController::class, 'index']);   // READ-heavy
Route::post('/orders', [OrderController::class, 'store']);      // WRITE-heavy
```

---

### 5.2 Product Controller (READ-heavy)

**File**: `app/Http/Controllers/ProductController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * 
     * Read-heavy endpoint for benchmarking SELECT performance.
     * Supports filtering and sorting to simulate realistic queries.
     * 
     * Query params:
     * - search: string (filters by name using LIKE)
     * - min_price: numeric
     * - max_price: numeric
     * - sort: recent|price_asc|price_desc
     * - per_page: int (default 20)
     */
    public function index(Request $request)
    {
        $query = Product::query();

        // Filter by name (performance note: LIKE with leading wildcard can't use index)
        if ($search = $request->query('search')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        // Price range filtering (uses price index)
        if ($minPrice = $request->query('min_price')) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice = $request->query('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

        // Sorting (price and created_at are indexed)
        $sort = $request->query('sort', 'recent');

        switch ($sort) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        // Pagination (adjust per_page to simulate different load patterns)
        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 100); // Clamp between 1-100

        return $query->paginate($perPage);
    }
}
```

**Performance notes**:
- Filters use indexed columns where possible
- LIKE queries with `%search%` can't use indexes efficiently
- Pagination uses `LIMIT/OFFSET` - both DBs handle this slightly differently
- No eager loading needed (no relations loaded)

---

### 5.3 Order Controller (WRITE-heavy)

**File**: `app/Http/Controllers/OrderController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * POST /api/orders
     * 
     * Write-heavy endpoint for benchmarking INSERT performance.
     * Creates a single order record.
     * 
     * Request body:
     * {
     *   "user_id": 1,
     *   "product_id": 1,
     *   "quantity": 2
     * }
     */
    public function store(Request $request)
    {
        // Validation adds overhead but is realistic
        $validated = $request->validate([
            'user_id'    => 'required|integer|exists:users,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'required|integer|min:1|max:100',
        ]);

        // Use transaction to ensure atomicity
        // Both PostgreSQL and MariaDB handle transactions
        $order = DB::transaction(function () use ($validated) {
            // Fetch user and product (adds SELECT queries)
            $user = User::findOrFail($validated['user_id']);
            $product = Product::findOrFail($validated['product_id']);
            
            $quantity = $validated['quantity'];
            $unitPrice = $product->price;
            $totalPrice = $unitPrice * $quantity;

            // Single INSERT into orders table
            // This is the primary write operation being benchmarked
            return Order::create([
                'user_id'     => $user->id,
                'product_id'  => $product->id,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'total_price' => $totalPrice,
                'status'      => 'paid',
            ]);
        });

        return response()->json($order, 201);
    }
}
```

**Performance notes**:
- Validation hits DB twice (`exists:users,id` and `exists:products,id`)
- Transaction overhead is minimal for single insert
- `findOrFail()` adds two SELECT queries
- Total queries per request: 2 validation + 2 finds + 1 insert = 5 queries
- For pure write benchmarking, could remove validation to reduce SELECT overhead

---

## 6. Configuration

### 6.1 Database Configuration

**File**: `config/database.php` - Ensure these settings for fair comparison:

```php
'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
        'sslmode' => 'prefer',
    ],

    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'unix_socket' => env('DB_SOCKET', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => 'InnoDB',
    ],
],
```

### 6.2 Environment Files

**PostgreSQL VM** (`.env`):

```ini
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-key-here

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=larabench
DB_USERNAME=postgres
DB_PASSWORD=your-password

# Disable query log in production
LOG_LEVEL=error
```

**MariaDB VM** (`.env`):

```ini
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-key-here

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=larabench
DB_USERNAME=root
DB_PASSWORD=your-password

# Disable query log in production
LOG_LEVEL=error
```

### 6.3 PHP Configuration

Ensure consistent PHP settings on both VMs:

```ini
memory_limit=512M
max_execution_time=60
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

---

## 7. Benchmark Execution

### 7.1 Initial Setup

On **both VMs**, run:

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader

# Generate app key
php artisan key:generate

# Run migrations and seeders
php artisan migrate:fresh --seed

# Cache configuration (important for performance)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verify row counts
php artisan tinker
>>> User::count();      // Should be 1001
>>> Product::count();   // Should be 2000
>>> Order::count();     // Should be 1000
```

### 7.2 Warm-up Queries

Before benchmarking, run warm-up queries to populate query cache:

```bash
# Hit each endpoint a few times
curl http://localhost/api/products
curl http://localhost/api/products?sort=price_asc
curl -X POST http://localhost/api/orders \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"product_id":1,"quantity":2}'
```

### 7.3 READ Benchmark (wrk)

Basic product listing:

```bash
# PostgreSQL
wrk -t4 -c40 -d60s http://VM_A_IP/api/products

# MariaDB
wrk -t4 -c40 -d60s http://VM_B_IP/api/products
```

With price sorting:

```bash
# PostgreSQL
wrk -t4 -c40 -d60s "http://VM_A_IP/api/products?sort=price_asc"

# MariaDB
wrk -t4 -c40 -d60s "http://VM_B_IP/api/products?sort=price_asc"
```

With filtering:

```bash
# PostgreSQL
wrk -t4 -c40 -d60s "http://VM_A_IP/api/products?min_price=50&max_price=200"

# MariaDB
wrk -t4 -c40 -d60s "http://VM_B_IP/api/products?min_price=50&max_price=200"
```

### 7.4 WRITE Benchmark (wrk + Lua)

Create `post_order.lua`:

```lua
wrk.method = "POST"
wrk.body   = '{"user_id":1,"product_id":1,"quantity":2}'
wrk.headers["Content-Type"] = "application/json"

-- Rotate through different products to avoid hotspots
local counter = 1
request = function()
    local user_id = (counter % 1000) + 1
    local product_id = (counter % 2000) + 1
    counter = counter + 1
    
    wrk.body = string.format('{"user_id":%d,"product_id":%d,"quantity":2}', user_id, product_id)
    return wrk.format(nil, "/api/orders")
end
```

Run benchmark:

```bash
# PostgreSQL
wrk -t4 -c40 -d60s -s post_order.lua http://VM_A_IP/api/orders

# MariaDB
wrk -t4 -c40 -d60s -s post_order.lua http://VM_B_IP/api/orders
```

### 7.5 Alternative: Using k6

For more control and better metrics, use k6:

**File**: `benchmark.js`

```javascript
import http from 'k6/http';
import { check } from 'k6';

export let options = {
    vus: 40,           // 40 virtual users
    duration: '60s',   // 60 second test
};

export default function () {
    // READ test
    let readResponse = http.get('http://VM_IP/api/products?sort=price_asc');
    check(readResponse, {
        'read status is 200': (r) => r.status === 200,
    });

    // WRITE test
    let payload = JSON.stringify({
        user_id: Math.floor(Math.random() * 1000) + 1,
        product_id: Math.floor(Math.random() * 2000) + 1,
        quantity: 2,
    });

    let writeResponse = http.post('http://VM_IP/api/orders', payload, {
        headers: { 'Content-Type': 'application/json' },
    });
    check(writeResponse, {
        'write status is 201': (r) => r.status === 201,
    });
}
```

Run:

```bash
k6 run benchmark.js
```

---

## 8. Performance Considerations

### 8.1 Database-Specific Behavior

**Query Cache**:
- **MariaDB**: Has query cache (may be disabled in recent versions)
- **PostgreSQL**: No query cache, relies on OS page cache

**Connection Pooling**:
- Both benefit from persistent connections
- Laravel uses `DB::connection()->getPdo()->getAttribute(PDO::ATTR_PERSISTENT)`

**Transaction Isolation**:
- Both default to READ COMMITTED
- PostgreSQL uses MVCC more aggressively

**Auto-increment**:
- PostgreSQL uses SEQUENCE (may have different contention behavior)
- MariaDB uses AUTO_INCREMENT (table-level locking on older versions)

### 8.2 Index Usage

All indexes defined are supported by both databases:

- B-tree indexes (default for both)
- Unique constraints (automatically indexed)
- Foreign keys (automatically indexed)
- Composite indexes (multi-column)

**Check index usage**:

```sql
-- PostgreSQL
EXPLAIN ANALYZE SELECT * FROM products WHERE price >= 50 AND price <= 200 ORDER BY price;

-- MariaDB
EXPLAIN SELECT * FROM products WHERE price >= 50 AND price <= 200 ORDER BY price;
```

### 8.3 Fair Comparison Checklist

- [ ] Both VMs have identical hardware specs
- [ ] Both VMs have identical PHP version and configuration
- [ ] Both databases have similar configuration (memory, cache sizes)
- [ ] Both databases use same storage engine (InnoDB for MariaDB)
- [ ] Same dataset seeded with same Faker seeds
- [ ] Laravel cache cleared and re-cached on both
- [ ] Warm-up queries run before benchmarking
- [ ] No other services running during benchmark
- [ ] Multiple benchmark runs to account for variance

### 8.4 Metrics to Collect

**Application Level**:
- Requests per second
- Average latency
- P50, P95, P99 latency
- Error rate
- Throughput (MB/s)

**Database Level**:
- Query execution time
- Connection pool usage
- Cache hit rate
- Lock wait time
- Disk I/O

**System Level**:
- CPU usage
- Memory usage
- Network I/O
- Disk I/O

### 8.5 Optimization Opportunities

If you want to stress-test further:

1. **Increase concurrency**: `-c100` or more
2. **Remove validation**: Skip `exists` checks to reduce SELECT overhead
3. **Add stock updates**: Make writes more complex
4. **Add joins**: Load product data with orders
5. **Test bulk inserts**: Create multiple orders per request
6. **Test with different page sizes**: `per_page=50` vs `per_page=5`

---

## Summary

This benchmark setup provides:

✅ **Cross-compatible code**: Works on both PostgreSQL and MariaDB  
✅ **Deterministic data**: Same faker seeds = comparable datasets  
✅ **Performance-aware schema**: Strategic indexes on access patterns  
✅ **Realistic workloads**: Pagination, filtering, sorting, transactions  
✅ **Measurable endpoints**: Clear READ and WRITE scenarios  
✅ **Reproducible**: Standard Laravel conventions, no custom packages  

**Next steps**:
1. Deploy to both VMs
2. Run migrations and seeders
3. Execute warm-up queries
4. Run benchmarks multiple times
5. Compare metrics
6. Analyze database query logs for optimization opportunities
