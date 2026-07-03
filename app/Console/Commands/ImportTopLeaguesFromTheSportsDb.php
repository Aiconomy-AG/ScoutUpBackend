<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\Team;
use App\Support\FootballPositions;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportTopLeaguesFromTheSportsDb extends Command
{
    protected $signature = 'football:import-top-leagues
                            {--league=* : Optional league keys: premier-league, la-liga, serie-a, bundesliga, ligue-1}
                            {--teams-only : Import only teams, without players}
                            {--delay=4000 : Delay in milliseconds between team player requests}
                            {--max-teams= : Optional maximum number of teams to import per league}';

    protected $description = 'Import teams and players from top European leagues using TheSportsDB.';

    private array $leagues = [
        'premier-league' => [
            'id' => '4328',
            'name' => 'English Premier League',
            'api_league_name' => 'English_Premier_League',
        ],
        'la-liga' => [
            'id' => '4335',
            'name' => 'Spanish La Liga',
            'api_league_name' => 'Spanish_La_Liga',
        ],
        'serie-a' => [
            'id' => '4332',
            'name' => 'Italian Serie A',
            'api_league_name' => 'Italian_Serie_A',
        ],
        'bundesliga' => [
            'id' => '4331',
            'name' => 'German Bundesliga',
            'api_league_name' => 'German_Bundesliga',
        ],
        'ligue-1' => [
            'id' => '4334',
            'name' => 'French Ligue 1',
            'api_league_name' => 'French_Ligue_1',
        ],
    ];

    public function handle(): int
    {
        $selectedLeagueKeys = $this->option('league');

        $leaguesToImport = empty($selectedLeagueKeys)
            ? $this->leagues
            : array_intersect_key($this->leagues, array_flip($selectedLeagueKeys));

        if (empty($leaguesToImport)) {
            $this->error('No valid leagues selected.');

            $this->line('Available league keys:');

            foreach (array_keys($this->leagues) as $leagueKey) {
                $this->line('- ' . $leagueKey);
            }

            return self::FAILURE;
        }

        foreach ($leaguesToImport as $leagueKey => $leagueData) {
            $this->newLine();
            $this->info('Importing ' . $leagueData['name'] . '...');

            $externalTeams = $this->fetchTeamsByLeagueName($leagueData['api_league_name']);

            if (empty($externalTeams)) {
                $this->warn('No teams found for ' . $leagueData['name'] . '.');
                continue;
            }

            $externalTeams = $this->filterTeamsForLeague($externalTeams, $leagueData);

            $this->info('Valid teams found for ' . $leagueData['name'] . ': ' . count($externalTeams));

            $maxTeams = $this->option('max-teams');

            if ($maxTeams !== null) {
                $externalTeams = array_slice($externalTeams, 0, (int) $maxTeams);

                $this->warn('Limiting import to ' . count($externalTeams) . ' teams for this league.');
            }

            $delayMs = (int) $this->option('delay');

            foreach ($externalTeams as $externalTeam) {
                $team = $this->importTeam($externalTeam, $leagueData['name']);

                $this->line('Imported team: ' . $team->name . ' | ' . $leagueData['name']);

                if ($this->option('teams-only')) {
                    continue;
                }

                $externalTeamId = $externalTeam['idTeam'] ?? null;

                if (!$externalTeamId) {
                    $this->warn('Skipping players for ' . $team->name . ': missing external team id.');
                    continue;
                }

                $externalPlayers = $this->fetchPlayersByTeamId($externalTeamId);

                if (empty($externalPlayers)) {
                    $this->warn('No players found for ' . $team->name . '.');
                    continue;
                }

                $importedPlayersCount = 0;

                foreach ($externalPlayers as $externalPlayer) {
                    $player = $this->importPlayer($externalPlayer, $team);

                    if ($player) {
                        $importedPlayersCount++;
                    }
                }

                $this->line('Imported players for ' . $team->name . ': ' . $importedPlayersCount);

                if ($delayMs > 0) {
                    $this->line('Waiting ' . $delayMs . 'ms before next request...');
                    usleep($delayMs * 1000);
                }
            }
        }

        $this->newLine();
        $this->info('Top leagues import completed.');

        return self::SUCCESS;
    }

    private function fetchTeamsByLeagueName(string $apiLeagueName): array
    {
        $response = $this->safeGet($this->apiUrl('search_all_teams.php'), [
            'l' => $apiLeagueName,
        ]);

        if (!$response) {
            $this->warn('Could not fetch teams for league ' . $apiLeagueName . '.');

            return [];
        }

        return $response->json('teams') ?? [];
    }

    private function fetchPlayersByTeamId(string $teamId): array
    {
        $response = $this->safeGet($this->apiUrl('lookup_all_players.php'), [
            'id' => $teamId,
        ]);

        if (!$response) {
            $this->warn('Could not fetch players for team id ' . $teamId . '.');

            return [];
        }

        return $response->json('player') ?? [];
    }

    private function filterTeamsForLeague(array $externalTeams, array $leagueData): array
    {
        return array_values(array_filter($externalTeams, function (array $team) use ($leagueData) {
            $teamLeagueId = $team['idLeague'] ?? null;
            $teamLeagueName = $team['strLeague'] ?? null;

            if ($teamLeagueId && $teamLeagueId === $leagueData['id']) {
                return true;
            }

            if ($teamLeagueName && strtolower($teamLeagueName) === strtolower($leagueData['name'])) {
                return true;
            }

            return true;
        }));
    }

    private function importTeam(array $externalTeam, string $leagueName): Team
    {
        $teamName = $externalTeam['strTeam'] ?? null;

        if (!$teamName) {
            throw new \RuntimeException('External team is missing strTeam.');
        }

        $foundedYear = $externalTeam['intFormedYear'] ?? null;

        return Team::updateOrCreate(
            [
                'name' => $teamName,
            ],
            [
                'city' => $externalTeam['strLocation'] ?? null,
                'stadium' => $externalTeam['strStadium'] ?? null,
                'league' => $leagueName,
                'founded_year' => $foundedYear ? (int) $foundedYear : null,
            ],
        );
    }

    private function importPlayer(array $externalPlayer, Team $team): ?Player
    {
        $fullName = trim($externalPlayer['strPlayer'] ?? '');

        if ($fullName === '') {
            return null;
        }

        [$firstName, $lastName] = $this->splitPlayerName($fullName);

        $position = FootballPositions::normalizeOrDefault(
            $externalPlayer['strPosition'] ?? null,
            'CM',
        );

        $age = $this->calculateAge($externalPlayer['dateBorn'] ?? null);

        return Player::updateOrCreate(
            [
                'team_id' => $team->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ],
            [
                'position' => $position,
                'age' => $age,
                'nationality' => $externalPlayer['strNationality'] ?? null,
            ],
        );
    }

    private function splitPlayerName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));

        if (!$parts || count($parts) === 0) {
            return ['Unknown', 'Player'];
        }

        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $lastName = array_pop($parts);
        $firstName = implode(' ', $parts);

        return [$firstName, $lastName];
    }

    private function calculateAge(?string $birthDate): ?int
    {
        if (!$birthDate) {
            return null;
        }

        try {
            return Carbon::parse($birthDate)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeGet(string $url, array $query = [])
    {
        $maxAttempts = 4;
        $baseWaitSeconds = 30;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Http::timeout(30)->get($url, $query);

            if ($response->successful()) {
                return $response;
            }

            if ($response->status() === 429) {
                $waitSeconds = $baseWaitSeconds * $attempt;

                $this->warn(
                    'Rate limited by API. Attempt ' .
                    $attempt .
                    '/' .
                    $maxAttempts .
                    '. Waiting ' .
                    $waitSeconds .
                    ' seconds...'
                );

                sleep($waitSeconds);

                continue;
            }

            $this->warn('HTTP ' . $response->status() . ' while requesting: ' . $url);
            $this->warn('Response body: ' . $response->body());

            return null;
        }

        $this->error('Request failed after too many rate-limit retries.');

        return null;
    }

    private function apiUrl(string $endpoint): string
    {
        $baseUrl = rtrim(config('services.thesportsdb.base_url'), '/');
        $apiKey = config('services.thesportsdb.api_key', '3');

        return $baseUrl . '/' . $apiKey . '/' . $endpoint;
    }
}
