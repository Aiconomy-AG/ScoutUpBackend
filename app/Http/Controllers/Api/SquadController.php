<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Squad;
use App\Models\SquadPlayer;
use App\Support\FootballFormations;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SquadController extends Controller
{
    public function index(Request $request)
    {
        $squads = Squad::where('user_id', $request->user()->id)
            ->with(['squadPlayers.player.team'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($squads);
    }

    public function store(Request $request)
    {
        $formation = $request->input('formation', '4-3-3');

        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'formation' => ['nullable', 'string', Rule::in(FootballFormations::allowedFormations())],
            'chemistry_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'players' => ['nullable', 'array'],
            'players.*.slot' => ['required', 'string', Rule::in(FootballFormations::slotsFor($formation))],
            'players.*.player_id' => ['required', 'integer', 'exists:players,id'],
        ]);

        $squad = DB::transaction(function () use ($request, $validatedData, $formation) {
            $squad = Squad::create([
                'user_id' => $request->user()->id,
                'name' => $validatedData['name'],
                'formation' => $validatedData['formation'] ?? $formation,
                'chemistry_score' => $validatedData['chemistry_score'] ?? 0,
            ]);

            $this->syncSquadPlayers($squad, $validatedData['players'] ?? []);

            return $squad;
        });

        $squad->load(['squadPlayers.player.team']);

        return response()->json($squad, Response::HTTP_CREATED);
    }

    public function show(Request $request, Squad $squad)
    {
        $this->ensureUserOwnsSquad($request, $squad);

        $squad->load(['squadPlayers.player.team']);

        return response()->json($squad);
    }

    public function update(Request $request, Squad $squad)
    {
        $this->ensureUserOwnsSquad($request, $squad);

        $formation = $request->input('formation', $squad->formation);

        $validatedData = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'formation' => ['sometimes', 'required', 'string', Rule::in(FootballFormations::allowedFormations())],
            'chemistry_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'players' => ['nullable', 'array'],
            'players.*.slot' => ['required', 'string', Rule::in(FootballFormations::slotsFor($formation))],
            'players.*.player_id' => ['required', 'integer', 'exists:players,id'],
        ]);

        DB::transaction(function () use ($squad, $validatedData) {
            $squad->update([
                'name' => $validatedData['name'] ?? $squad->name,
                'formation' => $validatedData['formation'] ?? $squad->formation,
                'chemistry_score' => $validatedData['chemistry_score'] ?? $squad->chemistry_score,
            ]);

            if (array_key_exists('players', $validatedData)) {
                $this->syncSquadPlayers($squad, $validatedData['players'] ?? []);
            }
        });

        $squad->load(['squadPlayers.player.team']);

        return response()->json($squad);
    }

    public function destroy(Request $request, Squad $squad)
    {
        $this->ensureUserOwnsSquad($request, $squad);

        $squad->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function ensureUserOwnsSquad(Request $request, Squad $squad): void
    {
        if ($squad->user_id !== $request->user()->id) {
            abort(403, 'You are not allowed to access this squad.');
        }
    }

    private function syncSquadPlayers(Squad $squad, array $players): void
    {
        $this->validateUniqueSlotsAndPlayers($players);

        $squad->squadPlayers()->delete();

        foreach ($players as $playerData) {
            SquadPlayer::create([
                'squad_id' => $squad->id,
                'player_id' => $playerData['player_id'],
                'slot' => $playerData['slot'],
            ]);
        }
    }

    private function validateUniqueSlotsAndPlayers(array $players): void
    {
        $slots = collect($players)->pluck('slot');
        $playerIds = collect($players)->pluck('player_id');

        if ($slots->count() !== $slots->unique()->count()) {
            throw ValidationException::withMessages([
                'players' => ['Each squad slot can only be used once.'],
            ]);
        }

        if ($playerIds->count() !== $playerIds->unique()->count()) {
            throw ValidationException::withMessages([
                'players' => ['Each player can only be used once in a squad.'],
            ]);
        }
    }
}
