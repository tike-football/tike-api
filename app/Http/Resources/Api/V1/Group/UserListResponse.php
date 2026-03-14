<?php

namespace App\Http\Resources\Api\V1\Group;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserListResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUser = $request->user();
        $avatarPath = !empty($this->avatar_path) ? $this->avatar_path : 'system/default01.png';
        $avatarUrl = null;
        $status = null;

        if (!empty($avatarPath)) {
            $folderConfig = config('filesystems.folders.user_avatars', []);
            $disk = $folderConfig['driver'] ?? config('filesystems.default', 'local');
            $root = trim((string) ($folderConfig['root'] ?? 'users/avatars/'), '/');
            $storagePath = $root . '/' . ltrim($avatarPath, '/');

            if ($disk === 's3') {
                try {
                    $signedUrlTtlSeconds = (int) config('filesystems.disks.s3.signed_url_ttl_seconds', 7200);
                    $avatarUrl = Storage::disk('s3')->temporaryUrl(
                        $storagePath,
                        now()->addSeconds($signedUrlTtlSeconds)
                    );
                } catch (\Throwable $e) {
                    $configuredUrl = (string) config('filesystems.disks.s3.url', '');
                    $bucket = (string) config('filesystems.disks.s3.bucket', '');
                    $region = (string) config('filesystems.disks.s3.region', 'us-east-1');

                    if (!empty($configuredUrl)) {
                        $baseUrl = rtrim($configuredUrl, '/');
                    } else {
                        $baseUrl = sprintf('https://%s.s3.%s.amazonaws.com', $bucket, $region);
                    }

                    $avatarUrl = $baseUrl . '/' . ltrim($storagePath, '/');
                }
            } else {
                $avatarUrl = Storage::disk($disk)->url($storagePath);
            }

            if (!Str::startsWith((string) $avatarUrl, ['http://', 'https://'])) {
                $avatarUrl = rtrim((string) config('app.url', ''), '/') . '/' . ltrim((string) $avatarUrl, '/');
            }
        }

        if ($authUser !== null && (int) $authUser->id !== (int) $this->id) {
            if ($authUser->isFriendWith($this->id)) {
                $status = 'friend';
            } elseif ($authUser->hasSentFriendRequestTo($this->id)) {
                $status = 'outgoing_friend_request';
            } elseif ($authUser->hasReceivedFriendRequestFrom($this->id)) {
                $status = 'incoming_friend_request';
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'avatar_url' => $avatarUrl,
            'status' => $status,
        ];
    }
}
