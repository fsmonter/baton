<?php

declare(strict_types=1);

namespace Baton\Contracts;

interface ProcessStore
{
    /** @param array<string, mixed> $data */
    public function save(string $id, array $data): void;

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array;

    /** @return array<string, array<string, mixed>> */
    public function all(): array;

    public function remove(string $id): void;

    public function exists(string $id): bool;
}
