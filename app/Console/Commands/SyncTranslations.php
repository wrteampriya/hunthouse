<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncTranslations extends Command
{
    protected $signature = 'app:sync-translations
                            {--dry-run : Preview changes without modifying the lang file}';

    protected $description = 'Sync translation keys from all project files into en.json';

    /**
     * Directories to scan
     */
    protected array $scanTargets = [
        'views'     => ['path' => 'resources/views',        'blade_only' => true,  'ext' => 'php'],
        'app'       => ['path' => 'app',                    'blade_only' => false, 'ext' => 'php'],
        'routes'    => ['path' => 'routes',                 'blade_only' => false, 'ext' => 'php'],
        'config'    => ['path' => 'config',                 'blade_only' => false, 'ext' => 'php'],
        'custom_js' => ['path' => 'public/assets/js/custom', 'blade_only' => false, 'ext' => 'js'],
    ];

    /**
     * Keys that should never be removed during sync.
     * These are used dynamically at runtime (via variables) and can't be detected by static code scanning.
     */
    protected array $protectedKeys = [
        'Succeed',
        'Failed',
        'Pending',
        'Under Review',
        'Rejected',
        'Payment Transaction'
    ];

    /**
     * Directories/files to ignore inside app/
     */
    protected array $ignorePaths = [
        'app/Console/Commands/SyncTranslations.php', // ignore self
        'vendor',
        'node_modules',
        'storage',
        'bootstrap',
        '.git',
    ];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN — no changes will be written.');
            $this->newLine();
        }

        $keys      = [];
        $totalFiles = 0;

        foreach ($this->scanTargets as $label => $target) {
            $basePath = base_path($target['path']);

            if (!is_dir($basePath)) {
                $this->line("  <fg=yellow>⚠ Skipping [{$label}] — not found: {$basePath}</>");
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            $fileCount = 0;

            $ext = $target['ext'] ?? 'php';

            foreach ($files as $file) {
                // Skip directories
                if ($file->isDir()) continue;

                // Skip files that don't match the target extension
                if ($file->getExtension() !== $ext) continue;

                // Skip blade-only targets that aren't blade files
                if ($target['blade_only'] && !str_contains($file->getFilename(), '.blade.')) continue;

                // Skip ignored paths
                $relativePath = str_replace(base_path() . '/', '', $file->getPathname());
                if ($this->isIgnored($relativePath)) continue;

                $content = file_get_contents($file->getPathname());
                $found   = $ext === 'js' ? $this->extractJsKeys($content) : $this->extractKeys($content);
                $keys    = array_merge($keys, $found);
                $fileCount++;
            }

            $totalFiles += $fileCount;
            $this->line("  <fg=cyan>✔ [{$label}]</> {$fileCount} files scanned.");
        }

        $keys = array_unique($keys);

        $this->newLine();
        $this->info("Total files scanned : {$totalFiles}");
        $this->info('Total unique keys   : ' . count($keys));
        $this->newLine();

        $langFile = lang_path('en.json');

        $existing = file_exists($langFile)
            ? json_decode(file_get_contents($langFile), true) ?? []
            : [];

        // Keys in code but missing from lang file
        $missing = array_values(array_diff($keys, array_keys($existing)));

        // Keys in lang file but not found anywhere in code (excluding protected keys)
        $unused = array_values(array_diff(array_keys($existing), $keys));
        $unused = array_values(array_diff($unused, $this->protectedKeys));

        // ── Report: Added ──────────────────────────────
        $this->info(count($missing) . ' keys to be added:');
        if (count($missing) > 0) {
            foreach ($missing as $key) {
                $this->line("  <fg=green>+</> {$key}");
            }
        } else {
            $this->line('  Nothing new to add.');
        }

        $this->newLine();

        // ── Report: Removed ────────────────────────────
        $this->info(count($unused) . ' unused keys to be removed:');
        if (count($unused) > 0) {
            foreach ($unused as $key) {
                $this->line("  <fg=red>-</> {$key}");
            }
        } else {
            $this->line('  No unused keys found.');
        }

        $this->newLine();

        // ── Write changes (unless dry-run) ─────────────
        if (!$isDryRun) {
            foreach ($missing as $key) {
                $existing[$key] = $key;
            }

            foreach ($unused as $key) {
                unset($existing[$key]);
            }

            ksort($existing);

            file_put_contents(
                $langFile,
                json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $this->newLine();
            $this->info('✔ en.json updated successfully.');
        } else {
            $this->warn('🔍 Dry run complete — en.json was NOT modified.');
        }
    }

    /**
     * Check if a file path should be ignored
     */
    protected function isIgnored(string $relativePath): bool
    {
        foreach ($this->ignorePaths as $ignore) {
            if (str_starts_with($relativePath, $ignore)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract all translation keys from file content
     */
    protected function extractKeys(string $content): array
    {
        $keys = [];

        // __('key') / __("key")
        preg_match_all("/__\(\s*['\"]([^'\"]+)['\"]/", $content, $m1);

        // trans('key') / trans("key")
        preg_match_all("/trans\(\s*['\"]([^'\"]+)['\"]/", $content, $m2);

        // @lang('key') / @lang("key")
        preg_match_all("/@lang\(\s*['\"]([^'\"]+)['\"]/", $content, $m3);

        // trans_choice('key', n) / trans_choice("key", n)
        preg_match_all("/trans_choice\(\s*['\"]([^'\"]+)['\"]/", $content, $m4);

        return array_merge($keys, $m1[1], $m2[1], $m3[1], $m4[1]);
    }

    /**
     * Extract translation keys from JS file content (window?.languageLabels references)
     */
    protected function extractJsKeys(string $content): array
    {
        $keys = [];

        // window?.languageLabels?.["Key"] or window?.languageLabels?.['Key']
        preg_match_all('/window\?\.\s*languageLabels\?\.\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]/', $content, $m1);

        // window?.languageLabels["Key"] or window?.languageLabels['Key']
        preg_match_all('/window\?\.\s*languageLabels\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]/', $content, $m2);

        return array_merge($keys, $m1[1], $m2[1]);
    }
}