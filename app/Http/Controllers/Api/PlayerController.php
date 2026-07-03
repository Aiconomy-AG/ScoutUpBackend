<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\FootballPositions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        $validatedFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'age_after' => ['nullable', 'integer', 'min:5', 'max:99'],
            'age_before' => ['nullable', 'integer', 'min:5', 'max:99'],
            'sort_by' => ['nullable', 'string', 'in:last_name,first_name,team_id,position,age,nationality,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $search = $validatedFilters['search'] ?? null;
        $position = $validatedFilters['position'] ?? null;
        $ageAfter = $validatedFilters['age_after'] ?? null;
        $ageBefore = $validatedFilters['age_before'] ?? null;
        $sortBy = $validatedFilters['sort_by'] ?? 'created_at';
        $sortDirection = $validatedFilters['sort_direction'] ?? 'desc';

        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = Player::with('team');

        if ($search) {
            $normalizedSearchPosition = FootballPositions::normalize($search);

            $query->where(function ($query) use ($search, $likeOperator, $normalizedSearchPosition) {
                $query->where('first_name', $likeOperator, '%' . $search . '%')
                    ->orWhere('last_name', $likeOperator, '%' . $search . '%')
                    ->orWhere('position', $likeOperator, '%' . $search . '%')
                    ->orWhere('nationality', $likeOperator, '%' . $search . '%');

                if (in_array($normalizedSearchPosition, FootballPositions::allowed(), true)) {
                    $query->orWhere('position', $normalizedSearchPosition);
                }
            });
        }

        if ($position) {
            $normalizedPosition = FootballPositions::normalize($position);

            if (in_array($normalizedPosition, FootballPositions::allowed(), true)) {
                $query->where('position', $normalizedPosition);
            }
        }

        if ($ageAfter) {
            $query->where('age', '>=', $ageAfter);
        }

        if ($ageBefore) {
            $query->where('age', '<=', $ageBefore);
        }

        $query->orderBy($sortBy, $sortDirection);

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $this->normalizePositionIfPresent($request);

        $validatedData = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', Rule::in(FootballPositions::allowed())],
            'age' => ['nullable', 'integer', 'min:15', 'max:99'],
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
        $this->normalizePositionIfPresent($request);

        $validatedData = $request->validate([
            'team_id' => ['sometimes', 'required', 'integer', 'exists:teams,id'],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'position' => ['sometimes', 'required', 'string', Rule::in(FootballPositions::allowed())],
            'age' => ['nullable', 'integer', 'min:15', 'max:99'],
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

    private function normalizePositionIfPresent(Request $request): void
    {
        if (!$request->has('position')) {
            return;
        }

        $request->merge([
            'position' => FootballPositions::normalize($request->input('position')),
        ]);
    }
}
