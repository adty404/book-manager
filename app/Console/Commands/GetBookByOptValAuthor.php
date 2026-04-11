<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class GetBookByOptValAuthor extends Command
{
    protected $signature = 'book:list-opt-author {--author= : Menampilkan buku berdasarkan author / penulis}';
    protected $description = 'Menampilkan buku berdasarkan penulis';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get option
        $author = $this->option('author');

        if ($author == "" || is_null($author)) {
            $books = Book::all();
        } else {
            $books = Book::whereHas('author', fn($query) => $query->where('name', 'like', '%' . $author . '%'))->get();
        }

        $this->table(
            ['ID', 'Judul', 'Penulis', 'Genre', 'Stock', 'Published'],
            $books->map(fn($book) => [$book->id, $book->title, $book->author->name, $book->genre, $book->stock, $book->published ? 'published' : 'not published'])
        );
    }
}
