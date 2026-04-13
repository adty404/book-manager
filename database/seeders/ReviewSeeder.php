<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reviews = [
            ['reviewer_name' => 'john', 'rating' => rand(1,5)],
            ['reviewer_name' => 'doe', 'rating' => rand(1,5)],
            ['reviewer_name' => 'jack', 'rating' => rand(1,5)],
        ];

        $bookIds = DB::table('books')->pluck('id')->toArray();

        foreach ($reviews as &$review) {
            $review['book_id'] = $bookIds[array_rand($bookIds)];
        }

        DB::table('reviews')->insert($reviews);
    }
}
