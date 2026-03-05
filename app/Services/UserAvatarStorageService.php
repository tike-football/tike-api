<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UserAvatarStorageService
{
    public function delete(string $avatarPath): void
    {
        $folderConfig = config('filesystems.folders.user_avatars', []);
        $disk = $folderConfig['driver'] ?? config('filesystems.default', 'local');
        $root = trim((string) ($folderConfig['root'] ?? 'users/avatars/'), '/');
        $storagePath = $root . '/' . ltrim($avatarPath, '/');

        Storage::disk($disk)->delete($storagePath);
    }

    public function store(User $user, UploadedFile $file): string
    {
        $folderConfig = config('filesystems.folders.user_avatars', []);
        $disk = $folderConfig['driver'] ?? config('filesystems.default', 'local');
        $root = trim((string) ($folderConfig['root'] ?? 'users/avatars/'), '/');

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $timestamp = now()->format('Ym') . now()->format('is');
        $filename = sprintf('avatar%d%s.%s', $user->id, $timestamp, $extension);

        $relativePath = 'users/' . $filename;
        $directory = $root !== '' ? $root . '/users' : 'users';

        $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $filename);
        if ($storedPath === false) {
            throw new RuntimeException('Failed to store avatar.');
        }

        return $relativePath;
    }
}
