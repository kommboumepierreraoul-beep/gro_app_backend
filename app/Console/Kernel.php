<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\WalletTransaction;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Toutes les 10 minutes, les transactions pending > 30 min passent en failed
        $schedule->call(function () {
            WalletTransaction::where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))
                ->update(['status' => 'failed']);
        })->everyTenMinutes();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
