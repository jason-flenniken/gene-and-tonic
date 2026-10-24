<?php

namespace Modules\Supplements\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Supplements\Models\Supplement;

interface SupplementRepository
{
    /**
     * @throws UniqueConstraintViolationException
     */
    public function create(string $name): Supplement;

    public function find(int $id): ?Supplement;

    public function existsByName(string $name): bool;

    public function delete(Supplement $supplement): void;

    /** @return LengthAwarePaginator<int, Supplement> */
    public function paginateWithResearch(int $perPage): LengthAwarePaginator;

    /** @return array<string, int> */
    public function countByStatus(): array;

    public function total(): int;

    /** @return Collection<int, Supplement> */
    public function recentlyCompleted(int $limit): Collection;
}
