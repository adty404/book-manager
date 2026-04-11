<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class GetBookByDefaultArg extends Command
{
    protected $signature = 'book:get-by-genre {genre=all : Menampilkan buku berdasarkan genre}';
    protected $description = 'Menampilkan buku berdasarkan genre yang dipilih';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get argument
        $genre = $this->argument('genre');

        if ($genre === "all") {
            $books = Book::all();
        } else {
            $books = Book::where('genre', 'like', '%' . $genre . '%')->get();
        }

        if ($books->isEmpty()) {
            $this->error('Buku dengan genre ' . $genre . ' tidak ada');
            return Command::FAILURE;
        }

        $this->table(
            ['ID', 'Judul', 'Penulis', 'Genre', 'Stock', 'Published'],
            $books->map(fn ($book) => [$book->id, $book->title, $book->author->name, $book->genre, $book->stock, $book->published ? 'published' : 'not published'])
        );
    }
}
