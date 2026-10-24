<?php

namespace Modules\Genetics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Genetics\Database\Factories\UserVariantFactory;

/**
 * One user's genotype at one rsID. The genotype is encrypted at rest and is
 * never logged or put in an exception message.
 *
 * @property int $id
 * @property int $user_id
 * @property string $rsid
 * @property string $genotype
 * @property string $source
 * @property int|null $genetic_upload_id
 */
class UserVariant extends Model
{
    /** @use HasFactory<UserVariantFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'rsid', 'genotype', 'source', 'genetic_upload_id'];

    protected static function newFactory(): UserVariantFactory
    {
        return UserVariantFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'genotype' => 'encrypted',
        ];
    }
}
