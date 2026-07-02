<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TheSportsDbService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.thesportsdb.base_url');
        $this->apiKey = config('services.thesportsdb.api_key');
    }

    private function url(string $endpoint): string
    {
        return "{$this->baseUrl}/{$this->apiKey}/{$endpoint}";
    }

    public function searchTeams(string $teamName): array
    {
        $response = Http::get($this->url('searchteams.php'), [
            't' => $teamName,
        ]);

        if ($response->failed()) {
            throw new \Exception('TheSportsDB request failed: ' . $response->body());
        }

        return $response->json();
    }

    public function searchPlayers(string $playerName): array
    {
        $response = Http::get($this->url('searchplayers.php'), [
            'p' => $playerName,
        ]);

        if ($response->failed()) {
            throw new \Exception('TheSportsDB request failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getTeamsByLeagueId(string $leagueId): array
    {
        $response = Http::get($this->url('search_all_teams.php'), [
            'id' => $leagueId,
        ]);

        if ($response->failed()) {
            throw new \Exception('TheSportsDB request failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getPlayersByTeamExternalId(string $externalTeamId): array
    {
        $response = Http::get($this->url('lookup_all_players.php'), [
            'id' => $externalTeamId,
        ]);

        if ($response->failed()) {
            throw new \Exception('TheSportsDB request failed: ' . $response->body());
        }

        return $response->json();
    }
}
