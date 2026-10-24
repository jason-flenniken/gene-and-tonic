<?php

namespace Modules\Reports\Tests\Support;

use Carbon\CarbonImmutable;
use Modules\Supplements\Data\EffectData;
use Modules\Supplements\Data\MechanismData;
use Modules\Supplements\Data\SourceData;
use Modules\Supplements\Data\StudyData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Enums\ResearchStatus;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Shared fixtures and assertions for the Reports tests, so every test file runs on its own.
 */
final class ReportFixtures
{
    public const DISCLAIMER = 'This is a research summary generated from published sources, not medical advice. Talk to a qualified clinician before starting a supplement.';

    /**
     * A completed research DTO: 2 effects, 1 mechanism, 2 studies, 3 sources.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function research(array $overrides = []): SupplementResearchData
    {
        return new SupplementResearchData(...array_merge([
            'supplementId' => 1,
            'name' => 'Magnesium',
            'status' => ResearchStatus::Complete,
            'summary' => 'Magnesium glycinate is well absorbed.',
            'safetyNotes' => 'Can cause loose stools at high doses.',
            'effects' => [
                new EffectData('Improves sleep quality', 'Shorter sleep latency', EvidenceGrade::Moderate, ['S1', 'S2']),
                new EffectData('Reduces muscle cramps', null, EvidenceGrade::Limited, ['S3']),
            ],
            'mechanisms' => [
                new MechanismData('Acts as an NMDA receptor antagonist', ['S2']),
            ],
            'studies' => [
                new StudyData('Magnesium and insomnia in the elderly', 'RCT', 'Older adults', 46, 2012, 'Improved sleep efficiency', EvidenceGrade::Moderate, 'S1', '23853635'),
                new StudyData('Cramp prophylaxis review', 'Systematic review', null, null, 2020, null, null, 'S3', null),
            ],
            'sources' => [
                new SourceData('S1', 'https://pubmed.ncbi.nlm.nih.gov/23853635/', 'Insomnia trial', 'pubmed.ncbi.nlm.nih.gov'),
                new SourceData('S2', 'https://ods.od.nih.gov/factsheets/Magnesium/', 'Magnesium fact sheet', 'ods.od.nih.gov'),
                new SourceData('S3', 'https://www.cochranelibrary.com/cdsr/doi/10.1002/14651858.CD009402', 'Cochrane cramps review', 'cochranelibrary.com'),
            ],
            'completedAt' => CarbonImmutable::parse('2026-10-20 09:00'),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function url(int $supplementId = 1, array $query = []): string
    {
        return route('reports.supplements.pdf', ['supplement' => $supplementId, ...$query]);
    }

    /**
     * Pest's negated toContain() passes when any one needle is missing, so check each.
     */
    public static function expectAbsent(?string $html, string ...$needles): void
    {
        foreach ($needles as $needle) {
            expect($html)->not->toContain($needle);
        }
    }

    public static function assertNoPdfResponded(): void
    {
        expect(fn () => Pdf::assertRespondedWithPdf(fn () => true))->toThrow(AssertionFailedError::class);
    }
}
