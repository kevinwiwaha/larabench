<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Optional: Set isolation level at runtime for PostgreSQL
        // This complements the config/database.php settings
        
        DB::listen(function ($query) {
            // You can add query logging here if needed for debugging
            // Log::debug('Query', ['sql' => $query->sql, 'time' => $query->time]);
        });

        // Set PostgreSQL isolation level explicitly (optional, as it's already default)
        if (config('database.default') === 'pgsql') {
            try {
                DB::statement("SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ COMMITTED");
            } catch (\Exception $e) {
                // Silently fail if already set or connection not ready
                logger()->debug('Could not set PostgreSQL isolation level: ' . $e->getMessage());
            }
        }

        // Alternative: Set per-transaction isolation level
        // This can be useful for testing different isolation levels
        $this->registerTransactionHelpers();
    }

    /**
     * Register helper methods for transaction isolation control
     */
    private function registerTransactionHelpers(): void
    {
        // These macros allow you to set isolation level per transaction
        
        DB::macro('transactionWithIsolation', function (string $level, callable $callback) {
            $driver = DB::getDriverName();
            
            // Start transaction with specific isolation level
            if ($driver === 'pgsql') {
                DB::statement("BEGIN TRANSACTION ISOLATION LEVEL $level");
            } elseif (in_array($driver, ['mysql', 'mariadb'])) {
                $mysqlLevel = str_replace(' ', '-', $level);
                DB::statement("SET TRANSACTION ISOLATION LEVEL $mysqlLevel");
                DB::beginTransaction();
            }

            try {
                $result = $callback();
                DB::commit();
                return $result;
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        });

        // Macro for common isolation levels
        DB::macro('transactionReadCommitted', function (callable $callback) {
            return DB::transactionWithIsolation('READ COMMITTED', $callback);
        });

        DB::macro('transactionRepeatableRead', function (callable $callback) {
            return DB::transactionWithIsolation('REPEATABLE READ', $callback);
        });

        DB::macro('transactionSerializable', function (callable $callback) {
            return DB::transactionWithIsolation('SERIALIZABLE', $callback);
        });
    }
}

