<?php

namespace App\Jobs;

use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class UpdateBookStock implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $bookId,
        public int $quantity
    ) {}

    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->bookId),
        ];
    }

    public function handle(): void
    {
        $book = Book::findOrFail($this->bookId);
        $book->increment('stock', $this->quantity);

        Log::info("Stock buku '{$book->title}' ditambah {$this->quantity}, sekarang: {$book->stock}");
    }
}
