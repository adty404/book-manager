<?php

namespace App\Jobs;

use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportBooksToCSV implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 10;

    public function __construct(
        public string $filename,
        public ?string $genre = null
    ) {}

    public function handle(): void
    {
        $query = Book::with('author');

        if ($this->genre) {
            $query->where('genre', $this->genre);
        }

        $books = $query->get();
        $csv = "ID,Title,Author,Genre,Stock,Published\n";

        foreach ($books as $book) {
            $csv .= implode(',', [
                $book->id,
                '"' . $book->title . '"',
                '"' . $book->author->name . '"',
                $book->genre,
                $book->stock,
                $book->published ? 'Yes' : 'No',
            ]) . "\n";
        }

        Storage::put("exports/{$this->filename}", $csv);

        Log::info("Export selesai: {$this->filename} ({$books->count()} buku)");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Export gagal: {$this->filename} — {$e->getMessage()}");
    }
}
