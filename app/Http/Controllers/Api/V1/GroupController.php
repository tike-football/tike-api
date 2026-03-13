<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\AddGroupUsersRequest;
use App\Http\Requests\Group\CreateGroupRequest;
use App\Http\Requests\Group\UploadGroupImageRequest;
use App\Http\Resources\Api\V1\Group\GroupResponse;
use App\Http\Resources\Api\V1\Group\UserListResponse;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupImageStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GroupController extends Controller
{
    public function users(int $groupId): JsonResponse
    {
        try {
            $authUser = request()->user();

            if ($authUser === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $group = Group::query()->find($groupId);

            if ($group === null) {
                return response()->json([
                    'message' => 'Group not found.',
                ], 404);
            }

            $isMember = $group->users()
                ->where('users.id', $authUser->id)
                ->wherePivot('is_accepted', true)
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'message' => 'You do not belong to this group.',
                ], 403);
            }

            $users = $group->users()
                ->wherePivot('is_accepted', true)
                ->orderBy('users.name')
                ->orderBy('users.last_name')
                ->get();

            return response()->json([
                'group' => GroupResponse::make($group)->resolve(),
                'total_users' => $users->count(),
                'users' => UserListResponse::collection($users)->resolve(),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'user_id' => request()->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving group users.',
                'error' => 'Group users retrieval failed. Please try again.',
            ], 500);
        }
    }

    public function store(CreateGroupRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $group = Group::query()->create([
                'owner_id' => $user->id,
                'name' => (string) $request->validated('name'),
                'description' => $request->validated('description'),
                'language' => (string) $user->getSetting('language', config('settings.language.default', 'es')),
            ]);

            $group->users()->attach($user->id, [
                'is_accepted' => true,
            ]);

            $group->refresh();

            return response()->json([
                'group' => GroupResponse::make($group)->resolve(),
            ], 201);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while creating the group.',
                'error' => 'Group creation failed. Please try again.',
            ], 500);
        }
    }

    public function addUsers(AddGroupUsersRequest $request, int $groupId): JsonResponse
    {
        try {
            $authUser = $request->user();

            if ($authUser === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $group = Group::query()->find($groupId);

            if ($group === null) {
                return response()->json([
                    'message' => 'Group not found.',
                ], 404);
            }

            if ($group->owner_id !== $authUser->id) {
                return response()->json([
                    'message' => 'You cannot add users to this group.',
                ], 403);
            }

            /** @var array<int> $userIds */
            $userIds = $request->validated('user_ids');
            $addedUserIds = [];
            $errors = [];

            foreach ($userIds as $userId) {
                $user = User::query()->find($userId);

                if ($user === null) {
                    $errors[] = [
                        'id' => $userId,
                        'error' => 'User does not exist.',
                    ];

                    continue;
                }

                $alreadyInGroup = $group->users()
                    ->where('users.id', $userId)
                    ->exists();

                if ($alreadyInGroup) {
                    $errors[] = [
                        'id' => $userId,
                        'error' => 'User already belongs to the group.',
                    ];

                    continue;
                }

                $group->users()->attach($userId, [
                    'is_accepted' => true,
                ]);

                $addedUserIds[] = $userId;
            }

            return response()->json([
                'message' => 'Users processed successfully.',
                'added_user_ids' => $addedUserIds,
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'user_id' => $request->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while adding users to the group.',
                'error' => 'Add group users failed. Please try again.',
            ], 500);
        }
    }

    public function uploadImage(
        UploadGroupImageRequest $request,
        GroupImageStorageService $storageService,
        int $groupId
    ): JsonResponse {
        try {
            $authUser = $request->user();

            if ($authUser === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $group = Group::query()->find($groupId);

            if ($group === null) {
                return response()->json([
                    'message' => 'Group not found.',
                ], 404);
            }

            if ($group->owner_id !== $authUser->id) {
                return response()->json([
                    'message' => 'You cannot update the image of this group.',
                ], 403);
            }

            $file = $request->file('image');

            if ($file === null) {
                return response()->json([
                    'message' => 'The group image is required.',
                    'error' => 'Group image upload failed. Please try again.',
                ], 422);
            }

            $previousImagePath = $group->image_path;
            $group->image_path = $storageService->store($group, $file);
            $group->save();

            if (is_string($previousImagePath) && $previousImagePath !== '' && $previousImagePath !== $group->image_path) {
                $storageService->delete($previousImagePath);
            }

            return response()->json([
                'group' => GroupResponse::make($group)->resolve(),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'user_id' => $request->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while uploading the group image.',
                'error' => 'Group image upload failed. Please try again.',
            ], 500);
        }
    }
}
