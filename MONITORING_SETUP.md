# Monitoring Setup: Prometheus + Grafana (VM C)

Complete setup guide for the monitoring VM to collect metrics from Laravel benchmark VMs.

---

## 📋 VM C Specifications

**Recommended Configuration**:
- 2 CPU cores
- 2GB RAM
- 20GB disk space
- Ubuntu 22.04 LTS
- IP: 192.168.1.12

---

## 🏗️ Architecture

```
┌──────────────────────────────┐   ┌──────────────────────────────┐
│   VM A (192.168.1.10)        │   │   VM B (192.168.1.11)        │
│   Laravel + PostgreSQL       │   │   Laravel + MariaDB          │
│   Node Exporter :9100        │   │   Node Exporter :9100        │
└──────────────┬───────────────┘   └────────────┬─────────────────┘
               │                                │
               │        Scrape Metrics          │
               └────────────────┬───────────────┘
                                │
                    ┌───────────▼───────────────┐
                    │   VM C (192.168.1.12)     │
                    │   Prometheus :9090        │
                    │   Grafana :3000           │
                    │   Nginx :80 (proxy)       │
                    └───────────────────────────┘
```

---

## 🚀 Setup Steps

### Step 1: Update System

```bash
# SSH into VM C
ssh user@192.168.1.12

# Update system
sudo apt update && sudo apt upgrade -y

# Install basic tools
sudo apt install -y curl wget git unzip
```

### Step 2: Install Prometheus

```bash
# Create prometheus user
sudo useradd --no-create-home --shell /bin/false prometheus

# Create directories
sudo mkdir -p /etc/prometheus
sudo mkdir -p /var/lib/prometheus

# Download Prometheus (check for latest version)
cd /tmp
wget https://github.com/prometheus/prometheus/releases/download/v3.7.3/prometheus-3.7.3.linux-amd64.tar.gz

# Extract
tar xvfz prometheus-3.7.3.linux-amd64.tar.gz
cd prometheus-3.7.3.linux-amd64

# Move binaries
sudo mv prometheus /usr/local/bin/
sudo mv promtool /usr/local/bin/

# Move config files
sudo mv consoles /etc/prometheus/
sudo mv console_libraries /etc/prometheus/

# Clean up
cd /tmp
rm -rf prometheus-3.7.3.linux-amd64*

# Set ownership
sudo chown -R prometheus:prometheus /etc/prometheus
sudo chown -R prometheus:prometheus /var/lib/prometheus
```

### Step 3: Configure Prometheus

Create Prometheus configuration:

```bash
sudo nano /etc/prometheus/prometheus.yml
```

Add this configuration:

```yaml
# Prometheus configuration for Laravel benchmark monitoring
global:
  scrape_interval: 15s
  evaluation_interval: 15s
  external_labels:
    monitor: 'laravel-benchmark'

# Alerting (optional)
alerting:
  alertmanagers:
    - static_configs:
        - targets: []

# Load rules
rule_files:
  # - "alerts.yml"

# Scrape configs
scrape_configs:
  # Prometheus self-monitoring
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']
        labels:
          instance: 'prometheus-server'

  # VM A - PostgreSQL
  - job_name: 'laravel-postgresql'
    static_configs:
      - targets: ['192.168.1.10:9100']
        labels:
          instance: 'vm-pgsql'
          database: 'postgresql'
          stack: 'laravel-pgsql'

  # VM B - MariaDB
  - job_name: 'laravel-mariadb'
    static_configs:
      - targets: ['192.168.1.11:9100']
        labels:
          instance: 'vm-mariadb'
          database: 'mariadb'
          stack: 'laravel-mariadb'
```

Set permissions:

```bash
sudo chown prometheus:prometheus /etc/prometheus/prometheus.yml
```

### Step 4: Create Prometheus Service

```bash
sudo nano /etc/systemd/system/prometheus.service
```

Add this service configuration:

```ini
[Unit]
Description=Prometheus
Wants=network-online.target
After=network-online.target

[Service]
User=prometheus
Group=prometheus
Type=simple
ExecStart=/usr/local/bin/prometheus \
    --config.file=/etc/prometheus/prometheus.yml \
    --storage.tsdb.path=/var/lib/prometheus/ \
    --web.console.templates=/etc/prometheus/consoles \
    --web.console.libraries=/etc/prometheus/console_libraries \
    --storage.tsdb.retention.time=30d \
    --web.listen-address=:9090

Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Start Prometheus:

```bash
sudo systemctl daemon-reload
sudo systemctl start prometheus
sudo systemctl enable prometheus

# Check status
sudo systemctl status prometheus

# Test Prometheus UI
curl http://localhost:9090
```

### Step 5: Install Grafana

```bash
# Install dependencies
sudo apt install -y software-properties-common

# Add Grafana GPG key
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -

# Add Grafana repository
echo "deb https://packages.grafana.com/oss/deb stable main" | sudo tee /etc/apt/sources.list.d/grafana.list

# Install Grafana
sudo apt update
sudo apt install -y grafana

# Start Grafana
sudo systemctl start grafana-server
sudo systemctl enable grafana-server

# Check status
sudo systemctl status grafana-server

# Test Grafana UI
curl http://localhost:3000
```

### Step 6: Install Nginx (Reverse Proxy)

```bash
# Install Nginx
sudo apt install -y nginx

# Create Nginx configuration
sudo nano /etc/nginx/sites-available/monitoring
```

Add this configuration:

```nginx
# Prometheus
server {
    listen 80;
    server_name prometheus.local 192.168.1.12;
    
    location / {
        proxy_pass http://localhost:9090;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}

# Grafana
server {
    listen 80;
    server_name grafana.local;
    
    location / {
        proxy_pass http://localhost:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Enable site:

```bash
# Disable default
sudo rm /etc/nginx/sites-enabled/default

# Enable monitoring
sudo ln -s /etc/nginx/sites-available/monitoring /etc/nginx/sites-enabled/

# Test and reload
sudo nginx -t
sudo systemctl restart nginx
```

### Step 7: Configure Firewall

```bash
# Allow SSH, HTTP, Prometheus, Grafana
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 9090/tcp
sudo ufw allow 3000/tcp

# Enable firewall
sudo ufw enable

# Verify
sudo ufw status
```

---

## 🎨 Grafana Setup

### Initial Login

1. Open browser: `http://192.168.1.12:3000`
2. Default credentials:
   - Username: `admin`
   - Password: `admin`
3. Change password when prompted

### Add Prometheus Data Source

1. Go to **Configuration** → **Data Sources**
2. Click **Add data source**
3. Select **Prometheus**
4. Configure:
   - **Name**: Prometheus
   - **URL**: `http://localhost:9090`
   - Click **Save & Test**

### Import Dashboard

#### Option 1: Import Laravel Benchmark Dashboard (Recommended)

A pre-configured dashboard for side-by-side comparison is available in the repository.

1. Go to **Dashboards** → **Import**
2. Click **Upload JSON file**
3. Select `grafana-dashboard-laravel-benchmark.json` from your repository
4. Select **Prometheus** as data source
5. Click **Import**

**Dashboard Features**:
- ✅ Side-by-side comparison of PostgreSQL vs MariaDB
- ✅ CPU usage and load average
- ✅ RAM and Swap usage
- ✅ Disk I/O (throughput and IOPS)
- ✅ Network I/O (throughput and packets)
- ✅ Auto-refresh every 10 seconds
- ✅ Blue color for PostgreSQL, Orange for MariaDB

#### Option 2: Use Pre-built Node Exporter Dashboard

1. Go to **Dashboards** → **Import**
2. Enter dashboard ID: `1860` (Node Exporter Full)
3. Select **Prometheus** as data source
4. Click **Import**

> **Note**: Dashboard 1860 is a general-purpose dashboard. Use Option 1 for benchmark-specific metrics.

#### Option 3: Create Custom Dashboard

Example queries for Laravel benchmark:

**CPU Usage**:
```promql
100 - (avg by (instance) (irate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)
```

**Memory Usage**:
```promql
100 * (1 - ((node_memory_MemAvailable_bytes) / (node_memory_MemTotal_bytes)))
```

**Network I/O**:
```promql
rate(node_network_receive_bytes_total[5m])
rate(node_network_transmit_bytes_total[5m])
```

**Disk I/O**:
```promql
rate(node_disk_read_bytes_total[5m])
rate(node_disk_written_bytes_total[5m])
```

---

## 📊 Useful Queries

### Compare PostgreSQL vs MariaDB CPU

```promql
avg by (database) (100 - (avg by (instance) (irate(node_cpu_seconds_total{mode="idle"}[5m])) * 100))
```

### Compare Memory Usage

```promql
100 * (1 - ((node_memory_MemAvailable_bytes) / (node_memory_MemTotal_bytes)))
```

### Network Load

```promql
rate(node_network_receive_bytes_total{device="eth0"}[5m])
```

---

## ✅ Verification

```bash
# Check Prometheus targets
curl http://localhost:9090/api/v1/targets | jq

# Check if scraping works
curl http://localhost:9090/api/v1/query?query=up

# Expected output: Both targets should show "up"
# {"instance":"192.168.1.10:9100","job":"laravel-postgresql"} value: 1
# {"instance":"192.168.1.11:9100","job":"laravel-mariadb"} value: 1
```

---

## 🔍 Troubleshooting

### Targets down in Prometheus

```bash
# Test connection from VM C to VM A/B
curl http://192.168.1.10:9100/metrics
curl http://192.168.1.11:9100/metrics

# Check firewall on VM A/B
# Should allow 9100 from 192.168.1.12
```

### Grafana not showing data

```bash
# Check Prometheus data source in Grafana
# Settings → Data Sources → Prometheus → Test

# Verify metrics are available
curl http://localhost:9090/api/v1/query?query=node_memory_MemTotal_bytes
```

---

## 📚 Resource Consumption (VM C)

```
Component          | Memory Usage | % of 2GB
-----------------------------------------------
Prometheus         | 200-400 MB   | 10-20%
Grafana            | 150-250 MB   | 7-12%
Nginx              | 20-50 MB     | 1-2%
System/OS          | 200-300 MB   | 10-15%
-----------------------------------------------
TOTAL USED         | ~600-1000MB  | 30-50%
FREE/BUFFER        | 1000-1400MB  | 50-70%
```

---

## 📊 Using the Dashboard During Benchmarks

### Dashboard Layout

The imported dashboard (`grafana-dashboard-laravel-benchmark.json`) has 8 panels organized as follows:

```
Row 1: CPU Metrics
├─ CPU Usage (%)              [Left]  - Shows % utilization
└─ System Load Average        [Right] - Shows 1m and 5m load

Row 2: Memory Metrics
├─ Memory Usage (RAM %)       [Left]  - Shows RAM consumption
└─ Swap Usage (%)             [Right] - Shows swap usage

Row 3: Disk I/O Metrics
├─ Disk I/O Throughput        [Left]  - Shows read/write bytes/sec
└─ Disk IOPS                  [Right] - Shows read/write operations/sec

Row 4: Network Metrics
├─ Network I/O Throughput     [Left]  - Shows receive/transmit bytes/sec
└─ Network Packets            [Right] - Shows packets/sec
```

### Color Coding

- **Blue**: PostgreSQL VM (192.168.1.10)
- **Orange**: MariaDB VM (192.168.1.11)
- **Light Blue**: PostgreSQL read/receive operations
- **Light Orange**: MariaDB read/receive operations

### During Load Testing

1. **Before starting**: Note baseline metrics (CPU idle, memory usage)
2. **During test**: Watch for:
   - CPU spikes (which DB handles load better?)
   - Memory growth (does swap get used?)
   - Disk I/O patterns (which DB writes more?)
   - Network throughput (API response patterns)
3. **After test**: Compare recovery time (how fast do metrics return to baseline?)

### Key Metrics to Compare

| Metric | What to Look For | Better is... |
|--------|------------------|--------------|
| CPU Usage | Lower average during load | Lower |
| Memory Usage | Stable, not growing | Lower/Stable |
| Swap Usage | Should stay at 0% or low | Lower |
| Disk Write | Which DB writes more for same operations | Lower (unless caching) |
| Disk Read | Read patterns during benchmark | Depends on workload |
| Network | Should be similar (same API traffic) | Similar |

### Tips

- Set time range to **Last 15 minutes** during short tests
- Use **Last 1 hour** for longer benchmark sessions
- Enable **Auto-refresh: 10s** to see real-time changes
- Click on legend items to hide/show specific metrics
- Use zoom (click and drag) to focus on specific time periods

---

## 🎯 Next Steps

1. ✅ Monitoring VM set up?
2. 🔍 Verify targets are up in Prometheus
3. 📈 Import the Laravel Benchmark dashboard in Grafana
4. 🚀 Ready to benchmark? Run load tests and watch metrics!

---

## 📁 Files Reference

- **Dashboard JSON**: `grafana-dashboard-laravel-benchmark.json`
- **Prometheus Config**: `/etc/prometheus/prometheus.yml`
- **Grafana URL**: `http://192.168.1.12:3000`
- **Prometheus URL**: `http://192.168.1.12:9090`

---

Your monitoring stack is ready! 📊

