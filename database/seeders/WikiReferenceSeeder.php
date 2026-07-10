<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Level;
use App\Models\Palace;
use Illuminate\Database\Seeder;

class WikiReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('wiki.domains') as $id => $name) {
            Domain::updateOrCreate(['id' => $id], ['name' => $name]);
        }

        foreach (array_values(config('wiki.palaces')) as $sort => $key) {
            Palace::updateOrCreate(['key' => $key], ['sort' => $sort]);
        }

        foreach (config('wiki.levels') as $id => $name) {
            Level::updateOrCreate(['id' => $id], ['name' => $name]);
        }
    }
}
