<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuthorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $authors = [
            ['name' => 'John Doe', 'email' => 'johndoe@author.com'],
            ['name' => 'Jane Smith', 'email' => 'janesmith@author.com'],
            ['name' => 'Taylor Otwell', 'email' => 'taylor@author.com'],
            ['name' => 'Jeffrey Way', 'email' => 'jeffrey@author.com'],
            ['name' => 'Matt Stauffer', 'email' => 'matt@author.com'],
            ['name' => 'Chris Fidao', 'email' => 'chris@author.com'],
            ['name' => 'Marcel Pociot', 'email' => 'marcel@author.com'],
            ['name' => 'Freek Van der Herten', 'email' => 'freek@author.com'],
            ['name' => 'Nuno Maduro', 'email' => 'nuno@author.com'],
            ['name' => 'Caleb Porzio', 'email' => 'caleb@author.com'],
        ];

        DB::table('authors')->insert($authors);
    }
}
