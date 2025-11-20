# VM Setup Guide: Laravel + PostgreSQL/MariaDB

Complete guide to set up Laravel benchmark application on virtual machines for performance testing.

---

## 📋 Prerequisites

### Hardware Requirements (per VM)

**Target Configuration** (this guide is optimized for):
- 2 CPU cores
- 2GB RAM
- 20GB disk space
- Ubuntu 22.04 LTS or Debian 12

**For higher loads** (optional):
- 4 CPU cores
- 4-8GB RAM
- 40GB SSD storage
- Ubuntu 22.04 LTS

> ⚠️ **Note**: All configurations in this guide are tuned for 2GB RAM VMs.

### VM Architecture (3 VMs Total)

This setup uses **three VMs**:
- **VM A**: Laravel + PostgreSQL + Node Exporter + Cloudflare Tunnel
- **VM B**: Laravel + MariaDB + Node Exporter + Cloudflare Tunnel
- **VM C**: Prometheus + Grafana (monitoring server)

### Network Requirements

- VMs should have static IP addresses
- Port 8000 accessible for Laravel (or configure Nginx)
- SSH access configured
- Internet access for package installation

---

## 🖥️ VM Architecture

We'll set up **three VMs**:

```
┌──────────────────────────────┐   ┌──────────────────────────────┐
│   VM A (PostgreSQL)          │   │   VM B (MariaDB)             │
│                              │   │                              │
│  Ubuntu 22.04 LTS            │   │  Ubuntu 22.04 LTS            │
│  PHP 8.2 + Laravel 10        │   │  PHP 8.2 + Laravel 10        │
│  PostgreSQL 15               │   │  MariaDB 10.11               │
│  Nginx                       │   │  Nginx                       │
│  Node Exporter :9100         │   │  Node Exporter :9100         │
│  Cloudflare Tunnel (idle)    │   │  Cloudflare Tunnel (idle)    │
│                              │   │                              │
│  IP: 192.168.1.10            │   │  IP: 192.168.1.11            │
└──────────────┬───────────────┘   └────────────┬─────────────────┘
               │                                │
               │    Metrics Collection          │
               └────────────────┬───────────────┘
                                │
                    ┌───────────▼───────────┐
                    │   VM C (Monitoring)   │
                    │                       │
                    │  Prometheus :9090     │
                    │  Grafana :3000        │
                    │                       │
                    │  IP: 192.168.1.12     │
                    └───────────────────────┘
                                │
                        ┌───────▼──────┐
                        │ Load Testing │
                        │   Machine    │
                        │   Vegeta     │
                        └──────────────┘
```

### Resource Allocation (2GB RAM per VM)

**VM A & B** (Laravel + Database):
```
Total RAM: 2048 MB
├── PostgreSQL/MariaDB: 512-768 MB
├── PHP-FPM (25 workers): ~750 MB
├── Nginx: 50-100 MB
├── Node Exporter: 20-30 MB
├── Cloudflare Tunnel: 20-40 MB (idle)
├── System/OS: 200-300 MB
└── Buffer: 100-150 MB
```

**VM C** (Monitoring):
- Covered in separate Prometheus/Grafana setup guide

---

## 🚀 Setup VM A: PostgreSQL

### Step 1: Update System

```bash
# SSH into VM A
ssh user@192.168.1.10

# Update system
sudo apt update && sudo apt upgrade -y

# Install basic tools
sudo apt install -y curl wget git unzip software-properties-common
```

### Step 2: Install PHP 8.2

```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP and required extensions
sudo apt install -y \
    php8.2 \
    php8.2-cli \
    php8.2-fpm \
    php8.2-common \
    php8.2-mysql \
    php8.2-pgsql \
    php8.2-zip \
    php8.2-gd \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-xml \
    php8.2-bcmath \
    php8.2-intl

# Verify PHP installation
php -v
# Should show: PHP 8.2.x
```

### Step 3: Install Composer

```bash
# Download and install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verify
composer --version
```

### Step 4: Install PostgreSQL 15

```bash
# Install PostgreSQL
sudo apt install -y postgresql-15 postgresql-contrib-15

# Start and enable PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Verify
sudo systemctl status postgresql

# Check version
psql --version
# Should show: psql (PostgreSQL) 15.x
```

### Step 5: Configure PostgreSQL

```bash
# Switch to postgres user
sudo -u postgres psql

# Inside PostgreSQL prompt:
-- Create benchmark database
CREATE DATABASE larabench;

-- Create user (optional, or use postgres user)
CREATE USER laravel WITH PASSWORD 'your_secure_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE larabench TO laravel;

-- Exit
\q

# Edit PostgreSQL configuration for performance
sudo nano /etc/postgresql/15/main/postgresql.conf
```

Add/modify these settings (optimized for 2GB RAM):

```conf
# Memory Settings (tuned for 2GB RAM VM)
shared_buffers = 512MB                  # 25% of RAM
effective_cache_size = 1536MB           # 75% of RAM
maintenance_work_mem = 128MB
work_mem = 8MB

# Checkpoint Settings
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100

# Connection Settings (reduced for 2GB RAM)
max_connections = 100

# Query Planner
random_page_cost = 1.1                  # SSD tuning
effective_io_concurrency = 200          # SSD tuning

# Write Performance
synchronous_commit = on                 # Keep for fair benchmark
wal_level = minimal                     # Reduce overhead for benchmark

# Additional Memory Optimizations
shared_preload_libraries = ''           # Reduce overhead
max_prepared_transactions = 0
```

Restart PostgreSQL:

```bash
sudo systemctl restart postgresql
```

### Step 6: Allow Remote Connections (if needed)

```bash
# Edit postgresql.conf
sudo nano /etc/postgresql/15/main/postgresql.conf

# Change listen_addresses
listen_addresses = '*'

# Edit pg_hba.conf
sudo nano /etc/postgresql/15/main/pg_hba.conf

# Add line for your network (adjust IP range as needed)
host    all             all             192.168.1.0/24          md5

# Restart
sudo systemctl restart postgresql
```

### Step 7: Clone and Setup Laravel

```bash
# Create application directory
sudo mkdir -p /var/www/larabench
sudo chown -R $USER:$USER /var/www/larabench

# Clone your repository or upload files
cd /var/www/larabench
git clone https://github.com/yourusername/larabench.git .

# Or upload via SCP from your local machine:
# scp -r /path/to/larabench/* user@192.168.1.10:/var/www/larabench/

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
sudo chown -R www-data:www-data /var/www/larabench/storage
sudo chown -R www-data:www-data /var/www/larabench/bootstrap/cache
chmod -R 775 /var/www/larabench/storage
chmod -R 775 /var/www/larabench/bootstrap/cache
```

### Step 8: Configure Environment

```bash
cd /var/www/larabench

# Create .env file
cat > .env << 'EOF'
APP_NAME="Laravel Benchmark PostgreSQL"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.10

# PostgreSQL Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=larabench
DB_USERNAME=postgres
DB_PASSWORD=your_secure_password

LOG_CHANNEL=stack
LOG_LEVEL=error

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
EOF

# Generate application key
php artisan key:generate

# Cache configuration
php artisan config:cache
php artisan route:cache
```

### Step 9: Run Migrations

```bash
cd /var/www/larabench

# Run migrations and seed database
php artisan migrate:fresh --seed --force

# Verify data
php artisan tinker --execute="
    echo 'Users: ' . \App\Models\User::count() . PHP_EOL;
    echo 'Products: ' . \App\Models\Product::count() . PHP_EOL;
    echo 'Orders: ' . \App\Models\Order::count() . PHP_EOL;
"
```

Expected output:
```
Users: 1001
Products: 2000
Orders: 1000
```

### Step 10: Setup Nginx

```bash
# Install Nginx
sudo apt install -y nginx

# Stop Nginx temporarily
sudo systemctl stop nginx
```

#### Configure Main Nginx Settings

Edit the main Nginx configuration:

```bash
sudo nano /etc/nginx/nginx.conf
```

Replace with this optimized configuration (2GB RAM, 2 CPU cores):

```nginx
user www-data;

# Match number of CPU cores
worker_processes 2;

# Process priority
worker_priority -5;

pid /run/nginx.pid;
error_log /var/log/nginx/error.log warn;

# Load modules
include /etc/nginx/modules-enabled/*.conf;

events {
    # Max connections per worker (2 workers × 1024 = 2048 total)
    worker_connections 1024;
    
    # Use epoll for Linux (efficient)
    use epoll;
    
    # Accept multiple connections at once
    multi_accept on;
    accept_mutex off;
}

http {
    ##
    # Basic Settings
    ##
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    
    # Keep-alive settings
    keepalive_timeout 65;
    keepalive_requests 100;
    
    # Hash table sizes
    types_hash_max_size 2048;
    server_names_hash_bucket_size 64;
    server_tokens off;
    
    # Timeouts (for API workloads)
    client_body_timeout 12;
    client_header_timeout 12;
    send_timeout 10;
    reset_timedout_connection on;

    ##
    # Buffer Sizes (optimized for 2GB RAM, API responses)
    ##
    client_body_buffer_size 16K;
    client_header_buffer_size 1k;
    client_max_body_size 8m;
    large_client_header_buffers 4 8k;

    ##
    # MIME Types
    ##
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    ##
    # Logging Settings
    ##
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" $request_time';
    
    log_format benchmark '$remote_addr [$time_local] "$request" $status '
                        'req_time=$request_time upstream_time=$upstream_response_time';
    
    # Buffer logs to reduce I/O
    access_log /var/log/nginx/access.log main buffer=32k flush=5s;

    ##
    # Gzip Settings (DISABLED for benchmark to save CPU)
    ##
    gzip off;

    ##
    # Connection Settings
    ##
    worker_rlimit_nofile 10000;

    ##
    # Virtual Host Configs
    ##
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

#### Configure Site (Laravel Application)

Create the site configuration:

```bash
sudo nano /etc/nginx/sites-available/larabench
```

Add this configuration (adjust IP for VM B to 192.168.1.11):

```nginx
# Laravel Benchmark Site Configuration
# Optimized for: 2 CPU cores, 2GB RAM
# VM A: 192.168.1.10 (PostgreSQL)
# VM B: 192.168.1.11 (MariaDB)

server {
    listen 80;
    listen [::]:80;
    server_name 192.168.1.10 larabench-pgsql.local;  # Change to .11 for VM B
    root /var/www/larabench/public;

    index index.php index.html;
    charset utf-8;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Logging (buffered for performance)
    access_log /var/log/nginx/larabench-access.log combined buffer=32k flush=5s;
    error_log /var/log/nginx/larabench-error.log warn;

    # For maximum performance during benchmark, disable access logs:
    # access_log off;

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Static files (cache aggressively)
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|xml|svg|woff|woff2|ttf|eot)$ {
        expires max;
        access_log off;
        log_not_found off;
        add_header Cache-Control "public, immutable";
    }

    # Favicon and robots
    location = /favicon.ico {
        access_log off;
        log_not_found off;
        expires max;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    # PHP-FPM processing
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Buffer settings (tuned for API responses)
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_busy_buffers_size 32k;

        # Timeouts
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;

        # Keep-alive for better performance
        fastcgi_keep_conn on;

        # Hide PHP version
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to sensitive files
    location ~ /\.(?:git|env|htaccess|htpasswd) {
        deny all;
    }

    # Health check endpoint
    location = /health {
        access_log off;
        return 200 "OK\n";
        add_header Content-Type text/plain;
    }

    # PHP-FPM status (for monitoring)
    location ~ ^/(php-fpm-status|php-fpm-ping)$ {
        access_log off;
        allow 127.0.0.1;
        allow 192.168.1.0/24;  # Adjust to your network
        deny all;
        
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Enable site and restart:

```bash
# Disable default site
sudo rm /etc/nginx/sites-enabled/default

# Enable larabench site
sudo ln -s /etc/nginx/sites-available/larabench /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# If test passes, restart services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm

# Enable services on boot
sudo systemctl enable nginx
sudo systemctl enable php8.2-fpm

# Test endpoint
curl http://192.168.1.10

# Test API
curl http://192.168.1.10/api/products
```

### Step 11: Install Node Exporter (Monitoring)

Node Exporter exposes system metrics for Prometheus.

```bash
# Download Node Exporter (check for latest version)
cd /tmp
wget https://github.com/prometheus/node_exporter/releases/download/v1.7.0/node_exporter-1.7.0.linux-amd64.tar.gz

# Extract
tar xvfz node_exporter-1.7.0.linux-amd64.tar.gz

# Move binary
sudo mv node_exporter-1.7.0.linux-amd64/node_exporter /usr/local/bin/
sudo chmod +x /usr/local/bin/node_exporter

# Clean up
rm -rf node_exporter-1.7.0.linux-amd64*

# Create user for node_exporter
sudo useradd --no-create-home --shell /bin/false node_exporter

# Create systemd service
sudo nano /etc/systemd/system/node_exporter.service
```

Add this service configuration:

```ini
[Unit]
Description=Node Exporter
Wants=network-online.target
After=network-online.target

[Service]
User=node_exporter
Group=node_exporter
Type=simple
ExecStart=/usr/local/bin/node_exporter \
    --collector.disable-defaults \
    --collector.cpu \
    --collector.meminfo \
    --collector.loadavg \
    --collector.filesystem \
    --collector.netdev \
    --collector.diskstats \
    --collector.stat \
    --web.listen-address=:9100

Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Start Node Exporter:

```bash
# Reload systemd
sudo systemctl daemon-reload

# Start and enable
sudo systemctl start node_exporter
sudo systemctl enable node_exporter

# Check status
sudo systemctl status node_exporter

# Test metrics endpoint
curl http://localhost:9100/metrics
```

Allow Prometheus to access Node Exporter:

```bash
# Allow port 9100 from monitoring VM
sudo ufw allow from 192.168.1.12 to any port 9100
```

### Step 12: Install Cloudflare Tunnel (Idle)

Cloudflare Tunnel will be installed but kept idle for resource estimation.

```bash
# Add Cloudflare GPG key
sudo mkdir -p /usr/share/keyrings
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null

# Add Cloudflare repository
echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared jammy main" | sudo tee /etc/apt/sources.list.d/cloudflared.list

# Install cloudflared
sudo apt update
sudo apt install -y cloudflared

# Verify installation
cloudflared --version

# Create systemd service (idle mode)
sudo nano /etc/systemd/system/cloudflared-idle.service
```

Add this idle service configuration:

```ini
[Unit]
Description=Cloudflare Tunnel (Idle Mode)
After=network.target

[Service]
Type=simple
User=root
ExecStart=/usr/bin/sleep infinity
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable the idle service:

```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable (but keep idle)
sudo systemctl enable cloudflared-idle.service

# The service is installed but not actively tunneling
# This gives us an accurate resource footprint
```

> 📝 **Note**: Cloudflare Tunnel is installed but not configured. When idle, it uses minimal resources (~20-30MB RAM). For actual tunnel setup, see Cloudflare documentation.

### Step 13: Optimize PHP-FPM (for 2GB RAM)

```bash
# Edit PHP-FPM pool configuration
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Adjust these settings (optimized for 2GB RAM VM):

```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

# Process Manager (tuned for 2GB RAM with monitoring)
# Assuming ~30MB per PHP process:
# 2GB RAM - 512MB (DB) - 50MB (Nginx) - 50MB (Node/CF) - 256MB (system) = ~1100MB
# 1100MB / 30MB = ~36 max children, use 25 for safety
pm = dynamic
pm.max_children = 25              # Reduced for 2GB RAM + monitoring
pm.start_servers = 4              # Number of CPU cores / 2
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500             # Recycle workers to prevent memory leaks

# Process priority
process_priority = -10

# Performance tuning
pm.process_idle_timeout = 10s
request_terminate_timeout = 60s

# Status page (useful for monitoring)
pm.status_path = /php-fpm-status
ping.path = /php-fpm-ping
ping.response = pong

# Slow log (disable during benchmark)
;slowlog = /var/log/php8.2-fpm-slow.log
;request_slowlog_timeout = 5s

# Environment variables
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp
```

Edit PHP configuration for optimization:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Optimize these settings:

```ini
; Memory (conservative for 2GB RAM)
memory_limit = 128M

; Execution
max_execution_time = 60
max_input_time = 60

; File uploads
upload_max_filesize = 2M
post_max_size = 8M

; Performance
realpath_cache_size = 4M
realpath_cache_ttl = 600

; OPcache (very important for performance)
opcache.enable=1
opcache.memory_consumption=128          ; Reduced for 2GB RAM
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0               ; Always check (safe for benchmark)
opcache.fast_shutdown=1
opcache.enable_cli=0

; Disable unused extensions to save memory
;disable_functions = pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm

# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Verify configuration
php-fpm8.2 -t
```

### Step 14: Firewall Configuration

```bash
# Allow HTTP and SSH
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 8000/tcp

# Allow Node Exporter from monitoring VM
sudo ufw allow from 192.168.1.12 to any port 9100

# Enable firewall
sudo ufw enable

# Verify
sudo ufw status verbose
```

---

## 🖥️ Setup VM B: MariaDB

### Step 1-3: Same as VM A

Follow Steps 1-3 from VM A (Update System, Install PHP, Install Composer)

### Step 4: Install MariaDB 10.11

```bash
# SSH into VM B
ssh user@192.168.1.11

# Install MariaDB
sudo apt install -y mariadb-server mariadb-client

# Start and enable MariaDB
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Verify
sudo systemctl status mariadb

# Check version
mysql --version
# Should show: mysql Ver 15.1 Distrib 10.11.x-MariaDB
```

### Step 5: Configure MariaDB

```bash
# Secure installation
sudo mysql_secure_installation
# Set root password: your_secure_password
# Remove anonymous users: Y
# Disallow root login remotely: N (for benchmark VM)
# Remove test database: Y
# Reload privilege tables: Y

# Login to MariaDB
sudo mysql -u root -p

# Inside MariaDB prompt:
-- Create database
CREATE DATABASE larabench CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (optional)
CREATE USER 'laravel'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON larabench.* TO 'laravel'@'localhost';
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

### Step 6: Optimize MariaDB Configuration

```bash
# Edit MariaDB configuration
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

Add/modify these settings under `[mysqld]` (optimized for 2GB RAM):

```ini
[mysqld]
# InnoDB Settings (tuned for 2GB RAM)
innodb_buffer_pool_size = 768M         # ~40% of RAM (leaving room for PHP)
innodb_log_file_size = 128M
innodb_flush_log_at_trx_commit = 1     # Keep for fair benchmark
innodb_flush_method = O_DIRECT
innodb_file_per_table = 1
innodb_buffer_pool_instances = 1       # Use 1 for < 1GB buffer pool

# Connection Settings (reduced for 2GB RAM)
max_connections = 100
max_connect_errors = 1000000

# Query Cache (disabled in MariaDB 10.11+)
# query_cache_size = 0
# query_cache_type = 0

# Buffer Settings (reduced for 2GB RAM)
sort_buffer_size = 2M
read_buffer_size = 1M
read_rnd_buffer_size = 4M
join_buffer_size = 2M
tmp_table_size = 32M
max_heap_table_size = 32M

# Performance Schema (disable to save memory during benchmark)
performance_schema = OFF

# Binary Logging (disable for benchmark)
skip-log-bin
sync_binlog = 0

# Thread Settings (reduced for 2GB RAM)
thread_cache_size = 16
table_open_cache = 2000
table_definition_cache = 1024
open_files_limit = 4096

# Additional optimizations for 2GB RAM
key_buffer_size = 16M
max_allowed_packet = 16M
thread_stack = 192K

# InnoDB additional settings
innodb_read_io_threads = 2
innodb_write_io_threads = 2
innodb_flush_neighbors = 0             # SSD optimization
innodb_log_buffer_size = 8M
```

Restart MariaDB:

```bash
sudo systemctl restart mariadb
```

### Step 7: Allow Remote Connections (if needed)

```bash
# Edit bind-address
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf

# Change bind-address
bind-address = 0.0.0.0

# Restart
sudo systemctl restart mariadb

# Grant remote access (from load testing machine)
sudo mysql -u root -p

GRANT ALL PRIVILEGES ON larabench.* TO 'root'@'192.168.1.%' IDENTIFIED BY 'your_secure_password';
FLUSH PRIVILEGES;
EXIT;
```

### Step 8: Configure Environment

Follow Step 8 from VM A (PostgreSQL section), but use these environment settings:

```bash
# .env file for MariaDB
cat > .env << 'EOF'
APP_NAME="Laravel Benchmark MariaDB"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.11

# MariaDB Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=larabench
DB_USERNAME=root
DB_PASSWORD=your_secure_password

LOG_CHANNEL=stack
LOG_LEVEL=error

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
EOF
```

### Step 9-14: Same as VM A

Follow Steps 9-14 from VM A:
- Step 9: Run Migrations
- Step 10: Setup Nginx (update IP to 192.168.1.11 in config)
- Step 11: Install Node Exporter
- Step 12: Install Cloudflare Tunnel (idle)
- Step 13: Optimize PHP-FPM
- Step 14: Firewall Configuration

---

## 🔫 Setup Load Testing Machine

This can be your local machine or a third VM.

### Install Vegeta

```bash
# Linux
wget https://github.com/tsenart/vegeta/releases/download/v12.11.1/vegeta_12.11.1_linux_amd64.tar.gz
tar xzf vegeta_12.11.1_linux_amd64.tar.gz
sudo mv vegeta /usr/local/bin/
chmod +x /usr/local/bin/vegeta

# Verify
vegeta --version
```

### Create Target Files

```bash
mkdir -p vegeta_targets vegeta_results

# Distributed order test for VM A (PostgreSQL)
cat > vegeta_targets/pgsql_orders.txt << 'EOF'
POST http://192.168.1.10/api/orders
Content-Type: application/json

{"user_id":1,"product_id":1,"quantity":1}

POST http://192.168.1.10/api/orders
Content-Type: application/json

{"user_id":2,"product_id":2,"quantity":1}
EOF

# Distributed order test for VM B (MariaDB)
cat > vegeta_targets/mariadb_orders.txt << 'EOF'
POST http://192.168.1.11/api/orders
Content-Type: application/json

{"user_id":1,"product_id":1,"quantity":1}

POST http://192.168.1.11/api/orders
Content-Type: application/json

{"user_id":2,"product_id":2,"quantity":1}
EOF
```

### Run Quick Test

```bash
# Test PostgreSQL VM
vegeta attack \
    -targets=vegeta_targets/pgsql_orders.txt \
    -rate=50 \
    -duration=10s \
    | vegeta report

# Test MariaDB VM
vegeta attack \
    -targets=vegeta_targets/mariadb_orders.txt \
    -rate=50 \
    -duration=10s \
    | vegeta report
```

---

## ✅ Verification Checklist

### VM A (PostgreSQL) - 192.168.1.10

```bash
# Check PostgreSQL
sudo systemctl status postgresql
psql -U postgres -d larabench -c "SELECT COUNT(*) FROM users;"

# Check PHP
php -v

# Check web server
curl http://192.168.1.10

# Check API endpoints
curl http://192.168.1.10/api/products
curl -X POST http://192.168.1.10/api/orders \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"product_id":1,"quantity":1}'
```

### VM B (MariaDB) - 192.168.1.11

```bash
# Check MariaDB
sudo systemctl status mariadb
mysql -u root -p larabench -e "SELECT COUNT(*) FROM users;"

# Check PHP
php -v

# Check web server
curl http://192.168.1.11

# Check API endpoints
curl http://192.168.1.11/api/products
curl -X POST http://192.168.1.11/api/orders \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"product_id":1,"quantity":1}'
```

### Both VMs

- [ ] PHP 8.2 installed and running
- [ ] Database installed and running
- [ ] Laravel application deployed
- [ ] Database seeded (4,000 rows)
- [ ] Nginx configured and responding
- [ ] API endpoints working
- [ ] Node Exporter running (port 9100)
- [ ] Cloudflare Tunnel installed (idle)
- [ ] Firewall configured
- [ ] Performance tuning applied

---

## 🔍 Monitoring Setup (Optional but Recommended)

### Install htop for real-time monitoring

```bash
# On both VMs
sudo apt install -y htop iotop

# Monitor during benchmark
htop
```

### Enable query logging (for debugging)

**PostgreSQL**:
```bash
sudo nano /etc/postgresql/15/main/postgresql.conf

# Add:
log_statement = 'all'
log_duration = on
log_min_duration_statement = 100

sudo systemctl restart postgresql

# View logs
sudo tail -f /var/log/postgresql/postgresql-15-main.log
```

**MariaDB**:
```bash
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf

# Add under [mysqld]:
general_log = 1
general_log_file = /var/log/mysql/query.log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 0.1

sudo systemctl restart mariadb

# View logs
sudo tail -f /var/log/mysql/query.log
```

---

## 🎯 Performance Tuning Tips

### PostgreSQL Specific

```bash
# Analyze tables after seeding
psql -U postgres -d larabench -c "VACUUM ANALYZE;"

# Check index usage
psql -U postgres -d larabench -c "
SELECT schemaname, tablename, indexname 
FROM pg_indexes 
WHERE tablename IN ('users', 'products', 'orders');"
```

### MariaDB Specific

```bash
# Optimize tables after seeding
mysql -u root -p larabench -e "OPTIMIZE TABLE users, products, orders;"

# Check index usage
mysql -u root -p larabench -e "
SHOW INDEX FROM products;
SHOW INDEX FROM orders;"
```

### System-Level Tuning (for 2GB RAM)

```bash
# Increase open file limits
sudo nano /etc/security/limits.conf

# Add:
* soft nofile 10000
* hard nofile 10000

# Edit sysctl for network and memory tuning
sudo nano /etc/sysctl.conf

# Add these optimizations:
# Network optimization
net.core.somaxconn = 1024
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.ip_local_port_range = 10000 65535
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 15

# Memory optimization (conservative for 2GB)
vm.swappiness = 10
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5

# Apply changes
sudo sysctl -p

# Verify
ulimit -n
```

### Monitor Memory Usage

```bash
# Check current memory usage
free -h

# Monitor during benchmark
watch -n 1 free -h

# Check per-process memory
ps aux --sort=-%mem | head -15

# Check memory by service
echo "=== Memory Usage by Service ==="
echo -n "PostgreSQL/MariaDB: "; ps aux | grep -E 'postgres|mysql' | awk '{sum+=$6} END {print sum/1024 " MB"}'
echo -n "PHP-FPM: "; ps aux | grep php-fpm | awk '{sum+=$6} END {print sum/1024 " MB"}'
echo -n "Nginx: "; ps aux | grep nginx | awk '{sum+=$6} END {print sum/1024 " MB"}'
echo -n "Node Exporter: "; ps aux | grep node_exporter | awk '{sum+=$6} END {print sum/1024 " MB"}'
echo -n "Cloudflared: "; ps aux | grep cloudflared | awk '{sum+=$6} END {print sum/1024 " MB"}'
```

---

## 🚀 Quick Start Script

Save as `vm_setup.sh` on each VM:

```bash
#!/bin/bash

echo "VM Setup for Laravel Benchmark"
read -p "Select database (1=PostgreSQL, 2=MariaDB): " choice

# Update system
sudo apt update && sudo apt upgrade -y

# Install common packages
sudo apt install -y curl wget git unzip software-properties-common

# Install PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-common \
    php8.2-mysql php8.2-pgsql php8.2-zip php8.2-gd php8.2-mbstring \
    php8.2-curl php8.2-xml php8.2-bcmath php8.2-intl

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

if [ "$choice" == "1" ]; then
    echo "Installing PostgreSQL..."
    sudo apt install -y postgresql-15 postgresql-contrib-15
    sudo systemctl start postgresql
    sudo systemctl enable postgresql
    echo "PostgreSQL installed. Configure with: sudo -u postgres psql"
else
    echo "Installing MariaDB..."
    sudo apt install -y mariadb-server mariadb-client
    sudo systemctl start mariadb
    sudo systemctl enable mariadb
    echo "MariaDB installed. Run: sudo mysql_secure_installation"
fi

echo "Base setup complete!"
echo "Next steps:"
echo "1. Configure database"
echo "2. Deploy Laravel application"
echo "3. Run: php artisan migrate:fresh --seed"
```

---

## 📋 Troubleshooting

### Issue: Can't connect to database

```bash
# PostgreSQL
sudo systemctl status postgresql
sudo -u postgres psql -c "SELECT 1;"

# MariaDB
sudo systemctl status mariadb
mysql -u root -p -e "SELECT 1;"
```

### Issue: Permission denied errors

```bash
# Fix storage permissions
sudo chown -R www-data:www-data /var/www/larabench/storage
sudo chmod -R 775 /var/www/larabench/storage
```

### Issue: Slow seeding

Seeding 4,000 rows takes time:
- Users: ~30 seconds (password hashing)
- Products: ~10 seconds
- Orders: ~5 seconds
- **Total: ~45 seconds** (normal)

### Issue: Out of memory during seeding

```bash
# Increase PHP CLI memory limit
sudo nano /etc/php/8.2/cli/php.ini

# Change:
memory_limit = 256M              # Conservative for 2GB RAM VM

# Re-run seeding
php artisan migrate:fresh --seed
```

### Issue: Nginx 502 Bad Gateway

```bash
# Check if PHP-FPM is running
sudo systemctl status php8.2-fpm

# Check PHP-FPM errors
sudo tail -f /var/log/php8.2-fpm.log

# Verify socket permissions
ls -la /var/run/php/php8.2-fpm.sock

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### Issue: High memory usage

```bash
# Check what's using memory
sudo ps aux --sort=-%mem | head -20

# If PostgreSQL is using too much:
# Edit postgresql.conf and reduce shared_buffers to 384MB

# If MariaDB is using too much:
# Edit my.cnf and reduce innodb_buffer_pool_size to 512M

# If PHP-FPM is spawning too many processes:
# Edit www.conf and reduce pm.max_children to 20
```

---

## 🎓 Next Steps

1. ✅ Both VMs set up? → Test endpoints manually
2. 🔫 Ready to benchmark? → Set up load testing machine
3. 📊 Ready to compare? → Run Vegeta tests
4. 📈 Want detailed guide? → Read `LOAD_TEST_VEGETA.md`

---

## 📞 Quick Reference

### VM A (PostgreSQL) - 192.168.1.10
```bash
# Connect
ssh user@192.168.1.10

# Check all services status
sudo systemctl status postgresql nginx php8.2-fpm node_exporter

# Check logs
sudo tail -f /var/log/postgresql/postgresql-15-main.log
sudo tail -f /var/log/nginx/larabench-error.log
sudo tail -f /var/www/larabench/storage/logs/laravel.log

# Check metrics
curl http://localhost:9100/metrics | grep -E "node_memory|node_cpu"

# Restart services
sudo systemctl restart postgresql
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart node_exporter

# Check memory usage
free -h && ps aux --sort=-%mem | head -10
```

### VM B (MariaDB) - 192.168.1.11
```bash
# Connect
ssh user@192.168.1.11

# Check all services status
sudo systemctl status mariadb nginx php8.2-fpm node_exporter

# Check logs
sudo tail -f /var/log/mysql/error.log
sudo tail -f /var/log/nginx/larabench-error.log
sudo tail -f /var/www/larabench/storage/logs/laravel.log

# Check metrics
curl http://localhost:9100/metrics | grep -E "node_memory|node_cpu"

# Restart services
sudo systemctl restart mariadb
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart node_exporter

# Check memory usage
free -h && ps aux --sort=-%mem | head -10
```

### Common Commands (Both VMs)

```bash
# Test API endpoints
curl http://192.168.1.10/api/products  # PostgreSQL
curl http://192.168.1.11/api/products  # MariaDB

# Check Node Exporter
curl http://192.168.1.10:9100/metrics
curl http://192.168.1.11:9100/metrics

# Monitor resource usage in real-time
htop

# Network connections
ss -tunap | grep -E "80|9100"

# Disk usage
df -h
du -sh /var/www/larabench
```

---

## 📊 Resource Consumption Summary

### Expected Memory Usage (2GB RAM VM)

After full setup:

```
Component              | Memory Usage | % of 2GB
-------------------------------------------------
PostgreSQL/MariaDB     | 512-768 MB   | 25-38%
PHP-FPM (25 workers)   | 600-750 MB   | 30-37%
Nginx (2 workers)      | 50-100 MB    | 2-5%
Node Exporter          | 20-30 MB     | 1-2%
Cloudflare Tunnel      | 20-30 MB     | 1-2%
System/OS              | 200-300 MB   | 10-15%
-------------------------------------------------
TOTAL USED             | ~1400-2000MB | 70-98%
FREE/BUFFER            | 50-650 MB    | 2-30%
```

### Port Usage

```
Port | Service           | Access
-------------------------------------
22   | SSH               | External
80   | Nginx (HTTP)      | External
9100 | Node Exporter     | Monitoring VM only
```

### Service Startup Order

```bash
# After reboot, services start automatically in this order:
1. System initialization
2. Database (PostgreSQL/MariaDB)
3. PHP-FPM
4. Nginx
5. Node Exporter
6. Cloudflared (idle)
```

### Verify All Services

```bash
# Quick health check script
cat > /tmp/check_services.sh << 'EOF'
#!/bin/bash
echo "=== Service Health Check ==="
echo -n "PostgreSQL/MariaDB: "; systemctl is-active postgresql mariadb 2>/dev/null || echo "N/A"
echo -n "PHP-FPM: "; systemctl is-active php8.2-fpm
echo -n "Nginx: "; systemctl is-active nginx
echo -n "Node Exporter: "; systemctl is-active node_exporter
echo ""
echo "=== Port Check ==="
echo -n "HTTP (80): "; nc -z localhost 80 && echo "OK" || echo "FAIL"
echo -n "Node Exporter (9100): "; nc -z localhost 9100 && echo "OK" || echo "FAIL"
echo ""
echo "=== Memory Usage ==="
free -h | grep Mem
EOF

chmod +x /tmp/check_services.sh
/tmp/check_services.sh
```

---

Your VMs are ready for benchmarking! 🚀

