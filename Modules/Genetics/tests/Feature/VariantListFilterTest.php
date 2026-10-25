<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;

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
    preg_match_all('/<td class="py-2 pr-4 font-medium">(.*?)<\/td>/', $component->html(), $matches);

    return $matches[1];
}

function sectionIsOpen($component, string $key): bool
{
    return preg_match('/<details[^>]*wire:key="section-'.$key.'"[^>]*\sopen\s/', $component->html()) === 1;
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
        ->assertSeeInOrder(['Methylation', '>2<', 'Brain & mood', '>1<', 'Other', 'Not looked up yet'])
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

    expect(mb_strlen($component->instance()->activeSearch))->toBe(100);
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
