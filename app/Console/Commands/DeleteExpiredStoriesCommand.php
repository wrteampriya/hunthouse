<?php

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DeleteExpiredStoriesCommand extends Command
{
    protected $signature   = 'stories:delete-expired';
    protected $description = 'Hard-delete stories whose 24-hour window has passed and remove their media files';

    public function handle(): void
    {
        $stories = Story::where('expires_at', '<=', now())->get();

        $disk         = 'public';
        $deleted      = 0;
        $renewed      = 0;
        $demoAgentIds = array_filter(array_map('intval', explode(',', (string) env('DEMO_AGENT_IDS', ''))));

        foreach ($stories as $story) {
            // On demo servers, admin stories (agent_id = 0) and configured demo agents' stories are renewed instead of deleted
            if (($story->agent_id === 0 || in_array((int) $story->agent_id, $demoAgentIds, true)) && env('DEMO_MODE', false)) {
                $originalTime = Carbon::parse($story->created_at);
                $newCreatedAt = now()->setTime($originalTime->hour, $originalTime->minute, $originalTime->second);
                // If the original time hasn't occurred yet today, keep it as today's future time
                $story->created_at = $newCreatedAt;
                $story->expires_at = $newCreatedAt->copy()->addHours(24);
                $story->save();
                $renewed++;
                continue;
            }

            // Remove media file
            if ($story->media_type === 'image') {
                $path = 'stories/images/' . basename($story->media_url);
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } else {
                $videoPath = 'stories/videos/' . basename($story->media_url);
                if (Storage::disk($disk)->exists($videoPath)) {
                    Storage::disk($disk)->delete($videoPath);
                }
                if ($story->thumbnail_url) {
                    $thumbPath = 'stories/thumbnails/' . basename($story->thumbnail_url);
                    if (Storage::disk($disk)->exists($thumbPath)) {
                        Storage::disk($disk)->delete($thumbPath);
                    }
                }
            }

            $story->delete();
            $deleted++;
        }

        $this->info("Deleted {$deleted} expired stories. Renewed {$renewed} demo stories.");
    }
}
