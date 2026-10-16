<?php

namespace App\Console\Commands;

use App\Models\Projects;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireListing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-listings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire properties and projects that have passed their expiration date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // Expire Properties
        $expiredProperties = Property::where('status', 1)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $now)
            ->update(['status' => 0]);

        if ($expiredProperties > 0) {
            $this->info("Expired {$expiredProperties} properties.");
        }

        // Expire Projects
        $expiredProjects = Projects::where('status', 1)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $now)
            ->update(['status' => 0]);

        if ($expiredProjects > 0) {
            $this->info("Expired {$expiredProjects} projects.");
        }

        if ($expiredProperties == 0 && $expiredProjects == 0) {
            $this->info('No listings expired.');
        }
    }
}
