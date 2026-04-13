<?php

namespace App\Jobs;

use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateLowStockReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('reports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $books = Book::where('stock', '<', 5)->get();

        foreach ($books as $book) {
            Log::warning("Stok rendah: {$book->title} (ID: {$book->id}, Stock: {$book->stock})");
        }
    }
}
