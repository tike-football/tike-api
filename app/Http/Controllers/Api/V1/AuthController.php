<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\User\UserStored;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignUpRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function getToken(Request $request): JsonResponse
    {
        try {
            $login = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            if (!Auth::attempt($login)) {
                return response()->json(['message' => 'Your email or password are incorrect.'], 403);
            }

            
            $user = Auth::user();

            // Check if the user's email is verified
            if (is_null($user->email_verified_at)) {
                return response()->json([
                    'message' => 'Your email address is not verified.',
                ], 403);
            }

            $scopes = $user->getRoleScopes();
            $token = $user->createToken('token-name', $scopes)->accessToken;

            return response()->json(
                [
                    'access_token' => $token,
                ],
                200
            );

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Register a new user.
     *
     * @param SignUpRequest $request
     * @return JsonResponse
     */
    public function signUp(SignUpRequest $request): JsonResponse
    {
        try {
            // Create the new user
            $user = User::create([
                'name' => $request->name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user', // Default role
            ]);

            // Dispatch UserStored event
            event(new UserStored($user));

            return response()->json([
                'message' => 'User registered successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ], 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Registration failed.',
                'error' => 'An error occurred while creating the user. Please try again.'
            ], 500);
        }
    }
}
