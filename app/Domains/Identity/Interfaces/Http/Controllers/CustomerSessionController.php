<?php

namespace App\Domains\Identity\Interfaces\Http\Controllers;

use App\Domains\Identity\Interfaces\Http\Resources\CustomerResource;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSessionController
{
    public function __construct(private readonly Factory $auth) {}

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'بيانات العميل الحالي.',
            'data' => (new CustomerResource($request->user('customer')))->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'message' => 'تم تسجيل الخروج.', 'data' => (object) []]);
    }
}
