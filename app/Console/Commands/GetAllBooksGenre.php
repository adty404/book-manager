<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class GetAllBooksGenre extends Command
{
    protected $signature = 'book:get-all-genre';
    protected $description = 'Menampilkan semua genre buku yang tersedia';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Menampilkan semua genre Buku');

        $books = Book::distinct()->pluck('genre');

        $this->table(
            ['Genre'],
            $books->map(fn($book) => [$book])
        );
    }
}
