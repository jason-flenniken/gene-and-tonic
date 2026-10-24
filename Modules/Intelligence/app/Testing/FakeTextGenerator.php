<?php

namespace Modules\Intelligence\Testing;

use Closure;
use LogicException;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Data\GenerationResult;
use Modules\Intelligence\Data\PromptRequest;
use Modules\Intelligence\Exceptions\GenerationFailedException;
use PHPUnit\Framework\Assert;

/**
 * In-memory TextGenerator for other modules' tests. Responses are consumed in order.
 */
class FakeTextGenerator implements TextGenerator
{
    /** @var list<array<string, mixed>|GenerationFailedException> */
    private array $queue = [];

    /** @var list<PromptRequest> */
    private array $requests = [];

    public static function install(): self
    {
        $fake = new self;

        app()->instance(TextGenerator::class, $fake);

        return $fake;
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    public function respondWith(array $structured): self
    {
        $this->queue[] = $structured;

        return $this;
    }

    public function failWith(string $message): self
    {
        $this->queue[] = GenerationFailedException::because($message);

        return $this;
    }

    /**
     * @return list<PromptRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * @param  Closure(PromptRequest): bool  $check
     */
    public function assertGenerated(Closure $check): void
    {
        Assert::assertNotEmpty(
            array_filter($this->requests, $check),
            'No generation request matched the given check.',
        );
    }

    public function generate(PromptRequest $request): GenerationResult
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new LogicException("FakeTextGenerator has no queued response for profile [{$request->profile}].");
        }

        $next = array_shift($this->queue);

        if ($next instanceof GenerationFailedException) {
            throw $next;
        }

        return new GenerationResult(
            text: (string) json_encode($next),
            structured: $next,
            provider: 'fake',
            model: 'fake',
            inputTokens: 0,
            outputTokens: 0,
            latencyMs: 0,
        );
    }
}
