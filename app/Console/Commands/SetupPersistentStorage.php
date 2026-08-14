<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class SetupPersistentStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:persistent {target_path? : Custom persistent storage root directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup persistent storage outside the Git web root to prevent Hostinger Auto-Deploy from wiping uploaded files.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $files = new Filesystem();

        $customPath = $this->argument('target_path');
        if ($customPath) {
            $persistentRoot = rtrim($customPath, '/\\');
        } else {
            // Default: One level above base_path (e.g. /home/username/domains/domain.com/arms_storage)
            $persistentRoot = dirname(base_path()) . DIRECTORY_SEPARATOR . 'arms_storage';
        }

        $this->info("Persistent storage target directory: {$persistentRoot}");

        $persistentPublic  = $persistentRoot . DIRECTORY_SEPARATOR . 'public';
        $persistentPrivate = $persistentRoot . DIRECTORY_SEPARATOR . 'private';

        // Ensure directories exist outside repository
        if (!$files->exists($persistentPublic)) {
            $files->makeDirectory($persistentPublic, 0755, true);
            $this->info("Created directory: {$persistentPublic}");
        }
        if (!$files->exists($persistentPrivate)) {
            $files->makeDirectory($persistentPrivate, 0755, true);
            $this->info("Created directory: {$persistentPrivate}");
        }

        $appPublicPath  = storage_path('app' . DIRECTORY_SEPARATOR . 'public');
        $appPrivatePath = storage_path('app' . DIRECTORY_SEPARATOR . 'private');

        // Migrate any legacy files inside storage/app/public or storage/app/private to persistent storage
        if ($files->exists($appPublicPath) && is_dir($appPublicPath) && !is_link($appPublicPath)) {
            $files->copyDirectory($appPublicPath, $persistentPublic);
            $this->info("Migrated legacy storage/app/public files to persistent storage.");
        }
        if ($files->exists($appPrivatePath) && is_dir($appPrivatePath) && !is_link($appPrivatePath)) {
            $files->copyDirectory($appPrivatePath, $persistentPrivate);
            $this->info("Migrated legacy storage/app/private files to persistent storage.");
        }

        // Ensure public/storage symlink points to persistent public folder directly
        $publicStorageSymlink = public_path('storage');
        $this->removeSymlinkOrLink($files, $publicStorageSymlink);

        try {
            $files->link($persistentPublic, $publicStorageSymlink);
            $this->info("Linked {$publicStorageSymlink} -> {$persistentPublic}");
        } catch (\Throwable $e) {
            $this->warn("Could not create public/storage link: " . $e->getMessage());
        }

        $this->info("\nPersistent Storage Setup Completed Successfully!");
        $this->info("Uploaded files are now stored safely in {$persistentRoot} outside the Git root.");
        $this->info("Hostinger Git Auto-Deploy will no longer wipe uploaded files during deployment!");

        return Command::SUCCESS;
    }

    private function processStorageLink(Filesystem $files, string $linkPath, string $targetPath, string $name): void
    {
        if ($this->isSymlinkOrJunction($linkPath)) {
            $currentTarget = @readlink($linkPath) ?: $linkPath;
            $this->info("storage/app/{$name} is already a symlink -> {$currentTarget}");
            return;
        }

        if ($files->exists($linkPath) && is_dir($linkPath)) {
            $this->info("Migrating existing files from storage/app/{$name} to persistent folder...");
            $files->copyDirectory($linkPath, $targetPath);
            $files->deleteDirectory($linkPath);
            $this->info("Migrated storage/app/{$name} contents.");
        }

        $this->removeSymlinkOrLink($files, $linkPath);

        try {
            $files->link($targetPath, $linkPath);
            $this->info("Created symlink: storage/app/{$name} -> {$targetPath}");
        } catch (\Throwable $e) {
            $this->error("Failed to symlink storage/app/{$name}: " . $e->getMessage());
        }
    }

    private function isSymlinkOrJunction(string $path): bool
    {
        if (is_link($path)) {
            return true;
        }
        if (file_exists($path) && @readlink($path) !== false) {
            return true;
        }
        return false;
    }

    private function removeSymlinkOrLink(Filesystem $files, string $path): void
    {
        if ($this->isSymlinkOrJunction($path)) {
            @rmdir($path);
            @unlink($path);
        } elseif ($files->exists($path)) {
            if (is_dir($path)) {
                $files->deleteDirectory($path);
            } else {
                $files->delete($path);
            }
        }
    }
}
