<?php

namespace Modules\Intelligence\Contracts;

use Modules\Intelligence\Data\GenerationResult;
use Modules\Intelligence\Data\PromptRequest;
use Modules\Intelligence\Exceptions\GenerationFailedException;
use Modules\Intelligence\Exceptions\UnknownProfileException;

interface TextGenerator
{
    /**
     * @throws UnknownProfileException
     * @throws GenerationFailedException
     */
    public function generate(PromptRequest $request): GenerationResult;
}
