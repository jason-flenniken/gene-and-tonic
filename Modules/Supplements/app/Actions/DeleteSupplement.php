<?php

namespace Modules\Supplements\Actions;

use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Repositories\SupplementRepository;

class DeleteSupplement
{
    public function __construct(private readonly SupplementRepository $supplements) {}

    /**
     * Delete the supplement; research and findings go with it via cascading foreign keys.
     */
    public function handle(Supplement $supplement): void
    {
        $this->supplements->delete($supplement);
    }
}
