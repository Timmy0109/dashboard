<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        return response()->json(
            User::orderBy('name')->get(['id', 'name', 'email', 'role', 'status', 'last_login_at', 'created_at'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'password'   => ['required', Password::min(8)],
            'role'       => 'required|in:admin,manager,member',
            'company_id' => 'sometimes|nullable|exists:companies,id',
        ]);

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'role'       => $data['role'],
            'company_id' => $data['company_id'] ?? null,
            'status'     => 'active',
        ]);

        return response()->json($user->only(['id', 'name', 'email', 'role', 'status']), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'role' => 'sometimes|in:admin,manager,member',
            'status' => 'sometimes|in:active,inactive',
            'password' => ['sometimes', Password::min(8)],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $wasActive = $user->status === 'active';
        $user->update($data);

        if ($wasActive && ($data['status'] ?? null) === 'inactive') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return response()->json($user->only(['id', 'name', 'email', 'role', 'status']));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($user->id === $request->user()->id, 422, '不能刪除自己的帳號');

        $user->delete();
        return response()->json(null, 204);
    }
}
