<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\Team;
use App\Services\TheSportsDbService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Support\FootballPositions;

class ImportLiga1FromTheSportsDb extends Command
{
    protected $signature = 'scoutup:import-liga1';

    protected $description = 'Import Liga 1 teams and players from TheSportsDB';

    public function handle(TheSportsDbService $service): int
    {
        $leagueId = config('services.thesportsdb.liga1_league_id', '4691');
        $leagueName = config('services.thesportsdb.liga1_league_name', 'Liga 1 Romania');

        $this->info("Importing league ID: {$leagueId}");

        $teamsResponse = $service->getTeamsByLeagueId($leagueId);

        $externalTeams = $teamsResponse['teams'] ?? [];

        if (!is_array($externalTeams)) {
            $this->error('TheSportsDB did not return a teams array.');
            $this->line('Response received:');
            $this->line(json_encode($teamsResponse, JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        if (empty($externalTeams)) {
            $this->error('No teams found for this league ID.');

            return self::FAILURE;
        }

        $importedTeams = 0;
        $importedPlayers = 0;

        foreach ($externalTeams as $externalTeam) {
            $externalTeamId = $externalTeam['idTeam'] ?? null;
            $teamName = $externalTeam['strTeam'] ?? null;

            if (!$externalTeamId || !$teamName) {
                continue;
            }

            $foundedYear = $externalTeam['intFormedYear'] ?? null;

            if ($foundedYear !== null && !is_numeric($foundedYear)) {
                $foundedYear = null;
            }

            $team = Team::updateOrCreate(
                [
                    'name' => $teamName,
                ],
                [
                    'city' => $externalTeam['strLocation'] ?? null,
                    'stadium' => $externalTeam['strStadium'] ?? null,
                    'founded_year' => $foundedYear ? (int) $foundedYear : null,
                ]
            );

            $importedTeams++;

            $this->line("Team imported: {$team->name}");

            $playersResponse = $service->getPlayersByTeamExternalId($externalTeamId);

            $externalPlayers = $playersResponse['player'] ?? [];

            foreach ($externalPlayers as $externalPlayer) {
                $fullName = $externalPlayer['strPlayer'] ?? null;

                if (!$fullName) {
                    continue;
                }

                [$firstName, $lastName] = $this->splitName($fullName);

                $age = $this->calculateAge($externalPlayer['dateBorn'] ?? null);

                Player::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                    ],
                    [
                        'position' => $externalPlayer['strPosition'] ?? 'Unknown',
                        'age' => $age,
                        'nationality' => $externalPlayer['strNationality'] ?? null,
                    ]
                );

                $importedPlayers++;
            }

            $this->line("Players imported for {$team->name}: " . count($externalPlayers));
        }

        $this->info("Done. Imported/updated {$importedTeams} teams and {$importedPlayers} players.");

        return self::SUCCESS;
    }

    private function splitName(string $fullName): array
    {
        $nameParts = explode(' ', trim($fullName), 2);

        $firstName = $nameParts[0] ?? $fullName;
        $lastName = $nameParts[1] ?? '';

        return [$firstName, $lastName];
    }

    private function calculateAge(?string $dateBorn): ?int
    {
        if (!$dateBorn) {
            return null;
        }

        try {
            return Carbon::parse($dateBorn)->age;
        } catch (\Exception) {
            return null;
        }
    }
}
