<?php

namespace App\Jobs;

use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheWarmupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('cache-warmup');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Cache::remember('books.all', 1800, function () {
            return Book::with('author')->get();
        });
        Log::info("[CACHE WARMUP] Cache books.all sudah diisi.");
        Cache::remember('books.genre.Programming', 1800, function () {
            return Book::where('genre', 'Programming')->get();
        });
        Log::info("[CACHE WARMUP] Cache books.genre.Programming sudah diisi.");

        Cache::remember('books.genre.Fiction', 1800, function () {
            return Book::where('genre', 'Fiction')->get();
        });
        Log::info("[CACHE WARMUP] Cache books.genre.Fiction sudah diisi.");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[CACHE WARMUP FAILED] Error: {$exception->getMessage()}");
    }
}
