<?php

namespace Modules\Intelligence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Intelligence\Models\AiRequest;

interface AiRequestRepository
{
    /**
     * @param  array{operation: string, profile: string|null, provider: string, model: string|null, effort: string|null, input_tokens: int, output_tokens: int, credits: int|null, latency_ms: int, estimated_cost_usd: float, status: string, error: string|null}  $attributes
     */
    public function record(array $attributes): void;

    public function summary(): UsageSummary;

    /** @return LengthAwarePaginator<int, AiRequest> */
    public function paginateLatest(int $perPage): LengthAwarePaginator;
}
