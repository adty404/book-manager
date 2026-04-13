<?php

namespace App\Console\Commands;

use App\Jobs\ExportBooksToCSV;
use Illuminate\Console\Command;

class RunQueueExportBooksToCSV extends Command
{
    protected $signature = 'queue:export-books-csv {filename} {genre?}';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get arguments
        $filename = $this->argument('filename');
        $genre = $this->argument('genre');

        if ($genre) {
            ExportBooksToCSV::dispatch($filename, $genre);
            $this->info("Job untuk mengekspor buku dengan genre '{$genre}' ke file '{$filename}' telah dijadwalkan.");
        } else {
            ExportBooksToCSV::dispatch($filename);
            $this->info("Job untuk mengekspor semua buku ke file '{$filename}' telah dijadwalkan.");
        }
    }
}
