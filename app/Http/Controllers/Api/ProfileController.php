<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    // PUT /api/profile
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $request->user()->update(['name' => $data['name']]);
        $user = $request->user()->fresh();

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'avatar_url' => $user->avatar_url,
        ]);
    }

    // POST /api/profile/avatar
    public function avatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json([
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    // PUT /api/profile/password
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password'      => 'required|string',
            'password'              => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['目前密碼不正確'],
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => '密碼已更新']);
    }
}
