<?php

namespace Modules\Intelligence\Services;

use InvalidArgumentException;
use Modules\Intelligence\Contracts\ProfileRegistry;
use Modules\Intelligence\Data\ProfileData;
use Modules\Intelligence\Exceptions\UnknownProfileException;

class ConfigProfileRegistry implements ProfileRegistry
{
    private const REQUIRED = ['provider', 'model', 'max_tokens', 'timeout', 'instructions', 'prompt'];

    /** @var array<string, array{provider: string, model: string, effort?: string|null, max_tokens: int, timeout: int, instructions: view-string, prompt: view-string}> */
    private array $registered = [];

    public function register(string $name, array $profile): void
    {
        $this->validate($name, $profile);

        $this->registered[$name] = $profile;
    }

    public function get(string $name): ProfileData
    {
        /** @var array{provider: string, model: string, effort?: string|null, max_tokens: int, timeout: int, instructions: view-string, prompt: view-string}|null $profile */
        $profile = $this->registered[$name] ?? config("intelligence.profiles.{$name}");

        if (! is_array($profile)) {
            throw UnknownProfileException::named($name);
        }

        $this->validate($name, $profile);

        return new ProfileData(
            name: $name,
            provider: (string) $profile['provider'],
            model: (string) $profile['model'],
            effort: isset($profile['effort']) && $profile['effort'] !== '' ? (string) $profile['effort'] : null,
            maxTokens: (int) $profile['max_tokens'],
            timeout: (int) $profile['timeout'],
            instructionsView: $profile['instructions'],
            promptView: $profile['prompt'],
        );
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function validate(string $name, array $profile): void
    {
        $missing = array_filter(self::REQUIRED, fn (string $key): bool => ! isset($profile[$key]) || $profile[$key] === '');

        if ($missing !== []) {
            throw new InvalidArgumentException("AI profile [{$name}] is missing: ".implode(', ', $missing).'.');
        }
    }
}
