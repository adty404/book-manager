<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $books = [
            ['title' => 'Mastering Laravel Console', 'genre' => 'Programming', 'stock' => 3, 'published' => rand(0,1)],
            ['title' => 'Mastering Laravel', 'genre' => 'Programming', 'stock' => 2, 'published' => rand(0,1)],
            ['title' => 'Laravel for Beginners', 'genre' => 'Programming', 'stock' => 5, 'published' => rand(0,1)],
            ['title' => 'Laravel Intermediate', 'genre' => 'Programming', 'stock' => 10, 'published' => rand(0,1)],
            ['title' => 'Laravel Advanced Techniques', 'genre' => 'Programming', 'stock' => 7, 'published' => rand(0,1)],
            ['title' => 'Laravel API Development', 'genre' => 'Web Development', 'stock' => 8, 'published' => rand(0,1)],
            ['title' => 'Laravel REST API Guide', 'genre' => 'Web Development', 'stock' => 6, 'published' => rand(0,1)],
            ['title' => 'Laravel with Vue.js', 'genre' => 'Web Development', 'stock' => 4, 'published' => rand(0,1)],
            ['title' => 'Laravel with React', 'genre' => 'Web Development', 'stock' => 9, 'published' => rand(0,1)],
            ['title' => 'Laravel Testing Handbook', 'genre' => 'Software Testing', 'stock' => 11, 'published' => rand(0,1)],
            ['title' => 'Laravel Security Best Practices', 'genre' => 'Cybersecurity', 'stock' => 5, 'published' => rand(0,1)],
            ['title' => 'Laravel Performance Optimization', 'genre' => 'Programming', 'stock' => 3, 'published' => rand(0,1)],
            ['title' => 'Laravel Queue & Jobs', 'genre' => 'Programming', 'stock' => 7, 'published' => rand(0,1)],
            ['title' => 'Laravel Event & Listener', 'genre' => 'Programming', 'stock' => 6, 'published' => rand(0,1)],
            ['title' => 'Laravel Broadcasting', 'genre' => 'Web Development', 'stock' => 4, 'published' => rand(0,1)],
            ['title' => 'Laravel Eloquent ORM Deep Dive', 'genre' => 'Database', 'stock' => 12, 'published' => rand(0,1)],
            ['title' => 'Laravel Relationships Explained', 'genre' => 'Database', 'stock' => 8, 'published' => rand(0,1)],
            ['title' => 'Laravel Query Builder', 'genre' => 'Database', 'stock' => 5, 'published' => rand(0,1)],
            ['title' => 'Laravel Sanctum Authentication', 'genre' => 'Cybersecurity', 'stock' => 9, 'published' => rand(0,1)],
            ['title' => 'Laravel Passport OAuth2', 'genre' => 'Cybersecurity', 'stock' => 3, 'published' => rand(0,1)],
            ['title' => 'Laravel Blade Templating', 'genre' => 'Web Development', 'stock' => 7, 'published' => rand(0,1)],
            ['title' => 'Laravel Livewire Fundamentals', 'genre' => 'Web Development', 'stock' => 10, 'published' => rand(0,1)],
            ['title' => 'Laravel Filament Admin Panel', 'genre' => 'Web Development', 'stock' => 6, 'published' => rand(0,1)],
            ['title' => 'Laravel Inertia.js Integration', 'genre' => 'Web Development', 'stock' => 4, 'published' => rand(0,1)],
            ['title' => 'Laravel Deployment Guide', 'genre' => 'DevOps', 'stock' => 8, 'published' => rand(0,1)],
            ['title' => 'Laravel Docker Setup', 'genre' => 'DevOps', 'stock' => 5, 'published' => rand(0,1)],
            ['title' => 'Laravel with AWS', 'genre' => 'DevOps', 'stock' => 3, 'published' => rand(0,1)],
            ['title' => 'Laravel Microservices', 'genre' => 'Software Architecture', 'stock' => 6, 'published' => rand(0,1)],
            ['title' => 'Laravel Design Patterns', 'genre' => 'Software Architecture', 'stock' => 9, 'published' => rand(0,1)],
            ['title' => 'Laravel Service Container', 'genre' => 'Software Architecture', 'stock' => 7, 'published' => rand(0,1)],
            ['title' => 'Laravel Middleware Deep Dive', 'genre' => 'Programming', 'stock' => 4, 'published' => rand(0,1)],
            ['title' => 'Laravel Form Validation', 'genre' => 'Web Development', 'stock' => 11, 'published' => rand(0,1)],
            ['title' => 'Laravel File Storage', 'genre' => 'Programming', 'stock' => 5, 'published' => rand(0,1)],
            ['title' => 'Laravel Mail & Notifications', 'genre' => 'Programming', 'stock' => 8, 'published' => rand(0,1)],
            ['title' => 'Laravel Scheduling Tasks', 'genre' => 'Programming', 'stock' => 6, 'published' => rand(0,1)],
            ['title' => 'Laravel Caching Strategies', 'genre' => 'Database', 'stock' => 3, 'published' => rand(0,1)],
            ['title' => 'Laravel Logging & Debugging', 'genre' => 'Programming', 'stock' => 7, 'published' => rand(0,1)],
            ['title' => 'Laravel Horizon & Redis', 'genre' => 'Database', 'stock' => 4, 'published' => rand(0,1)],
            ['title' => 'Laravel Scout Full-Text Search', 'genre' => 'Database', 'stock' => 9, 'published' => rand(0,1)],
            ['title' => 'Laravel Telescope Debugging', 'genre' => 'Software Testing', 'stock' => 5, 'published' => rand(0,1)],
            ['title' => 'Laravel Multi-Tenancy', 'genre' => 'Software Architecture', 'stock' => 10, 'published' => rand(0,1)],
            ['title' => 'Laravel SaaS Application', 'genre' => 'Web Development', 'stock' => 6, 'published' => rand(0,1)],
            ['title' => 'Laravel E-Commerce Project', 'genre' => 'Web Development', 'stock' => 8, 'published' => rand(0,1)],
            ['title' => 'Laravel Blog Application', 'genre' => 'Web Development', 'stock' => 12, 'published' => rand(0,1)],
            ['title' => 'Laravel Social Media App', 'genre' => 'Web Development', 'stock' => 4, 'published' => rand(0,1)],
            ['title' => 'Laravel Real-Time Chat', 'genre' => 'Web Development', 'stock' => 7, 'published' => rand(0,1)],
            ['title' => 'Laravel GraphQL Integration', 'genre' => 'Web Development', 'stock' => 3, 'published' => rand(0,1)],
            ['title' => 'Laravel Clean Architecture', 'genre' => 'Software Architecture', 'stock' => 9, 'published' => rand(0,1)],
            ['title' => 'Laravel Repository Pattern', 'genre' => 'Software Architecture', 'stock' => 5, 'published' => rand(0,1)],
            ['title' => 'Laravel from Zero to Hero', 'genre' => 'Programming', 'stock' => 15, 'published' => rand(0,1)],
        ];

        $authorIds = DB::table('authors')->pluck('id')->toArray();

        foreach ($books as &$book) {
            $book['author_id'] = $authorIds[array_rand($authorIds)];
        }

        DB::table('books')->insert($books);
    }
}
