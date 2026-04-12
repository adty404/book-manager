<?php

namespace App\Jobs;

use App\Models\Author;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyAuthor implements ShouldQueue
{
    use Queueable, Batchable;

    public function __construct(
        public Author $author,
        public string $message
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        Log::info("Notifikasi ke {$this->author->name} ({$this->author->email}): {$this->message}");
    }
}
