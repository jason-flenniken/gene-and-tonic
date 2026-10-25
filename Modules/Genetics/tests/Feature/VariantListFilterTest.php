<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;
use Modules\Shared\Data\LinkedSupplementData;
use Modules\Shared\Testing\FakeVariantLinks;

beforeEach(function () {
    $this->withoutVite();
    Queue::fake();
    $this->user = User::factory()->create();
});

function seedVariant(User $user, array $reference, string $genotype = 'GG'): void
{
    GeneVariant::factory()->create($reference);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => $reference['rsid'], 'genotype' => $genotype]);
}

/** @return list<string> the gene cells of the rendered list */
function genes($component): array
{
    preg_match_all('/data-variant-gene="(.*?)"/', $component->html(), $matches);

    return $matches[1];
}

function sectionIsOpen($component, string $key): bool
{
    return preg_match('/<details[^>]*data-section="'.$key.'"[^>]*\sopen[\s>]/', $component->html()) === 1;
}

function seedList(User $user): void
{
    seedVariant($user, ['rsid' => 'rs1', 'gene' => 'MTHFR', 'category_group' => VariantGroup::Methylation, 'category' => 'Folate cycle', 'affects' => 'Folate use.']);
    seedVariant($user, ['rsid' => 'rs2', 'gene' => 'COMT', 'category_group' => VariantGroup::BrainMood, 'category' => 'Dopamine', 'affects' => 'Stress response.']);
    seedVariant($user, ['rsid' => 'rs3', 'gene' => 'ZZZ9', 'category_group' => null, 'category' => 'Misc', 'affects' => 'Unsorted trait.']);
    GeneVariant::factory()->pending()->create(['rsid' => 'rs4']);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs4', 'genotype' => 'GG']);
}

it('renders sections in enum order with counts, Other for ungrouped and pending last', function () {
    seedList($this->user);
    seedVariant($this->user, ['rsid' => 'rs5', 'gene' => 'ABC1', 'category_group' => VariantGroup::Methylation]);

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->assertSeeInOrder(['Methylation', '2 variants', 'Brain & mood', '1 variant', 'Other', 'Not looked up yet'])
        ->assertSeeInOrder(['Other', 'ZZZ9'])
        ->assertSeeHtml('wire:key="section-unlooked"');
});

it('starts collapsed, opens filtered sections and collapses again when cleared', function () {
    seedList($this->user);

    $component = Livewire::actingAs($this->user)->test('genetics::profile');
    expect(preg_match('/<details[^>]*\sopen[\s>]/', $component->html()))->toBe(0);

    $component->set('group', 'methylation');
    expect(genes($component))->toBe(['MTHFR'])->and(sectionIsOpen($component, 'methylation'))->toBeTrue();

    $component->set('group', '');
    expect(genes($component))->toHaveCount(4)->and(preg_match('/<details[^>]*\sopen[\s>]/', $component->html()))->toBe(0);

    $component->set('search', 'comt');
    expect(sectionIsOpen($component, 'brain_mood'))->toBeTrue();

    $component->set('search', '');
    expect(preg_match('/<details[^>]*\sopen[\s>]/', $component->html()))->toBe(0);
});

it('searches case-insensitively over gene, group label and affects', function () {
    seedList($this->user);

    $component = Livewire::actingAs($this->user)->test('genetics::profile');

    expect(genes($component->set('search', 'comt')))->toBe(['COMT']);
    expect(genes($component->set('search', 'unsorted trait')))->toBe(['ZZZ9']);
    expect(genes($component->set('search', 'brain')))->toBe(['COMT']);
});

it('combines the dropdown and the search and shows an empty message', function () {
    seedList($this->user);

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('group', 'methylation')->set('search', 'COMT')
        ->assertSee('No variants match');
});

it('offers All, groups that have rows, then Not looked up yet', function () {
    seedList($this->user);

    $options = Livewire::actingAs($this->user)->test('genetics::profile')->instance()->groupOptions;

    expect(array_keys($options))->toBe(['methylation', 'brain_mood', 'other', 'unlooked']);
});

it('trims and caps the search at 100 characters without erroring', function () {
    seedList($this->user);

    $component = Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('search', '  '.str_repeat('a', 300))
        ->assertHasNoErrors()
        ->assertSee('No variants match');

    expect(mb_strlen($component->instance()->activeSearch))->toBe(98);
});

it('keeps the Look up button for a pending row in the last section', function () {
    seedList($this->user);

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('group', 'unlooked')
        ->assertSee('Not looked up yet')
        ->assertSeeHtml("wire:click=\"lookUp('rs4')\"")
        ->assertDontSee('ZZZ9');
});

it('treats an unknown dropdown value as All and never prints it unescaped', function (string $value) {
    seedList($this->user);

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('group', $value)
        ->assertSee('rs4')
        ->assertDontSeeHtml('<script>');

    $component = Livewire::actingAs($this->user)->test('genetics::profile')->set('group', $value);
    expect(genes($component))->toHaveCount(4)->and(preg_match('/<details[^>]*\sopen[\s>]/', $component->html()))->toBe(0);
})->with(['<script>', '../x', 'METHYLATION']);

it('escapes search text and never matches the genotype letters', function () {
    seedVariant($this->user, ['rsid' => 'rs9', 'gene' => 'MTHFR', 'category' => 'Folate', 'affects' => 'Folate use.', 'genotype_effects' => ['TT' => 'Some effect.']], 'TT');

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('search', 'TT')->assertSee('No variants match')
        ->set('search', '<script>alert(1)</script>')->assertDontSeeHtml('<script>alert(1)</script>');
});

it('never shows another user\'s variants under any filter or search', function () {
    $other = User::factory()->create();
    seedVariant($other, ['rsid' => 'rs777', 'gene' => 'OTHERGENE', 'category_group' => VariantGroup::Methylation]);
    seedList($this->user);

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->assertDontSee('OTHERGENE')
        ->set('group', 'methylation')->assertDontSee('OTHERGENE')
        ->set('group', '')->set('search', 'othergene')->assertDontSee('OTHERGENE')->assertSee('No variants match');
});

it('keeps a looking-up row polling from the unfiltered list', function () {
    seedList($this->user);
    UserVariant::query()->where('rsid', 'rs4')->update(['user_id' => $this->user->id]);
    GeneVariant::where('rsid', 'rs4')->update(['status' => LookupStatus::LookingUp]);

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('group', 'methylation')
        ->assertSeeHtml('wire:poll.3s');
});

it('toggles a section open, keeps it open across re-renders and closes it again', function () {
    seedList($this->user);

    $component = Livewire::actingAs($this->user)->test('genetics::profile')->call('toggleSection', 'methylation');

    expect(sectionIsOpen($component, 'methylation'))->toBeTrue()
        ->and(sectionIsOpen($component, 'brain_mood'))->toBeFalse();

    $component->call('$refresh');
    expect(sectionIsOpen($component, 'methylation'))->toBeTrue();

    $component->call('toggleSection', 'methylation');
    expect(sectionIsOpen($component, 'methylation'))->toBeFalse();
});

it('ignores invalid section keys and never renders them', function (string $key) {
    seedList($this->user);

    $component = Livewire::actingAs($this->user)->test('genetics::profile')->call('toggleSection', $key);

    expect($component->get('openSections'))->toBe([])
        ->and(preg_match('/<details[^>]*\sopen[\s>]/', $component->html()))->toBe(0);
    $component->assertDontSeeHtml('<script>');
})->with(['<script>', '../x', 'METHYLATION', '']);

it('rejects open-section values set from the client and filters stored ones on read', function () {
    seedList($this->user);

    $component = Livewire::actingAs($this->user)->test('genetics::profile');

    expect(fn () => $component->set('openSections', ['brain_mood']))->toThrow(CannotUpdateLockedPropertyException::class);

    $component->call('toggleSection', 'brain_mood');
    expect(sectionIsOpen($component, 'brain_mood'))->toBeTrue();
    $component->assertDontSeeHtml('<script>');
});

it('lets a section be closed while a search or dropdown filter is active', function () {
    seedList($this->user);

    $component = Livewire::actingAs($this->user)->test('genetics::profile')->set('search', 'comt');
    expect(sectionIsOpen($component, 'brain_mood'))->toBeTrue();

    $component->call('toggleSection', 'brain_mood');
    expect(sectionIsOpen($component, 'brain_mood'))->toBeFalse();

    $component->call('$refresh');
    expect(sectionIsOpen($component, 'brain_mood'))->toBeFalse();

    $component->set('search', '')->set('group', 'brain_mood');
    expect(sectionIsOpen($component, 'brain_mood'))->toBeTrue();

    $component->call('toggleSection', 'brain_mood');
    expect(sectionIsOpen($component, 'brain_mood'))->toBeFalse();
});

it('caps the search text at 100 characters', function () {
    seedList($this->user);

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('search', str_repeat('a', 300))
        ->assertSet('search', str_repeat('a', 100));
});

it('keeps an opened section open when another row finishes its lookup', function () {
    seedList($this->user);
    GeneVariant::where('rsid', 'rs4')->update(['status' => LookupStatus::LookingUp]);

    $component = Livewire::actingAs($this->user)->test('genetics::profile')->call('toggleSection', 'brain_mood');

    GeneVariant::where('rsid', 'rs4')->update(['status' => LookupStatus::Complete, 'category_group' => VariantGroup::Methylation, 'category' => 'Folate']);
    $component->call('$refresh');

    expect(sectionIsOpen($component, 'brain_mood'))->toBeTrue()
        ->and(sectionIsOpen($component, 'methylation'))->toBeFalse()
        ->and(genes($component))->toHaveCount(4);
});

it('collapses everything again once both search and dropdown are cleared', function () {
    seedList($this->user);

    $component = Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('search', 'comt')->call('toggleSection', 'methylation')
        ->set('search', '');

    expect($component->get('openSections'))->toBe([])
        ->and(preg_match('/<details[^>]*\sopen[\s>]/', $component->html()))->toBe(0);
});

it('finds rows by rsID, category text and genotype effect text', function () {
    seedVariant($this->user, ['rsid' => 'rs11', 'gene' => 'MTHFR', 'category' => 'Folate cycle', 'affects' => 'Folate use.', 'genotype_effects' => ['T/T' => 'Reduced enzyme activity.']], 'T/T');
    seedVariant($this->user, ['rsid' => 'rs22', 'gene' => 'COMT', 'category' => 'Dopamine', 'affects' => 'Stress response.']);

    $component = Livewire::actingAs($this->user)->test('genetics::profile');

    expect(genes($component->set('search', 'rs22')))->toBe(['COMT'])
        ->and(genes($component->set('search', 'folate cycle')))->toBe(['MTHFR'])
        ->and(genes($component->set('search', 'reduced enzyme')))->toBe(['MTHFR']);
});

it('shows Linked chips and filters to linked variants only', function () {
    seedList($this->user);
    FakeVariantLinks::install()->for($this->user->id, ['rs1' => [new LinkedSupplementData(7, 'Methylfolate')]]);

    $component = Livewire::actingAs($this->user)->test('genetics::profile')
        ->assertSee('Linked: Methylfolate')
        ->assertSeeHtml(route('supplements.show', 7));

    expect($component->instance()->counts['linked'])->toBe(1);

    $component->set('group', 'linked');
    expect(genes($component))->toBe(['MTHFR']);
});

it('filters to two-copy variants and to not looked up', function () {
    seedVariant($this->user, ['rsid' => 'rs1', 'gene' => 'AAA1', 'risk_allele' => 'A', 'category_group' => VariantGroup::Methylation], 'AA');
    seedVariant($this->user, ['rsid' => 'rs2', 'gene' => 'BBB2', 'risk_allele' => 'A', 'category_group' => VariantGroup::Methylation], 'AG');
    GeneVariant::factory()->pending()->create(['rsid' => 'rs3']);
    UserVariant::factory()->create(['user_id' => $this->user->id, 'rsid' => 'rs3', 'genotype' => 'AA']);

    $component = Livewire::actingAs($this->user)->test('genetics::profile');

    expect($component->instance()->counts)->toMatchArray(['all' => 3, 'two_copies' => 1, 'unlooked' => 1]);
    expect(genes($component->set('group', 'two_copies')))->toBe(['AAA1']);
    $component->assertSee('Your AA, two copies')->set('group', 'unlooked')->assertSee('rs3')->assertDontSee('AAA1');
    $component->set('group', 'methylation')->assertSee('Your AG', false);
});

it('opens the section named by ?group on load', function () {
    seedList($this->user);

    $component = Livewire::withQueryParams(['group' => 'methylation'])->actingAs($this->user)->test('genetics::profile');

    expect(sectionIsOpen($component, 'methylation'))->toBeTrue()
        ->and(sectionIsOpen($component, 'brain_mood'))->toBeFalse()
        ->and(genes($component))->toBe(['MTHFR']);
});

it('treats a hostile ?group as All and never echoes it', function () {
    seedList($this->user);

    $component = Livewire::withQueryParams(['group' => '<script>alert(1)</script>'])->actingAs($this->user)->test('genetics::profile');

    expect(genes($component))->toHaveCount(4)->and(preg_match('/<details[^>]*\sopen[\s>]/', $component->html()))->toBe(0);
    $component->assertDontSeeHtml('<script>alert(1)</script>');
});

it('never shows another user\'s linked chips or genotype', function () {
    $other = User::factory()->create();
    seedVariant($other, ['rsid' => 'rs777', 'gene' => 'OTHERGENE', 'category_group' => VariantGroup::Methylation]);
    seedList($this->user);
    FakeVariantLinks::install()->for($other->id, ['rs777' => [new LinkedSupplementData(9, 'Secret supp')]]);

    Livewire::actingAs($this->user)->test('genetics::profile')->assertDontSee('Secret supp')->assertDontSee('OTHERGENE');
});
