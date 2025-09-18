<?php

namespace App\Services\Mods;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;

class ModrinthApiService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.modrinth.com/v2/',
            'timeout' => 30,
        ]);
    }

    /**
     * Search for mods on Modrinth
     */
    public function searchMods(string $query, array $options = []): Collection
    {
        $params = [
            'query' => $query,
            'limit' => $options['limit'] ?? 20,
            'offset' => $options['offset'] ?? 0,
            'facets' => $options['facets'] ?? '[["categories:minecraft"]]',
        ];

        if (isset($options['versions'])) {
            $params['facets'] = '[["versions:' . implode('","versions:', $options['versions']) . '"]]';
        }

        try {
            $response = $this->client->get('search', [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody(), true);

            return collect($data['hits'] ?? []);
        } catch (RequestException $e) {
            throw new \Exception('Failed to search mods: ' . $e->getMessage());
        }
    }

    /**
     * Get mod details
     */
    public function getMod(string $modId): array
    {
        try {
            $response = $this->client->get("project/{$modId}");

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get mod details: ' . $e->getMessage());
        }
    }

    /**
     * Get mod versions
     */
    public function getModVersions(string $modId, array $options = []): Collection
    {
        $params = [];

        if (isset($options['loaders'])) {
            $params['loaders'] = json_encode($options['loaders']);
        }

        if (isset($options['game_versions'])) {
            $params['game_versions'] = json_encode($options['game_versions']);
        }

        try {
            $response = $this->client->get("project/{$modId}/version", [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody(), true);

            return collect($data);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get mod versions: ' . $e->getMessage());
        }
    }

    /**
     * Get download URL for a specific version
     */
    public function getDownloadUrl(string $versionId): string
    {
        try {
            $response = $this->client->get("version/{$versionId}");

            $data = json_decode($response->getBody(), true);

            return $data['files'][0]['url'] ?? '';
        } catch (RequestException $e) {
            throw new \Exception('Failed to get download URL: ' . $e->getMessage());
        }
    }
}