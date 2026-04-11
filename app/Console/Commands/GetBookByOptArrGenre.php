<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class GetBookByOptArrGenre extends Command
{
    protected $signature = 'book:list-opt-arr-genre {--genre=* : Menampilkan buku berdasarkan genre (bisa banyak)}';
    protected $description = 'Menampilkan buku berdasarkan genre (bisa banyak)';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get option
        $genres = $this->option('genre');

        if (count($genres) > 0) {
            $books = Book::whereIn('genre', $genres)->get();
        } else {
            $books = Book::all();
        }

        $this->info('Menampilkan ' . (count($genres) == 0 ? 'semua' : '') . 'buku' . (count($genres) == 0 ? '' : 'dengan genre ') . implode(', ', $genres));

        $this->table(
            ['ID', 'Judul', 'Penulis', 'Genre', 'Stock', 'Published'],
            $books->map(fn($book) => [$book->id, $book->title, $book->author->name, $book->genre, $book->stock, $book->published ? 'published' : 'not published'])
        );
    }
}
