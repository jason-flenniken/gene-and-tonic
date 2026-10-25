<?php

namespace Modules\Intelligence\Contracts;

use Modules\Intelligence\Data\ProfileData;
use Modules\Intelligence\Exceptions\UnknownProfileException;

interface ProfileRegistry
{
    /**
     * Register a profile at runtime. Shape matches `intelligence.profiles.<name>`.
     *
     * @param  array{provider: string, model: string, effort?: string|null, label?: string, max_tokens: int, timeout: int, instructions: view-string, prompt: view-string}  $profile
     */
    public function register(string $name, array $profile): void;

    /**
     * @throws UnknownProfileException
     */
    public function get(string $name): ProfileData;

    /**
     * Human label for a stored usage row. Never throws: unknown names fall back to the raw name,
     * and search rows without a profile read "Web search".
     */
    public function labelFor(?string $name, string $operation): string;
}
