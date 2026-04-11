<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class GetBookByArrArgIDs extends Command
{
    protected $signature = 'book:find-by-ids {ids* : Menampilkan buku berdasarkan id}';
    protected $description = 'Menampilkan buku berdasarkan id yang ditentukan';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get argument
        $ids = $this->argument('ids');

        if (count($ids) > 0) {
            $books = Book::find($ids);
        } else {
            $books = Book::all();
        }

        $this->info('Menampilkan ' . (count($ids) == 0 ? 'semua' : '') . 'buku' . (count($ids) == 0 ? '' : 'dengan id ') . implode(', ', $ids));

        $this->table(
            ['ID', 'Judul', 'Penulis', 'Genre', 'Stock', 'Published'],
            $books->map(fn($book) => [$book->id, $book->title, $book->author->name, $book->genre, $book->stock, $book->published ? 'published' : 'not published'])
        );
    }
}
