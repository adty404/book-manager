<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class GetBookByOptArgStock extends Command
{
    protected $signature = 'book:get-by-stock {stock? : Menampilkan buku berdasarkan stock} {title? : Menampilkan buku berdasarkan judul}';
    protected $description = 'Menampilkan buku berdasarkan stock yang ditentukan';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get argument
        $stock = $this->argument('stock');
        $title = $this->argument('title');

        $query = Book::query();
        if ($stock) {
            $query->where('stock', $stock);
        }
        if ($title) {
            $query->where('title', 'like', '%' . $title . '%');
        }

        $books = $query->get();

        if ($books->isEmpty()) {
            $this->error('Buku dengan stock ' . $stock . ' tidak ditemukan');
            return Command::FAILURE;
        }

        $this->table(
            ['ID', 'Judul', 'Penulis', 'Stock'],
            $books->map(fn($book) => [$book->id, $book->title, $book->author->name, $book->stock])
        );
    }
}
