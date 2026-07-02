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

            'sort_by' => ['nullable', 'string', 'in:name,city,stadium,founded_year,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $search = $validatedFilters['search'] ?? null;
        $foundedAfter = $validatedFilters['founded_after'] ?? null;
        $foundedBefore = $validatedFilters['founded_before'] ?? null;

        $sortBy = $validatedFilters['sort_by'] ?? 'created_at';
        $sortDirection = $validatedFilters['sort_direction'] ?? 'desc';

        $query = Team::with('players');

        if ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('city', 'ilike', '%' . $search . '%')
                    ->orWhere('stadium', 'ilike', '%' . $search . '%');
            });
        }

        if ($foundedAfter) {
            $query->where('founded_year', '>=', $foundedAfter);
        }

        if ($foundedBefore) {
            $query->where('founded_year', '<=', $foundedBefore);
        }

        $query->orderBy($sortBy, $sortDirection);

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
