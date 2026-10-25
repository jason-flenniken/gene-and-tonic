<?php

namespace Modules\Intelligence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Intelligence\Enums\UsageWindow;
use Modules\Intelligence\Models\AiRequest;

class EloquentAiRequestRepository implements AiRequestRepository
{
    public function record(array $attributes): void
    {
        AiRequest::query()->create($attributes);
    }

    public function summary(UsageWindow $window = UsageWindow::All): UsageSummary
    {
        $row = $this->within($window)
            ->selectRaw(
                "count(*) as calls,
                sum(case when status = 'failed' then 1 else 0 end) as failed_calls,
                sum(input_tokens) as input_tokens,
                sum(output_tokens) as output_tokens,
                sum(estimated_cost_usd) as cost",
            )
            ->toBase()
            ->first();

        return new UsageSummary(
            calls: (int) ($row->calls ?? 0),
            failedCalls: (int) ($row->failed_calls ?? 0),
            inputTokens: (int) ($row->input_tokens ?? 0),
            outputTokens: (int) ($row->output_tokens ?? 0),
            estimatedCostUsd: (float) ($row->cost ?? 0),
        );
    }

    public function paginateLatest(int $perPage, UsageWindow $window = UsageWindow::All): LengthAwarePaginator
    {
        return $this->within($window)->orderByDesc('id')->paginate($perPage);
    }

    public function costByProfile(UsageWindow $window = UsageWindow::All): array
    {
        $rows = $this->within($window)
            ->selectRaw(
                "profile, operation, count(*) as calls,
                sum(case when status = 'failed' then 1 else 0 end) as failed,
                sum(coalesce(credits, 0)) as credits,
                sum(estimated_cost_usd) as cost",
            )
            ->groupBy('profile', 'operation')
            ->orderByDesc('cost')
            ->orderBy('profile')
            ->toBase()
            ->get();

        return array_values($rows->map(fn (object $row): ProfileCost => new ProfileCost(
            profile: $row->profile,
            operation: (string) $row->operation,
            calls: (int) $row->calls,
            failed: (int) $row->failed,
            credits: (int) $row->credits,
            cost: (float) $row->cost,
        ))->all());
    }

    /** @return Builder<AiRequest> */
    private function within(UsageWindow $window): Builder
    {
        $query = AiRequest::query();

        if ($since = $window->since()) {
            $query->where('created_at', '>=', $since);
        }

        return $query;
    }
}
