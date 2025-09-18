<?php

namespace App\Services\Mods;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use Exception;

class ModDownloadService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 60,
        ]);
    }

    /**
     * Download a mod from Modrinth to the server
     */
    public function downloadMod(Server $server, string $downloadUrl, string $filename, string $directory = 'mods'): void
    {
        try {
            // Ensure the mods directory exists
            $this->ensureDirectoryExists($server, $directory);

            // Use the daemon's pull method to download the file
            $fileRepository = new DaemonFileRepository();
            $fileRepository->setServer($server);

            $fileRepository->pull($downloadUrl, $directory, [
                'filename' => $filename,
                'foreground' => true,
            ]);

        } catch (Exception $e) {
            throw new Exception('Failed to download mod: ' . $e->getMessage());
        }
    }

    /**
     * Download multiple mods
     */
    public function downloadMods(Server $server, array $mods, string $directory = 'mods'): void
    {
        foreach ($mods as $mod) {
            $this->downloadMod($server, $mod['url'], $mod['filename'], $directory);
        }
    }

    /**
     * Ensure the mods directory exists on the server
     */
    private function ensureDirectoryExists(Server $server, string $directory): void
    {
        $fileRepository = new DaemonFileRepository();
        $fileRepository->setServer($server);

        try {
            // Try to list the directory to see if it exists
            $fileRepository->getDirectory($directory);
        } catch (Exception $e) {
            // Directory doesn't exist, create it
            $fileRepository->createDirectory($directory, '/');
        }
    }

    /**
     * Get the appropriate mods directory based on server type
     */
    public function getModsDirectory(Server $server): string
    {
        // For Minecraft Forge servers, mods go in 'mods'
        // For Fabric, also 'mods'
        // This could be expanded based on egg type
        return 'mods';
    }
}