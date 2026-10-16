<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class OptimizeImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes for optimization
    public $tries = 1; // Only try once

    public function __construct(public string $imagePath)
    {
    }

    public function handle(): void
    {
        try {
            if (file_exists($this->imagePath)) {
                OptimizerChainFactory::create()->optimize($this->imagePath);
            }
        } catch (Exception $e) {
            Log::warning('Image optimization job failed', [
                'path' => $this->imagePath,
                'error' => $e->getMessage()
            ]);
        }
    }
}
