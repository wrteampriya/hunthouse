<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;

class MakePendingTransactionsFailed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-pending-transactions-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make pending transactions failed after some time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Payments pending last 1 day
        try {
            $count = PaymentTransaction::where('payment_status', 'pending')->where('created_at', '<', now()->subDay())->update(['payment_status' => 'failed', 'reject_reason' => 'Transaction Failed Automatically']);
            $this->info("{$count} transactions have been updated to failed.");
            if($count){
                Log::info("{$count} transactions have been updated to failed.");
            }
        } catch (Exception $e) {
            Log::error('Make Pending Transactions Failed Command: ' . $e->getMessage());
        }
    }
}
