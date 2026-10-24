<?php

namespace Modules\Intelligence\Services;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\Intelligence\Contracts\ProfileRegistry;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Data\GenerationResult;
use Modules\Intelligence\Data\ProfileData;
use Modules\Intelligence\Data\PromptRequest;
use Modules\Intelligence\Exceptions\GenerationFailedException;

class LaravelAiTextGenerator implements TextGenerator
{
    public function __construct(
        private readonly ProfileRegistry $profiles,
        private readonly UsageLogger $usage,
        private readonly ViewFactory $views,
    ) {}

    public function generate(PromptRequest $request): GenerationResult
    {
        $profile = $this->profiles->get($request->profile);
        $started = hrtime(true);

        try {
            $result = $this->run($profile, $request, $started);
        } catch (GenerationFailedException $e) {
            $this->usage->logGeneration($profile, null, $e, $this->elapsedMs($started));

            throw $e;
        }

        $this->usage->logGeneration($profile, $result, null, $result->latencyMs);

        return $result;
    }

    private function run(ProfileData $profile, PromptRequest $request, int $started): GenerationResult
    {
        $agent = new ProfiledAgent(
            instructions: $this->views->make($profile->instructionsView, $request->variables)->render(),
            schema: $request->schema ?? fn (JsonSchema $s): array => ['text' => $s->string()->required()],
            effort: $profile->effort,
            maxTokens: $profile->maxTokens,
        );

        try {
            /** @var StructuredAgentResponse $response */
            $response = $agent->prompt(
                $this->views->make($profile->promptView, $request->variables)->render(),
                provider: $profile->provider,
                model: $profile->model,
                timeout: $profile->timeout,
            );
        } catch (RateLimitedException $e) {
            throw GenerationFailedException::because('The AI provider is rate limiting requests. Try again shortly.', $e);
        } catch (InsufficientCreditsException $e) {
            throw GenerationFailedException::because('The AI provider account has insufficient credits.', $e);
        } catch (ProviderOverloadedException $e) {
            throw GenerationFailedException::because('The AI provider is overloaded. Try again shortly.', $e);
        } catch (ProviderConnectionException|ConnectionException $e) {
            throw GenerationFailedException::because('Could not reach the AI provider.', $e);
        } catch (RequestException $e) {
            throw GenerationFailedException::because("The AI provider rejected the request (HTTP {$e->response->status()}).", $e);
        } catch (AiException $e) {
            throw GenerationFailedException::because('The AI provider reported an error.', $e);
        }

        if ($response->steps->last()?->finishReason === FinishReason::Length) {
            throw GenerationFailedException::because('The research model ran out of output tokens before finishing its answer');
        }

        // OpenAI refusals arrive as an empty structured answer.
        if ($response->structured === []) {
            throw GenerationFailedException::because('The research model returned an unusable answer');
        }

        return new GenerationResult(
            text: $response->text,
            structured: $response->structured,
            provider: $response->meta->provider ?? $profile->provider,
            model: $response->meta->model ?? $profile->model,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            latencyMs: $this->elapsedMs($started),
        );
    }

    private function elapsedMs(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
