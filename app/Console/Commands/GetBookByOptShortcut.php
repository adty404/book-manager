<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class GetBookByOptShortcut extends Command
{
    protected $signature = 'book:list-shortcut {--S|min-stock=3} {--P|published}';
    protected $description = 'Menampilkan buku dengan shortcut option';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get option
        $minStock = $this->option('min-stock');
        $published = $this->option('published');

        $query = Book::query();

        if ($minStock) {
            $query->where('stock', '>=', $minStock);
        }

        if ($published) {
            $query->where('published', true);
        }

        $books = $query->get();

        $this->info('Menampilkan buku dengan stock minimal ' . $minStock . ($published ? ' dan sudah dipublikasikan' : ''));

        $this->table(
            ['ID', 'Judul', 'Penulis', 'Genre', 'Stock', 'Published'],
            $books->map(fn($book) => [$book->id, $book->title, $book->author->name, $book->genre, $book->stock, $book->published ? 'published' : 'not published'])
        );
    }
}
