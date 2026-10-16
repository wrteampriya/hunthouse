<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DeployToFtp extends Command
{
    protected $signature = 'deploy:ftp
        {commit? : Starting commit — all changes from this commit through HEAD will be deployed (default: HEAD, i.e. just the last commit)}
        {--profile= : Profile key from .vscode/sftp.json — omit to be prompted}
        {--dry : List files that would upload, without uploading}';

    protected $description = 'Upload files changed in a git commit to an FTP server using .vscode/sftp.json profile';

    public function handle(): int
    {
        $commit = $this->argument('commit') ?: 'HEAD';

        $configPath = base_path('.vscode/sftp.json');
        if (! file_exists($configPath)) {
            $this->error(".vscode/sftp.json not found at {$configPath}");
            return self::FAILURE;
        }

        $config = json_decode(file_get_contents($configPath), true);
        $profileKeys = array_keys($config['profiles'] ?? []);
        if (empty($profileKeys)) {
            $this->error('No profiles defined in sftp.json');
            return self::FAILURE;
        }

        $profileKey = $this->option('profile');
        if (! $profileKey) {
            $default = $config['defaultProfile'] ?? $profileKeys[0];
            $profileKey = $this->choice('Which profile do you want to deploy to?', $profileKeys, $default);
        }

        $profile = $config['profiles'][$profileKey] ?? null;
        if (! $profile) {
            $this->error("Profile '{$profileKey}' not found in sftp.json. Available: " . implode(', ', $profileKeys));
            return self::FAILURE;
        }

        $files = $this->changedFiles($commit);
        if (empty($files)) {
            $this->info('No changed files found for commit ' . $commit);
            return self::SUCCESS;
        }

        $this->info('Profile: ' . $profile['name'] . ' (' . $profile['host'] . ')');
        $this->info('Files (' . count($files) . '):');
        foreach ($files as $f) {
            $this->line('  ' . $f);
        }

        if ($this->option('dry')) {
            return self::SUCCESS;
        }

        if (! $this->confirm('Upload these files to ' . $profile['name'] . '?', true)) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        return $this->upload($profile, $files);
    }

    private function changedFiles(string $commit): array
    {
        // Diff from the commit's parent through HEAD, so all commits from
        // $commit up to and including HEAD are covered, not just $commit itself.
        $process = new Process(['git', 'diff', '--name-only', $commit . '^', 'HEAD']);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput());
            return [];
        }

        $files = array_filter(explode("\n", trim($process->getOutput())));

        // drop deleted files and anything under ignored patterns
        return array_values(array_filter($files, function ($file) {
            $full = base_path($file);
            if (! is_file($full)) {
                return false;
            }
            if (str_contains($file, '.env')) {
                return false;
            }
            return true;
        }));
    }

    private function upload(array $profile, array $files): int
    {
        $host = $profile['host'];
        $username = $profile['username'];
        $password = $profile['password'];
        $remoteRoot = rtrim($profile['remotePath'] ?? '/', '/');

        $conn = ftp_connect($host);
        if (! $conn) {
            $this->error("Could not connect to {$host}");
            return self::FAILURE;
        }

        if (! @ftp_login($conn, $username, $password)) {
            $this->error('FTP login failed');
            ftp_close($conn);
            return self::FAILURE;
        }

        ftp_pasv($conn, true);

        $failed = [];
        foreach ($files as $file) {
            $localPath = base_path($file);
            $remotePath = $remoteRoot . '/' . $file;

            $this->ensureRemoteDir($conn, dirname($remotePath));

            if (ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
                $this->line("<fg=green>uploaded</> {$file}");
            } else {
                $this->line("<fg=red>failed</> {$file}");
                $failed[] = $file;
            }
        }

        ftp_close($conn);

        if (! empty($failed)) {
            $this->error(count($failed) . ' file(s) failed to upload.');
            return self::FAILURE;
        }

        $this->info('Deploy complete.');
        return self::SUCCESS;
    }

    private function ensureRemoteDir($conn, string $dir): void
    {
        if ($dir === '.' || $dir === '/' || $dir === '') {
            return;
        }

        $parts = explode('/', trim($dir, '/'));
        $path = '';
        foreach ($parts as $part) {
            $path .= '/' . $part;
            if (@ftp_chdir($conn, $path)) {
                continue;
            }
            @ftp_mkdir($conn, $path);
        }
        ftp_chdir($conn, '/');
    }
}