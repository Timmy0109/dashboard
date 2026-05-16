<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberApprovalController extends Controller
{
    private function managerGuard(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user->isManager() && ! $user->isAdmin()) {
            return response()->json(['message' => '權限不足'], 403);
        }
        if (! $user->company_id) {
            return response()->json(['message' => '無公司歸屬'], 403);
        }
        return null;
    }

    // GET /api/manager/members/pending
    public function pending(Request $request): JsonResponse
    {
        if ($err = $this->managerGuard($request)) return $err;

        $companyId = $request->user()->company_id;

        $pending = User::where('company_id', $companyId)
            ->where('status', 'pending')
            ->where('role', 'member')
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'created_at' => $u->created_at?->format('Y-m-d H:i'),
            ]);

        return response()->json($pending);
    }

    // POST /api/manager/members/{user}/approve
    public function approve(Request $request, User $user): JsonResponse
    {
        if ($err = $this->managerGuard($request)) return $err;

        if ($user->company_id !== $request->user()->company_id) {
            return response()->json(['message' => '無權限操作此成員'], 403);
        }

        $user->update(['status' => 'active']);
        return response()->json(['message' => '已核准']);
    }

    // POST /api/manager/members/{user}/reject
    public function reject(Request $request, User $user): JsonResponse
    {
        if ($err = $this->managerGuard($request)) return $err;

        if ($user->company_id !== $request->user()->company_id) {
            return response()->json(['message' => '無權限操作此成員'], 403);
        }

        $user->update(['status' => 'inactive']);
        return response()->json(['message' => '已拒絕']);
    }
}
