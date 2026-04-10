<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class ShowBookDetail extends Command
{
    protected $signature = 'book:detail {title : Menampilkan detail buku berdasarkan judul}';
    protected $description = 'Menampilkan detail buku berdasarkan judul yang diberikan';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $title = $this->argument('title');
        $this->info('Menampilkan detail buku dengan judul: ' . $title);

        $books = Book::where('title', 'like', '%' . $title . '%')->with('author')->get();

        $this->table(
            ['Judul', 'Penulis', 'Stock'],
            $books->map(fn($book) => [$book->title, $book->author->name, $book->stock])
        );

        // if ($book) {
        //     $this->line('Judul: ' . $book->title);
        //     $this->line('Penulis: ' . $book->author->name);
        //     $this->line('Stock: ' . $book->stock);
        // } else {
        //     $this->warn('Buku dengan judul ' . $title . ' tidak ditemukan.');
        // }
    }
}
