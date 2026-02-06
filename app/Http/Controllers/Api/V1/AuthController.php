<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\User\UserStored;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignUpRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Return an access_token
     *
     * @param Request $request
     * @return JsonResponse
     */
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

            // Set user language setting
            $language = $request->input('language');
            
            // If language is provided and valid, use it; otherwise use default
            $availableLanguages = config('settings.language.options', ['es', 'en']);
            $defaultLanguage = config('settings.language.default', 'es');
            
            if ($language && in_array($language, $availableLanguages)) {
                $user->setSetting('language', $language);
            } else {
                $user->setSetting('language', $defaultLanguage);
            }

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
                    'language' => $user->getSetting('language'),
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

    /**
     * Verify user's email address.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if email is already verified
            if (!is_null($user->email_verified_at)) {
                return response()->json([
                    'message' => 'Email address is already verified.',
                ], 400);
            }

            // Mark email as verified
            $user->email_verified_at = now();
            $user->save();

            // Revoke the current token to prevent reuse
            $token = $user->token();
            if ($token) {
                $token->revoke();
            }

            return response()->json([
                'message' => 'Email verified successfully.',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at->toDateTimeString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred while verifying the email.',
                'error' => 'Email verification failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Update the authenticated user's password
     *
     * @param UpdatePasswordRequest $request
     * @return JsonResponse
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Update the password
            $user->password = Hash::make($request->input('new_password'));
            $user->save();

            return response()->json([
                'message' => 'Password updated successfully.',
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred while updating the password.',
                'error' => 'Password update failed. Please try again.'
            ], 500);
        }
    }
}
