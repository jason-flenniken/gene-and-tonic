<?php

namespace Modules\Intelligence\Repositories;

use Modules\Intelligence\Models\AiRequest;

class EloquentAiRequestRepository implements AiRequestRepository
{
    public function record(array $attributes): void
    {
        AiRequest::query()->create($attributes);
    }
}
