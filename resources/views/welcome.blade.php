<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Benchmark - {{ ucfirst($driver) }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 60px;
            max-width: 800px;
            width: 100%;
        }
        
        h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            color: #666;
            font-size: 1.2rem;
            margin-bottom: 40px;
        }
        
        .db-info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .db-driver {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .db-driver.pgsql {
            color: #336791;
        }
        
        .db-driver.mysql {
            color: #00758F;
        }
        
        .db-version {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .endpoints {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }
        
        .endpoint {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            font-family: 'Courier New', monospace;
        }
        
        .method {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
            margin-right: 15px;
            font-size: 0.85rem;
        }
        
        .method.get {
            background: #52c41a;
        }
        
        .method.post {
            background: #1890ff;
        }
        
        .path {
            color: #333;
            font-size: 0.95rem;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #999;
            font-size: 0.85rem;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Laravel Benchmark</h1>
        <p class="subtitle">PostgreSQL vs MariaDB Performance Testing</p>
        
        <div class="db-info">
            <div class="db-driver {{ $driver }}">
                {{ $driver === 'pgsql' ? '🐘 PostgreSQL' : '🐬 MariaDB/MySQL' }}
            </div>
            <div class="db-version">
                Version: {{ $version }}<br>
                Connection: <code>{{ $connection }}</code>
            </div>
        </div>
        
        @if($stats)
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value">{{ number_format($stats['users']) }}</div>
                <div class="stat-label">Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ number_format($stats['products']) }}</div>
                <div class="stat-label">Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ number_format($stats['orders']) }}</div>
                <div class="stat-label">Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ number_format($stats['products_in_stock']) }}</div>
                <div class="stat-label">In Stock</div>
            </div>
        </div>
        @else
        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; color: #856404; margin-top: 20px;">
            ⚠️ Database not seeded. Run: <code>php artisan migrate:fresh --seed</code>
        </div>
        @endif
        
        <div class="endpoints">
            <h3 style="margin-bottom: 20px; color: #333;">📡 API Endpoints</h3>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/products</span>
                <span style="margin-left: auto;" class="badge badge-success">Read-Heavy</span>
                </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/orders</span>
                <span style="margin-left: auto;" class="badge badge-warning">Write-Heavy</span>
                </div>
        </div>

        <div class="footer">
            <p>🔬 Atomic Stock Decrement Strategy Active</p>
            <p style="margin-top: 5px;">Ready for load testing with Vegeta</p>
        </div>
    </div>
    </body>
</html>
