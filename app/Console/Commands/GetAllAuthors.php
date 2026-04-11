<?php

namespace App\Console\Commands;

use App\Models\Author;
use Illuminate\Console\Command;

class GetAllAuthors extends Command
{
    protected $signature = 'author:get-all';
    protected $description = 'Menampilkan semua penulis yang ada di database';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $authors = Author::all()->pluck('name');

        $this->info('Menampilkan semua penulis yang ada di database:');

        $this->table(
            ['Nama Penulis'],
            $authors->map(fn($author) => [$author])
        );
    }
}
