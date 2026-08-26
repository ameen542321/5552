<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('plans')->insert([

            [
                'name' => 'basic',
                'allowed_stores' => 1,
                'allowed_accountants' => 2,
                'price' => 0,
            ],

            [
                'name' => 'silver',
                'allowed_stores' => 3,
                'allowed_accountants' => 8,
                'price' => 0,
            ],

            [
                'name' => 'gold',
                'allowed_stores' => 6,
                'allowed_accountants' => 115,
                'price' => 0,
            ],

        ]);
    }
}
