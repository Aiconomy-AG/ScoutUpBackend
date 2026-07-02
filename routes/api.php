<?php

use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Http\Request;
use App\Services\TheSportsDbService;
use App\Models\Team;
use App\Models\Player;

Route::apiResource('teams', TeamController::class);
Route::apiResource('players', PlayerController::class);


Route::get('/external/teams/search', function (TheSportsDbService $service) {
    $teamName = request('name');

    if (!$teamName) {
        return response()->json([
            'message' => 'Query parameter "name" is required.',
        ], 422);
    }

    return response()->json(
        $service->searchTeams($teamName)
    );
});

Route::get('/external/players/search', function (TheSportsDbService $service) {
    $playerName = request('name');

    if (!$playerName) {
        return response()->json([
            'message' => 'Query parameter "name" is required.',
        ], 422);
    }

    return response()->json(
        $service->searchPlayers($playerName)
    );
});

Route::post('/external/teams/import', function (Request $request, TheSportsDbService $service) {
    $validatedData = $request->validate([
        'name' => ['required', 'string', 'max:255'],
    ]);

    $response = $service->searchTeams($validatedData['name']);

    $externalTeam = $response['teams'][0] ?? null;

    if (!$externalTeam) {
        return response()->json([
            'message' => 'No team found with this name.',
        ], 404);
    }

    $foundedYear = $externalTeam['intFormedYear'] ?? null;

    if ($foundedYear !== null && !is_numeric($foundedYear)) {
        $foundedYear = null;
    }

    $team = Team::updateOrCreate(
        [
            'name' => $externalTeam['strTeam'],
        ],
        [
            'city' => $externalTeam['strLocation'] ?? null,
            'stadium' => $externalTeam['strStadium'] ?? null,
            'founded_year' => $foundedYear ? (int) $foundedYear : null,
        ]
    );

    return response()->json([
        'message' => 'Team imported successfully.',
        'team' => $team,
        'external_data' => $externalTeam,
    ], 201);
});

Route::post('/external/players/import', function (Request $request, TheSportsDbService $service) {
    $validatedData = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'team_id' => ['required', 'integer', 'exists:teams,id'],
    ]);

    $response = $service->searchPlayers($validatedData['name']);

    $externalPlayer = $response['player'][0] ?? null;

    if (!$externalPlayer) {
        return response()->json([
            'message' => 'No player found with this name.',
        ], 404);
    }

    $fullName = $externalPlayer['strPlayer'] ?? $validatedData['name'];

    $nameParts = explode(' ', trim($fullName), 2);

    $firstName = $nameParts[0] ?? $fullName;
    $lastName = $nameParts[1] ?? '';

    $age = null;

    if (!empty($externalPlayer['dateBorn'])) {
        $age = \Carbon\Carbon::parse($externalPlayer['dateBorn'])->age;
    }

    $player = Player::updateOrCreate(
        [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'team_id' => $validatedData['team_id'],
        ],
        [
            'position' => $externalPlayer['strPosition'] ?? 'Unknown',
            'age' => $age,
            'nationality' => $externalPlayer['strNationality'] ?? null,
        ]
    );

    return response()->json([
        'message' => 'Player imported successfully.',
        'player' => $player,
        'external_data' => $externalPlayer,
    ], 201);
});
