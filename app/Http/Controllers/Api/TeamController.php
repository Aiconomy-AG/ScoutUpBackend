<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    public function index(Request $request)
    {

        $validatedFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'founded_after' => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
            'founded_before' => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
        ]);

        $search = $validatedFilters['search'] ?? null;
        $foundedAfter = $validatedFilters['founded_after'] ?? null;
        $foundedBefore = $validatedFilters['founded_before'] ?? null;

        $query = Team::with('players');

        if($search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('city', 'like', '%' . $search . '%')
                    ->orWhere('stadium', 'like', '%' . $search . '%');
            });
        }

        if ($foundedAfter) {
            $query->where('founded_year', '>=', $foundedAfter);
        }

        if ($foundedBefore) {
            $query->where('founded_year', '<=', $foundedBefore);
        }

        $teams = $query->get();

        return response()->json($teams);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'stadium' => ['nullable', 'string', 'max:255'],
            'founded_year' => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
        ]);

        $team = Team::create($validatedData);

        return response()->json($team, Response::HTTP_CREATED);
    }

    public function show(Team $team)
    {
        $team->load('players');

        return response()->json($team);
    }

    public function update(Request $request, Team $team)
    {
        $validatedData = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'stadium' => ['nullable', 'string', 'max:255'],
            'founded_year' => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
        ]);

        $team->update($validatedData);

        return response()->json($team);
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
