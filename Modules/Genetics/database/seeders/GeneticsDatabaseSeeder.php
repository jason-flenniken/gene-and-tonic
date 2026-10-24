<?php

namespace Modules\Genetics\Database\Seeders;

use Illuminate\Database\Seeder;

class GeneticsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([GeneVariantSeeder::class]);
    }
}
