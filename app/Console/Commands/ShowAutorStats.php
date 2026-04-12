<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Author;

class ShowAutorStats extends Command
{
    protected $signature = 'book:stats {--min-books=0 : Hanya tampilkan author dengan minimal buku tertentu}';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minBooks = $this->option('min-books');

        $authors = Author::withCount('books')->having('books_count', '>=', $minBooks)->get();

        if ($authors->isEmpty()) {
            $this->warn("Tidak ada data author yang memenuhi kriteria");
            return;
        }

        $headers = ['Nama Author', 'Jumlah Buku'];
        $data = $authors->map(fn($author) => [$author->name, $author->books_count]);

        $this->info("Menampilkan Statistik Author: ");
        $this->table($headers, $data);
    }
}
