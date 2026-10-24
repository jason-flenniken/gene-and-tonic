<?php

namespace Modules\Genetics\Contracts;

use Modules\Genetics\Data\GeneticProfileData;

/**
 * Read-only access to one user's variants joined to the reference data. The
 * caller passes the authenticated user's id; never take it from request input.
 */
interface GeneticProfileReader
{
    public function forUser(int $userId): GeneticProfileData;
}
