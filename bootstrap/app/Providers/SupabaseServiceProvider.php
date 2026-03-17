<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupabaseServiceProvider extends ServiceProvider
{
    /**
     * Register Supabase-specific bindings.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap Supabase-specific configuration.
     *
     * When running on Supabase PostgreSQL, we configure:
     *  1. Session mode for PgBouncer compatibility
     *  2. Search path to public schema
     *  3. Timezone to Africa/Accra
     */
    public function boot(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        // Only configure on actual DB connection attempts, not during builds
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        DB::listen(function ($query) {
            // Optionally log slow queries in production for Supabase monitoring
            if (config('app.debug') && $query->time > 1000) {
                Log::warning('Slow query detected', [
                    'sql'      => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms'  => $query->time,
                ]);
            }
        });

        // Set session-level settings for Supabase connection
        try {
            DB::statement("SET TIME ZONE 'UTC'");
            DB::statement("SET search_path TO public");
        } catch (\Exception $e) {
            // Don't crash the app if DB isn't available during build
            Log::warning('Supabase session config failed: ' . $e->getMessage());
        }
    }
}
