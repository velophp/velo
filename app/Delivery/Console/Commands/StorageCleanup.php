<?php

namespace App\Delivery\Console\Commands;

use Illuminate\Console\Command;

class StorageCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:storage-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up files in public and private storage directories';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting storage cleanup...');

        $publicPath = storage_path('app/public');
        $privatePath = storage_path('app/private');
        $glideCache = storage_path('app/glide_cache');

        $totalDeleted = 0;

        // Clean public storage
        if (is_dir($publicPath)) {
            $publicDeleted = $this->cleanDirectory($publicPath);
            $totalDeleted += $publicDeleted;
            $this->line("Cleaned {$publicDeleted} files from public storage");
        } else {
            $this->warn('Public storage directory does not exist');
        }

        // Clean private storage
        if (is_dir($privatePath)) {
            $privateDeleted = $this->cleanDirectory($privatePath);
            $totalDeleted += $privateDeleted;
            $this->line("Cleaned {$privateDeleted} files from private storage");
        } else {
            $this->warn('Private storage directory does not exist');
        }

        // Clean glide storage
        if (is_dir($glideCache)) {
            $glideDeleted = $this->cleanDirectory($glideCache);
            $totalDeleted += $glideDeleted;
            $this->line("Cleaned {$glideDeleted} files from glide cache storage");
        } else {
            $this->warn('Glide storage directory does not exist');
        }

        $this->info("Total files deleted: {$totalDeleted}");

        return Command::SUCCESS;
    }

    /**
     * Clean all files in a directory recursively, keeping .gitignore
     *
     * @return int Number of files deleted
     */
    protected function cleanDirectory(string $path): int
    {
        $deleted = 0;
        $items = glob($path . '/{,.}*', GLOB_BRACE);

        foreach ($items as $item) {
            $basename = basename($item);

            // Skip . and .. and .gitignore
            if ($basename === '.' || $basename === '..' || $basename === '.gitignore') {
                continue;
            }

            if (is_dir($item)) {
                // Recursively clean subdirectories
                $deleted += $this->cleanDirectory($item);
                // Remove empty directory
                @rmdir($item);
            } else {
                // Delete file
                if (unlink($item)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
