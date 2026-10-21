<?php

namespace App\Console\Commands;

use App\Models\VerifyCustomer;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptionVerifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-subscription-verifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set users unverified when their subscription package expires';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $count = VerifyCustomer::where('status', 'approved')
                ->where('source', 'subscription')
                // User has no active package anymore (same logic as UserPackage::onlyActive)
                ->whereNotExists(function ($query) {
                    
                    $query->select('id')
                        ->from('user_packages')
                        ->whereColumn('user_packages.user_id', 'verify_customers.user_id')
                        ->where(function ($q) {
                            $q->whereDate('end_date', '>', now())->orWhereNull('end_date');
                        });
                })
                ->update(['status' => 'pending']);

            $this->info("{$count} subscription verifications have been set to unverified.");
            if ($count) {
                Log::info("{$count} subscription verifications have been set to unverified.");
            }
        } catch (Exception $e) {
            Log::error('Expire Subscription Verifications Command: '.$e->getMessage());
            $this->error('Failed to expire subscription verifications: '.$e->getMessage());
        }
    }
}
