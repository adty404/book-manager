<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class FindBookByArgTitle extends Command
{
    protected $signature = 'book:find {title : Mencari buku berdasarkan judul}';
    protected $description = 'Mencari buku berdasarkan judul yang ditentukan';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $title = $this->argument('title');

        $books = Book::where('title', 'like', '%' . $title . '%')->get();

        if ($books->isEmpty()) {
            $this->error('Buku dengan judul "' . $title . '" tidak ditemukan.');
            return Command::FAILURE;
        }

        // table
        $this->table(
            ['Judul', 'Penulis', 'Stock'],
            $books->map(fn($book) => [$book->title, $book->author->name, $book->stock])
        );
    }
}
