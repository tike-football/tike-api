<?php

namespace App\Services;

use App\Models\Group;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GroupImageStorageService
{
    public function delete(string $imagePath): void
    {
        $folderConfig = config('filesystems.folders.group_images', []);
        $disk = $folderConfig['driver'] ?? config('filesystems.default', 'local');
        $root = trim((string) ($folderConfig['root'] ?? 'groups/images/'), '/');
        $storagePath = $root . '/' . ltrim($imagePath, '/');

        $deleted = Storage::disk($disk)->delete($storagePath);

        Log::info('Group image deleted from storage', [
            'disk' => $disk,
            'storage_path' => $storagePath,
            'deleted' => $deleted,
        ]);
    }

    public function store(Group $group, UploadedFile $file): string
    {
        $folderConfig = config('filesystems.folders.group_images', []);
        $disk = $folderConfig['driver'] ?? config('filesystems.default', 'local');
        $root = trim((string) ($folderConfig['root'] ?? 'groups/images/'), '/');

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $timestamp = now()->format('YmdHis');
        $filename = sprintf('group%d%s.%s', $group->id, $timestamp, $extension);

        $storedPath = Storage::disk($disk)->putFileAs($root, $file, $filename);

        if ($storedPath === false) {
            throw new RuntimeException('Failed to store group image.');
        }

        return $filename;
    }
}
