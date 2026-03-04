<?php

namespace App\Http\Resources\Api\V1\Util;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvailableAvatarsResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $avatars = is_array($this->resource) ? $this->resource : [];

        $folderConfig = config('filesystems.folders.user_avatars', []);
        $disk = $folderConfig['driver'] ?? config('filesystems.default', 'local');
        $root = trim((string) ($folderConfig['root'] ?? 'users/avatars/'), '/');
        $signedUrlTtlSeconds = (int) config('filesystems.disks.s3.signed_url_ttl_seconds', 7200);

        $response = [];

        foreach ($avatars as $avatarPath) {
            if (!is_string($avatarPath) || $avatarPath === '') {
                continue;
            }

            $avatarUrl = null;
            $storagePath = $root . '/' . ltrim($avatarPath, '/');

            if ($disk === 's3') {
                try {
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

            $response[] = [
                'avatar_path' => $avatarPath,
                'avatar_url' => $avatarUrl,
            ];
        }

        return $response;
    }
}
