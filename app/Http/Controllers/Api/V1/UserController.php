<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\User\UserResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get the authenticated user.
     */
    public function get(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserResponse::make($request->user())->resolve(),
        ]);
    }
}
