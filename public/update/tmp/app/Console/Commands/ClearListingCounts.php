<?php

namespace App\Console\Commands;

use App\Models\ProjectView;
use App\Models\PropertyView;
use Illuminate\Console\Command;

class ClearListingCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-listing-counts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Clear Project View Counts for last 3 months
        $last3Months = now()->subMonths(3);
        ProjectView::where('date', '<', $last3Months)->delete();

        // Clear Property View Counts for last 3 months
        $last3Months = now()->subMonths(3);
        PropertyView::where('date', '<', $last3Months)->delete();
    }
}
