<?php

use App\Models\User;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Testing\FakeGeneticRelevanceReader;
use Modules\Reports\Tests\Support\ReportFixtures;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Testing\FakeSupplementResearchReader;
use Spatie\LaravelPdf\Facades\Pdf;

beforeEach(function () {
    Pdf::fake();
    FakeSupplementResearchReader::install()->with(ReportFixtures::research());
    $this->relevance = FakeGeneticRelevanceReader::install();
});

function reportGeneticMatch(string $genotype = 'AA', string $explanation = 'Reduced enzyme activity.'): GeneticMatchData
{
    return new GeneticMatchData('MTHFR', 'rs1801133', $genotype, 2, GeneRelation::Helps, $explanation, ['S2'], GeneticMatchData::MATCHED_ON_ALLELE);
}

function reportRelevance(GeneticMatchData ...$matches): GeneticRelevanceData
{
    return new GeneticRelevanceData(1, true, true, array_values($matches));
}

it('adds the genetics section after effects in both templates', function (string $template) {
    $user = User::factory()->create();
    $this->relevance->for($user->id, 1, reportRelevance(reportGeneticMatch()));

    $this->actingAs($user)->get(ReportFixtures::url(query: ['template' => $template]))->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        $html = $pdf->getHtml();

        expect($html)->toContain(
            'Your genetic relevance',
            'MTHFR',
            'rs1801133',
            'Genotype: AA (2 copies)',
            'Helps',
            'Reduced enzyme activity.',
            '<sup class="cite">[2]</sup>',
            'Educational summary of published research, not medical advice or a diagnosis. Talk to a qualified clinician before acting on genetic results.',
        );
        expect(strpos($html, 'id="effects"'))->toBeLessThan(strpos($html, 'id="genetics"'))
            ->and(strpos($html, 'id="genetics"'))->toBeLessThan(strpos($html, 'id="mechanisms"'));

        return true;
    });
})->with(['clinical', 'modern']);

it('leaves the report unchanged for a guest', function () {
    $this->relevance->for(1, 1, reportRelevance(reportGeneticMatch()));

    $this->get(ReportFixtures::url())->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        ReportFixtures::expectAbsent($pdf->getHtml(), 'Your genetic relevance', 'rs1801133', 'id="genetics"');

        return true;
    });
});

it('adds no section when a signed-in user has no matches', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(ReportFixtures::url())->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        ReportFixtures::expectAbsent($pdf->getHtml(), 'Your genetic relevance', 'id="genetics"');

        return true;
    });
});

it('shows only the signed-in user their own genotype and ignores a user query parameter', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $this->relevance
        ->for($userA->id, 1, reportRelevance(reportGeneticMatch('AA')))
        ->for($userB->id, 1, reportRelevance(reportGeneticMatch('GG')));

    $this->actingAs($userA)->get(ReportFixtures::url(query: ['user' => $userB->id, 'user_id' => $userB->id]))->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->getHtml())->toContain('Genotype: AA');
        ReportFixtures::expectAbsent($pdf->getHtml(), 'Genotype: GG');

        return true;
    });
});

it('escapes hostile text in the genetics section', function () {
    $user = User::factory()->create();
    $this->relevance->for($user->id, 1, reportRelevance(reportGeneticMatch('AA', '<script>alert(1)</script>')));

    $this->actingAs($user)->get(ReportFixtures::url())->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->getHtml())->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
        ReportFixtures::expectAbsent($pdf->getHtml(), '<script');

        return true;
    });
});
