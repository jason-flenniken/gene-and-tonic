<?php

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Modules\Intelligence\Contracts\ProfileRegistry;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Data\GenerationResult;
use Modules\Intelligence\Data\PromptRequest;
use Modules\Intelligence\Exceptions\GenerationFailedException;
use Modules\Intelligence\Exceptions\UnknownProfileException;
use Modules\Intelligence\Models\AiRequest;
use Modules\Intelligence\Services\ProfiledAgent;
use Modules\Intelligence\Services\UsageLogger;
use Modules\Intelligence\Testing\FakeTextGenerator;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function testProfile(array $overrides = []): array
{
    return array_merge([
        'provider' => 'openai',
        'model' => 'gpt-6.1-sol',
        'effort' => 'medium',
        'max_tokens' => 8000,
        'timeout' => 120,
        'instructions' => 'intelligence-test::prompts.instructions',
        'prompt' => 'intelligence-test::prompts.prompt',
    ], $overrides);
}

function testSchema(): Closure
{
    return fn (JsonSchema $s): array => ['summary' => $s->string()->required()];
}

function testRequest(): PromptRequest
{
    return PromptRequest::for('test.profile', ['subject' => 'zincprompt'], testSchema());
}

/**
 * @return array<string, mixed>
 */
function openAiBody(string $text, bool $refusal = false): array
{
    return [
        'status' => 'completed',
        'model' => 'gpt-6.1-sol',
        'output' => [[
            'type' => 'message',
            'status' => 'completed',
            'content' => [$refusal
                ? ['type' => 'refusal', 'refusal' => $text]
                : ['type' => 'output_text', 'text' => $text]],
        ]],
        'usage' => ['input_tokens' => 1000, 'output_tokens' => 2000],
    ];
}

beforeEach(function () {
    View::addNamespace('intelligence-test', __DIR__.'/../fixtures/views');
    config(['intelligence.profiles.test.profile' => testProfile()]);
    config(['ai.providers.openai.key' => 'sk-test-secret']);
});

it('generates structured output through the profile and logs one row', function () {
    ProfiledAgent::fake([
        new StructuredTextResponse(['summary' => 'ok'], '{"summary":"ok"}', new TextUsage(1000, 2000), new Meta('openai', 'gpt-6.1-sol')),
    ]);

    $result = app(TextGenerator::class)->generate(testRequest());

    expect($result)->toBeInstanceOf(GenerationResult::class)
        ->and($result->structured)->toBe(['summary' => 'ok'])
        ->and($result->inputTokens)->toBe(1000)
        ->and($result->outputTokens)->toBe(2000);

    ProfiledAgent::assertPrompted(fn (AgentPrompt $p): bool => $p->model === 'gpt-6.1-sol'
        && $p->provider->name() === 'openai'
        && $p->contains('Research zincprompt thoroughly.')
        && trim($p->agent->instructions()) === 'You are a careful research assistant.'
        && $p->agent->providerOptions('openai') === ['reasoning' => ['effort' => 'medium']]
        && $p->agent->maxTokens() === 8000
        && $p->timeout === 120);

    $row = AiRequest::sole();
    expect($row->status)->toBe('success')
        ->and($row->operation)->toBe('generate')
        ->and($row->profile)->toBe('test.profile')
        ->and($row->effort)->toBe('medium')
        ->and($row->input_tokens)->toBe(1000)
        ->and((float) $row->estimated_cost_usd)->toBe(0.022);
});

it('sends no reasoning options for other providers or a null effort', function () {
    expect((new ProfiledAgent('x', effort: 'medium', maxTokens: 100))->providerOptions('anthropic'))->toBe([])
        ->and((new ProfiledAgent('x', maxTokens: 100))->providerOptions('openai'))->toBe([]);
});

it('switches provider by config alone', function () {
    config(['intelligence.profiles.test.profile.provider' => 'anthropic']);
    ProfiledAgent::fake([['summary' => 'ok']]);

    app(TextGenerator::class)->generate(testRequest());

    ProfiledAgent::assertPrompted(fn (AgentPrompt $p): bool => $p->provider->name() === 'anthropic'
        && $p->agent->providerOptions('anthropic') === []);
});

it('throws UnknownProfileException for an unknown profile', function () {
    app(TextGenerator::class)->generate(PromptRequest::for('nope'));
})->throws(UnknownProfileException::class);

it('reflects runtime config changes and registered profiles', function () {
    $registry = app(ProfileRegistry::class);

    expect($registry->get('test.profile')->model)->toBe('gpt-6.1-sol');

    config(['intelligence.profiles.test.profile.model' => 'gpt-6-luna']);
    expect($registry->get('test.profile')->model)->toBe('gpt-6-luna');

    $registry->register('runtime.one', testProfile(['effort' => null]));
    expect($registry->get('runtime.one')->effort)->toBeNull();
});

it('resolves a registered profile built from env values', function () {
    putenv('AI_RESEARCH_MODEL=gpt-6-luna');
    $_ENV['AI_RESEARCH_MODEL'] = $_SERVER['AI_RESEARCH_MODEL'] = 'gpt-6-luna';

    try {
        app(ProfileRegistry::class)->register('env.profile', testProfile([
            'model' => env('AI_RESEARCH_MODEL', 'gpt-6.1-sol'),
        ]));

        expect(app(ProfileRegistry::class)->get('env.profile')->model)->toBe('gpt-6-luna');
    } finally {
        putenv('AI_RESEARCH_MODEL');
        unset($_ENV['AI_RESEARCH_MODEL'], $_SERVER['AI_RESEARCH_MODEL']);
    }
});

it('rejects an incomplete profile', function () {
    app(ProfileRegistry::class)->register('bad', ['provider' => 'openai']);
})->throws(InvalidArgumentException::class);

it('returns structured data over the real http path with effort, max tokens and strict schema', function () {
    Http::fake(['api.openai.com/*' => Http::response(openAiBody('{"summary":"ok"}'))]);

    $result = app(TextGenerator::class)->generate(testRequest());

    expect($result->structured)->toBe(['summary' => 'ok'])->and($result->model)->toBe('gpt-6.1-sol');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['reasoning']['effort'] === 'medium'
            && $body['max_output_tokens'] === 8000
            && $body['text']['format']['strict'] === true;
    });
});

it('wraps provider failures and logs a failed row', function (Closure $fake) {
    Http::fake(['api.openai.com/*' => $fake]);

    expect(fn () => app(TextGenerator::class)->generate(testRequest()))
        ->toThrow(GenerationFailedException::class);

    $row = AiRequest::sole();
    expect($row->status)->toBe('failed')->and($row->error)->not->toBeEmpty();
})->with([
    '429' => fn () => fn () => Http::response(['error' => ['message' => 'slow down']], 429),
    '503' => fn () => fn () => Http::response(['error' => ['message' => 'down']], 503),
    '500' => fn () => fn () => Http::response(['error' => ['message' => 'boom']], 500),
    'connection' => fn () => fn () => throw new ConnectionException('cURL error 6: sk-test-secret'),
]);

it('treats a refusal as an unusable answer', function () {
    Http::fake(['api.openai.com/*' => Http::response(openAiBody('I cannot help', refusal: true))]);

    expect(fn () => app(TextGenerator::class)->generate(testRequest()))
        ->toThrow(GenerationFailedException::class, 'The research model returned an unusable answer');

    expect(AiRequest::sole()->status)->toBe('failed');
});

it('never stores prompts or keys', function () {
    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(openAiBody('{"summary":"ok"}'))
        ->push([], 500)]);

    app(TextGenerator::class)->generate(testRequest());
    try {
        app(TextGenerator::class)->generate(testRequest());
    } catch (GenerationFailedException) {
    }

    expect(AiRequest::count())->toBe(2);

    foreach (DB::table('ai_requests')->get() as $row) {
        foreach ((array) $row as $value) {
            expect((string) $value)->not->toContain('sk-test-secret')->not->toContain('zincprompt')->not->toContain('careful research');
        }
    }

    expect(Schema::getColumnListing('ai_requests'))->not->toContain('prompt', 'response', 'body', 'instructions');
});

it('scrubs keys out of logged errors', function () {
    $scrubbed = app(UsageLogger::class)->scrub('bad key sk-test-secret and sk-live-abcdefgh12345');

    expect($scrubbed)->not->toContain('sk-test-secret')->not->toContain('sk-live');
});

it('skips logging when disabled', function () {
    config(['intelligence.log_requests' => false]);
    ProfiledAgent::fake([['summary' => 'ok']]);

    app(TextGenerator::class)->generate(testRequest());

    expect(AiRequest::count())->toBe(0);
});

it('provides a fake text generator', function () {
    $fake = FakeTextGenerator::install();
    $fake->respondWith(['summary' => 'a'])->failWith('nope');

    $request = PromptRequest::for('any', ['x' => 1]);

    expect(app(TextGenerator::class)->generate($request)->structured)->toBe(['summary' => 'a']);
    expect(fn () => app(TextGenerator::class)->generate($request))->toThrow(GenerationFailedException::class, 'nope');
    expect(fn () => app(TextGenerator::class)->generate($request))->toThrow(LogicException::class);

    $fake->assertGenerated(fn (PromptRequest $r): bool => $r->variables === ['x' => 1]);
    expect($fake->requests())->toHaveCount(3);
});

it('prices by the configured alias when the provider returns a dated snapshot id', function () {
    Http::fake(['api.openai.com/*' => Http::response(array_merge(
        openAiBody('{"summary":"ok"}'),
        ['model' => 'gpt-6.1-sol-2026-08-12'],
    ))]);

    app(TextGenerator::class)->generate(testRequest());

    $row = AiRequest::sole();
    expect($row->model)->toBe('gpt-6.1-sol-2026-08-12')
        ->and((float) $row->estimated_cost_usd)->toBeGreaterThan(0.0)
        ->and((float) $row->estimated_cost_usd)->toBe(0.022);
});
