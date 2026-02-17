<?php

declare(strict_types=1);

namespace Baton\Store;

use Baton\Contracts\ProcessStore;
use Illuminate\Support\Facades\File;

class FilesystemStore implements ProcessStore
{
    public function __construct(
        protected string $directory,
    ) {
        File::ensureDirectoryExists($this->directory);
    }

    public function save(string $id, array $data): void
    {
        $path = $this->path($id);

        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
    }

    public function get(string $id): ?array
    {
        $path = $this->path($id);

        if (! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    public function all(): array
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        $entries = [];

        foreach (glob($this->directory.'/*.json') as $file) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            $data = $this->get($id);

            if ($data !== null) {
                $entries[$id] = $data;
            }
        }

        return $entries;
    }

    public function remove(string $id): void
    {
        $path = $this->path($id);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function exists(string $id): bool
    {
        return file_exists($this->path($id));
    }

    protected function path(string $id): string
    {
        return $this->directory.'/'.basename($id).'.json';
    }
}
