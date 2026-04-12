<?php

namespace App\Jobs;

use App\Models\Author;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateAuthorReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $filename) {}

    public function handle(): void
    {
        $authors = Author::withCount('books')->get();
        $csv = "ID,Name,Email,Total Books\n";

        foreach ($authors as $author) {
            $csv .= "{$author->id},{$author->name},{$author->email},{$author->books_count}\n";
        }

        Storage::put("reports/{$this->filename}", $csv);

        Log::info("Author report selesai: {$this->filename}");
    }
}
