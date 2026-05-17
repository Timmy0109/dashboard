<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class MemberApprovalController extends Controller
{
    private function managerGuard(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user->isManager() && ! $user->isAdmin()) {
            return response()->json(['message' => '權限不足'], 403);
        }
        if (! $user->isAdmin() && ! $user->company_id) {
            return response()->json(['message' => '無公司歸屬'], 403);
        }
        return null;
    }

    // GET /api/manager/members
    public function members(Request $request): JsonResponse
    {
        if ($err = $this->managerGuard($request)) return $err;

        $actor = $request->user();
        $query = User::where('role', 'member');

        if (! $actor->isAdmin()) {
            $query->where('company_id', $actor->company_id);
        }

        $members = $query->orderBy('name')->get()->map(fn($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'status'     => $u->status,
            'created_at' => $u->created_at?->format('Y-m-d'),
        ]);

        return response()->json($members);
    }

    // GET /api/manager/members/pending
    public function pending(Request $request): JsonResponse
    {
        if ($err = $this->managerGuard($request)) return $err;

        $actor = $request->user();
        $query = User::where('status', 'pending')->where('role', 'member');

        if (! $actor->isAdmin()) {
            $query->where('company_id', $actor->company_id);
        }

        $pending = $query->with('company')->get()->map(fn($u) => [
            'id'           => $u->id,
            'name'         => $u->name,
            'email'        => $u->email,
            'company_name' => $u->company?->name,
            'created_at'   => $u->created_at?->format('Y-m-d H:i'),
        ]);

        return response()->json($pending);
    }

    // PUT /api/manager/members/{user}
    public function update(Request $request, User $user): JsonResponse
    {
        if ($err = $this->managerGuard($request)) return $err;

        $actor = $request->user();
        if ($user->role !== 'member' || (! $actor->isAdmin() && $user->company_id !== $actor->company_id)) {
            return response()->json(['message' => '無權限操作此成員'], 403);
        }

        $data = $request->validate([
            'name'     => 'sometimes|string|max:100',
            'email'    => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => ['sometimes', Password::min(8)],
            'status'   => 'sometimes|in:active,inactive',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $wasActive = $user->status === 'active';
        $user->update($data);

        if ($wasActive && ($data['status'] ?? null) === 'inactive') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return response()->json([
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'status' => $user->status,
        ]);
    }

    // DELETE /api/manager/members/{user}
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($err = $this->managerGuard($request)) return $err;

        $actor = $request->user();
        if ($user->role !== 'member' || (! $actor->isAdmin() && $user->company_id !== $actor->company_id)) {
            return response()->json(['message' => '無權限操作此成員'], 403);
        }

        $user->delete();
        return response()->json(null, 204);
    }

    // POST /api/manager/members/{user}/approve
    public function approve(Request $request, User $user): JsonResponse
    {
        if ($err = $this->managerGuard($request)) return $err;

        $actor = $request->user();
        if (! $actor->isAdmin() && $user->company_id !== $actor->company_id) {
            return response()->json(['message' => '無權限操作此成員'], 403);
        }

        $user->update(['status' => 'active']);
        return response()->json(['message' => '已核准']);
    }

    // POST /api/manager/members/{user}/reject
    public function reject(Request $request, User $user): JsonResponse
    {
        if ($err = $this->managerGuard($request)) return $err;

        $actor = $request->user();
        if (! $actor->isAdmin() && $user->company_id !== $actor->company_id) {
            return response()->json(['message' => '無權限操作此成員'], 403);
        }

        $user->update(['status' => 'inactive']);
        return response()->json(['message' => '已拒絕']);
    }
}
