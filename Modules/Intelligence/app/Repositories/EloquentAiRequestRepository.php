<?php

namespace Modules\Intelligence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Intelligence\Models\AiRequest;

class EloquentAiRequestRepository implements AiRequestRepository
{
    public function record(array $attributes): void
    {
        AiRequest::query()->create($attributes);
    }

    public function summary(): UsageSummary
    {
        $since = now()->subDay();

        $row = AiRequest::query()
            ->selectRaw(
                "count(*) as calls,
                sum(case when status = 'failed' then 1 else 0 end) as failed_calls,
                sum(input_tokens) as input_tokens,
                sum(output_tokens) as output_tokens,
                sum(estimated_cost_usd) as cost,
                sum(case when created_at >= ? then 1 else 0 end) as calls_24h,
                sum(case when created_at >= ? then estimated_cost_usd else 0 end) as cost_24h",
                [$since, $since],
            )
            ->toBase()
            ->first();

        return new UsageSummary(
            calls: (int) ($row->calls ?? 0),
            failedCalls: (int) ($row->failed_calls ?? 0),
            inputTokens: (int) ($row->input_tokens ?? 0),
            outputTokens: (int) ($row->output_tokens ?? 0),
            estimatedCostUsd: (float) ($row->cost ?? 0),
            callsLast24h: (int) ($row->calls_24h ?? 0),
            costLast24hUsd: (float) ($row->cost_24h ?? 0),
        );
    }

    public function paginateLatest(int $perPage): LengthAwarePaginator
    {
        return AiRequest::query()->orderByDesc('id')->paginate($perPage);
    }
}
