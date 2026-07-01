<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlayerController extends Controller
{
    public function index()
    {
        $players = Player::with('team')->get();

        return response()->json($players);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:15', 'max:60'],
            'nationality' => ['nullable', 'string', 'max:255'],
        ]);

        $player = Player::create($validatedData);

        return response()->json($player, Response::HTTP_CREATED);
    }

    public function show(Player $player)
    {
        $player->load('team');

        return response()->json($player);
    }

    public function update(Request $request, Player $player)
    {
        $validatedData = $request->validate([
            'team_id' => ['sometimes', 'required', 'integer', 'exists:teams,id'],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'position' => ['sometimes', 'required', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:15', 'max:60'],
            'nationality' => ['nullable', 'string', 'max:255'],
        ]);

        $player->update($validatedData);

        return response()->json($player);
    }

    public function destroy(Player $player)
    {
        $player->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
