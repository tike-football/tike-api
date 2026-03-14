<?php

namespace App\Http\Resources\Api\V1\Group;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GroupResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUser = $request->user();
        $imageUrl = null;

        if (!empty($this->image_path)) {
            $folderConfig = config('filesystems.folders.group_images', []);
            $disk = $folderConfig['driver'] ?? config('filesystems.default', 'local');
            $root = trim((string) ($folderConfig['root'] ?? 'groups/images/'), '/');
            $storagePath = $root . '/' . ltrim((string) $this->image_path, '/');

            if ($disk === 's3') {
                try {
                    $signedUrlTtlSeconds = (int) config('filesystems.disks.s3.signed_url_ttl_seconds', 7200);
                    $imageUrl = Storage::disk('s3')->temporaryUrl(
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

                    $imageUrl = $baseUrl . '/' . ltrim($storagePath, '/');
                }
            } else {
                $imageUrl = Storage::disk($disk)->url($storagePath);
            }

            if (!Str::startsWith((string) $imageUrl, ['http://', 'https://'])) {
                $imageUrl = rtrim((string) config('app.url', ''), '/') . '/' . ltrim((string) $imageUrl, '/');
            }
        }

        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $imageUrl,
            'is_active' => $this->is_active,
            'allows_comments' => $this->allows_comments,
            'accepts_join_requests' => $this->accepts_join_requests,
            'requires_join_approval' => $this->requires_join_approval,
            'language' => $this->language,
            'total_users' => $this->users()->where('group_user.is_accepted', true)->count(),
            'is_owner' => $authUser !== null ? (int) $this->owner_id === (int) $authUser->id : false,
        ];
    }
}
