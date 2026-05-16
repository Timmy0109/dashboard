<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /** Validate an invite code and return company name. */
    public function validateCode(Request $request): JsonResponse
    {
        $request->validate(['invite_code' => 'required|string']);

        $company = Company::findByInviteCode($request->invite_code);

        if (! $company) {
            throw ValidationException::withMessages([
                'invite_code' => ['邀請碼無效或已過期'],
            ]);
        }

        return response()->json(['company_name' => $company->name]);
    }

    /** Register a new member using an invite code. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|string|min:8|confirmed',
            'invite_code' => 'required|string',
        ]);

        $company = Company::findByInviteCode($data['invite_code']);

        if (! $company) {
            throw ValidationException::withMessages([
                'invite_code' => ['邀請碼無效或已過期'],
            ]);
        }

        $requiresApproval = $company->hasFeature('member.approval_required');

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'role'       => 'member',
            'status'     => $requiresApproval ? 'pending' : 'active',
            'company_id' => $company->id,
        ]);

        return response()->json([
            'message'          => $requiresApproval
                ? '註冊成功！您的帳號待主管審核後即可登入。'
                : '註冊成功！您現在可以登入了。',
            'requires_approval' => $requiresApproval,
        ], 201);
    }
}
