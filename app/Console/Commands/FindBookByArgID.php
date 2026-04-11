<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class FindBookByArgID extends Command
{
    protected $signature = 'book:find-by-id {id : Menampilkan buku berdasarkan ID}';
    protected $description = 'Menampilkan buku berdasarkan ID yang ditentukan';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get argument
        $ID = $this->argument('id');

        // get book
        $book = Book::find($ID);

        if (!$book) {
            $this->error('Buku dengan ID ' . $ID . 'tidak ditemukan');
            return Command::FAILURE;
        }

        $this->table(
            ['ID', 'Judul', 'Penulis', 'Stock'],
            [
                [$book->id, $book->title, $book->author->name, $book->stock]
            ]
        );
    }
}
