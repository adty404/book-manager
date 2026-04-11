<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class GetBookByOptBoolFlagPublished extends Command
{
    protected $signature = 'book:get-by-published {--published : Menampilkan buku published/not published}';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get option
        $published = $this->option('published');

        if ($published) {
            $books = Book::where('published', 1)->get();
        } else {
            $books = Book::where('published', 0)->get();
        }

        $this->info('Menampilkan buku yang ' . ($published ?  'published' : 'belum published'));

        $this->table(
            ['ID', 'Judul', 'Penulis', 'Genre', 'Stock', 'Published'],
            $books->map(fn($book) => [$book->id, $book->title, $book->author->name, $book->genre, $book->stock, $book->published ? 'published' : 'not published'])
        );
    }
}
