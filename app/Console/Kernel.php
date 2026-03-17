<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * All Console Commands — must be registered here.
     */
    protected $commands = [
        Commands\MarkOverdueLoans::class,
        Commands\SendDueReminders::class,
        Commands\SendOverdueWarnings::class,
        Commands\CleanExpiredPaymentLinks::class,
        Commands\SendPushNotifications::class,
    ];

    /**
     * Scheduled tasks.
     *
     * ──────────────────────────────────────────────────────────────────────────
     * IMPORTANT — SHARED HOSTING / CPANEL SETUP:
     *
     * Add ONE cron entry in cPanel:
     *   * * * * * /usr/local/bin/php /home/YOUR_USER/public_html/artisan schedule:run >> /dev/null 2>&1
     *
     * Replace /home/YOUR_USER/public_html/ with your actual document root path.
     * All tasks below will run automatically via that single cron.
     * ──────────────────────────────────────────────────────────────────────────
     */
    protected function schedule(Schedule $schedule): void
    {
        // Mark overdue loans and accrue penalties — every day at 1 AM Ghana time
        $schedule->command('loans:mark-overdue')
            ->dailyAt('01:00')
            ->timezone('Africa/Accra')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/overdue.log'));

        // Send payment due reminders — every day at 8 AM
        $schedule->command('loans:send-reminders')
            ->dailyAt('08:00')
            ->timezone('Africa/Accra')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/reminders.log'));

        // Send overdue warnings — every day at 9 AM
        $schedule->command('loans:send-overdue-warnings')
            ->dailyAt('09:00')
            ->timezone('Africa/Accra')
            ->withoutOverlapping();

        // Clean expired payment links — every hour
        $schedule->command('paystack:clean-expired-links')
            ->hourly();

        // Send push notifications for due reminders — daily at 8:30 AM
        $schedule->command('push:send-reminders --type=due_reminders')
            ->dailyAt('08:30')
            ->timezone('Africa/Accra')
            ->withoutOverlapping();

        // Send push notifications for overdue warnings — daily at 9:30 AM
        $schedule->command('push:send-reminders --type=overdue_warnings')
            ->dailyAt('09:30')
            ->timezone('Africa/Accra')
            ->withoutOverlapping();

        // Clear old activity logs (keep 6 months)
        $schedule->command('activitylog:clean --days=180')
            ->monthly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
