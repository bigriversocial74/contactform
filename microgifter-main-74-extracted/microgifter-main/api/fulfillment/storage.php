<?php
declare(strict_types=1);

interface MgMediaStorageProvider
{
    public function resolve(string $storageKey): string;
    public function exists(string $storageKey): bool;
}

final class MgPrivateLocalStorage implements MgMediaStorageProvider
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $candidate = $root ?: dirname(__DIR__, 2) . '/storage/private';
        $resolved = realpath($candidate);
        if ($resolved === false) {
            throw new RuntimeException('Private media storage is unavailable.');
        }
        $this->root = $resolved;
    }

    public function resolve(string $storageKey): string
    {
        $path = realpath($this->root . '/' . ltrim($storageKey, '/'));
        if ($path === false || !str_starts_with($path, $this->root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Media storage path is invalid.');
        }
        return $path;
    }

    public function exists(string $storageKey): bool
    {
        try {
            return is_file($this->resolve($storageKey));
        } catch (Throwable) {
            return false;
        }
    }
}

function mg_media_storage(string $provider): MgMediaStorageProvider
{
    return match ($provider) {
        'private_local' => new MgPrivateLocalStorage(),
        default => throw new RuntimeException('Unsupported media storage provider.'),
    };
}
