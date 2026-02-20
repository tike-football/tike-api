<?php

namespace App\Http\Resources\Api\V1\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avatarPath = !empty($this->avatar_path) ? $this->avatar_path : 'system/default01.png';
        $avatarUrl = null;

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

            if (!Str::startsWith($avatarUrl, ['http://', 'https://'])) {
                $avatarUrl = rtrim((string) config('app.url', ''), '/') . '/' . ltrim($avatarUrl, '/');
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'country_code' => $this->country_code,
            'phone_number' => $this->phone_number,
            'full_phone_number' => $this->full_phone_number,
            'role' => $this->role,
            'avatar_path' => $avatarPath,
            'avatar_url' => $avatarUrl,
            'settings' => $this->settings
                ->mapWithKeys(fn ($setting): array => [$setting->key => $setting->value])
                ->toArray(),
        ];
    }
}
