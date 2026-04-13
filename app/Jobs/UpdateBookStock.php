<?php

namespace App\Jobs;

use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateBookStock implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $bookId, public int $newStock)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $book = Book::find($this->bookId);

        if ($book) {
            $book->stock = $this->newStock;
            $book->save();

            Log::info("[STOCK UPDATE] '" . $book->title . "' -> stock: {$this->newStock}");
        } else {
            throw new \Exception("Book not found: {$this->bookId}");
        }
    }

    public function backoff(): array
    {
        return [5,10,15];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[STOCK UPDATE FAILED] Book ID: {$this->bookId} - Error: {$exception->getMessage()}");
    }
}
