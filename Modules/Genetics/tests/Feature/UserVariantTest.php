<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Genetics\Models\UserVariant;

it('stores the genotype as ciphertext and reads it back as plaintext', function () {
    UserVariant::factory()->create(['rsid' => 'rs1801133', 'genotype' => 'CT']);

    $raw = DB::table('user_variants')->value('genotype');

    expect($raw)->not->toBe('CT')
        ->and($raw)->not->toContain('CT')
        ->and(UserVariant::sole()->genotype)->toBe('CT');
});

it('keeps the rsid in plaintext', function () {
    UserVariant::factory()->create(['rsid' => 'rs1801133']);

    expect(DB::table('user_variants')->value('rsid'))->toBe('rs1801133');
});

it('allows one row per user and rsid', function () {
    $user = User::factory()->create();

    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs4680']);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs4680']);
})->throws(QueryException::class);

it('deletes a user\'s variants with the user', function () {
    $variant = UserVariant::factory()->create();

    User::findOrFail($variant->user_id)->delete();

    expect(UserVariant::count())->toBe(0);
});
