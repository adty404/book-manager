<?php

namespace App\Jobs;

use App\Models\Author;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateAuthorReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct() {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $authors = Author::with('books')->get();

        foreach ($authors as $author) {
            $reportLog = "[REPORT] {$author->name} - {$author->books->count()} buku, total stock: {$author->books->sum('stock')}";
            Log::info($reportLog);
        }

        $summary = $authors->map(fn ($a) => [
            'name' => $a->name,
            'total_books' => $a->books->count(),
            'total_stock' => $a->books->sum('stock'),
        ])->toArray();

        Cache::put('report.authors', $summary, 3600);

        Log::info("[REPORT] Summary cached ke report.authors");
    }
}
