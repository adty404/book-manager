<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class DeleteBookByArrArgIDs extends Command
{
    protected $signature = 'book:delete-by-id {ids* : Menghapus buku berdasarkan ID}';
    protected $description = 'Menghapus buku berdasarkan ID yang ditentukan';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get argument
        $ids = $this->argument('ids');

        Book::destroy($ids);

        $this->info('books with id ' . implode(', ', $ids) . ' are all deleted successfully');
    }
}
