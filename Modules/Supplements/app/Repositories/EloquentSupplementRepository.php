<?php

namespace Modules\Supplements\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;
use Modules\Supplements\Support\SupplementName;

class EloquentSupplementRepository implements SupplementRepository
{
    public function create(string $name): Supplement
    {
        $name = SupplementName::clean($name);

        return Supplement::query()->create([
            'name' => $name,
            'normalized_name' => SupplementName::normalize($name),
            'slug' => $this->uniqueSlug($name),
        ]);
    }

    public function find(int $id): ?Supplement
    {
        return Supplement::query()->find($id);
    }

    public function existsByName(string $name): bool
    {
        return Supplement::query()
            ->where('normalized_name', SupplementName::normalize($name))
            ->exists();
    }

    public function delete(Supplement $supplement): void
    {
        $supplement->delete();
    }

    public function paginateWithResearch(int $perPage): LengthAwarePaginator
    {
        return Supplement::query()
            ->with('research')
            ->withCount(['effects', 'studies'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function countByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = SupplementResearch::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return $counts;
    }

    public function total(): int
    {
        return Supplement::query()->count();
    }

    public function recentlyCompleted(int $limit): Collection
    {
        return Supplement::query()
            ->with('research')
            ->withCount(['effects', 'studies'])
            ->whereHas('research', fn ($query) => $query->where('status', ResearchStatus::Complete))
            ->orderByDesc(
                SupplementResearch::query()->select('completed_at')->whereColumn('supplement_id', 'supplements.id')
            )
            ->limit($limit)
            ->get();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'supplement';
        $slug = $base;
        $suffix = 2;

        while (Supplement::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
