<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class GetBookByDefaultOptMinStock extends Command
{
    protected $signature = 'book:list-min-stock {--min-stock=3 : Menampilkan buku dengan stock minimal tertentu}';
    protected $description = 'Menampilkan buku dengan stock minimal tertentu';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get option
        $minStock = $this->option('min-stock');

        $books = Book::where('stock', '>=', $minStock)->get();

        $this->info('Menampilkan buku dengan stock minimal ' . $minStock);

        $this->table(
            ['ID', 'Judul', 'Penulis', 'Genre', 'Stock', 'Published'],
            $books->map(fn($book) => [$book->id, $book->title, $book->author->name, $book->genre, $book->stock, $book->published ? 'published' : 'not published'])
        );
    }
}
