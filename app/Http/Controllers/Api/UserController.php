<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validatedFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'in:admin,user'],
            'sort_by' => ['nullable', 'string', 'in:name,email,role,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $search = $validatedFilters['search'] ?? null;
        $role = $validatedFilters['role'] ?? null;

        $sortBy = $validatedFilters['sort_by'] ?? 'created_at';
        $sortDirection = $validatedFilters['sort_direction'] ?? 'desc';

        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = User::query();

        if ($search) {
            $query->where(function ($query) use ($search, $likeOperator) {
                $query->where('name', $likeOperator, '%' . $search . '%')
                    ->orWhere('email', $likeOperator, '%' . $search . '%')
                    ->orWhere('role', $likeOperator, '%' . $search . '%');
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        $query->orderBy($sortBy, $sortDirection);

        $users = $query->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,user'],
        ]);

        $user = User::create($validatedData);

        return response()->json($user, Response::HTTP_CREATED);
    }

    public function show(User $user)
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $validatedData = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'required', 'string', 'in:admin,user'],
        ]);

        if (empty($validatedData['password'])) {
            unset($validatedData['password']);
        }

        $user->update($validatedData);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $user->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
