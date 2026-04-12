# Laravel Queue — Interview Preparation (book-manager)

> Semua contoh kode di bawah **bisa langsung dicoba** di project book-manager.
> Model yang dipakai: `Book` (title, genre, stock, published, author_id), `Author` (name, email).

---

## 0. Setup Queue (Project ini)

### Cek driver saat ini

```bash
php artisan tinker
> config('queue.default');   # → "database"
```

Project ini sudah pakai driver `database`. Tabel `jobs`, `job_batches`, `failed_jobs` sudah ada dari migration bawaan Laravel.

### Perbandingan Driver

| Driver     | Kecepatan | Persisten | Use Case                                              |
| ---------- | --------- | --------- | ----------------------------------------------------- |
| `sync`     | Instant   | -         | Development / debug (tidak ada queue, langsung jalan) |
| `database` | Sedang    | Ya        | **Project ini** — belum setup Redis                   |
| `redis`    | **Cepat** | Ya        | **Production (recommended)**                          |
| `sqs`      | Cepat     | Ya        | AWS environment, serverless                           |

### Ganti driver (opsional)

```env
# .env
QUEUE_CONNECTION=sync       # langsung jalan, tanpa queue (debug)
QUEUE_CONNECTION=database   # ← default project ini
QUEUE_CONNECTION=redis      # butuh: composer require predis/predis
```

---

## 1. Konsep Dasar Queue

Queue = antrian pekerjaan yang dijalankan **di background**, bukan saat request berlangsung.

```
Tanpa queue (sync):
  POST /api/authors → Simpan → Kirim Email (3 detik) → Response   ← user nunggu

Dengan queue:
  POST /api/authors → Simpan → Masuk Queue → Response (instant!)
                                    ↓
                       Worker → Kirim Email (background)
```

### Kapan Pakai Queue di book-manager?

| Pakai Queue ✅                        | Jangan Pakai Queue ❌            |
| ------------------------------------- | -------------------------------- |
| Kirim welcome email ke author baru    | GET /api/books (baca data)       |
| Export semua buku ke CSV              | POST /api/books (simpan 1 buku)  |
| Generate laporan author + jumlah buku | PUT /api/books/1 (update 1 buku) |
| Bulk update stock semua buku          | DELETE /api/books/1              |
| Kirim notifikasi ke semua author      |                                  |

---

## 2. Membuat & Dispatch Job

### 2.1 Job yang Sudah Ada: `SendWelcomeEmail`

File `app/Jobs/SendWelcomeEmail.php` sudah ada di project:

```php
<?php

namespace App\Jobs;

use App\Models\Author;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue  // ← implements ShouldQueue = masuk queue
{
    use Queueable;

    public function __construct(public Author $author) {}

    public function handle(): void
    {
        Mail::to($this->author->email)->send(new \App\Mail\WelcomeEmail($this->author));
    }
}
```

**Penting:** `implements ShouldQueue` → job masuk queue. Tanpa ini, job jalan langsung (sync).

### 2.2 Cara Dispatch

```php
use App\Jobs\SendWelcomeEmail;

// Dispatch ke default queue
SendWelcomeEmail::dispatch($author);

// Dispatch ke queue tertentu
SendWelcomeEmail::dispatch($author)->onQueue('emails');

// Dispatch dengan delay 5 menit
SendWelcomeEmail::dispatch($author)->delay(now()->addMinutes(5));

// Dispatch ke connection tertentu
SendWelcomeEmail::dispatch($author)->onConnection('redis');

// Dispatch langsung (tanpa queue, bypass ShouldQueue)
SendWelcomeEmail::dispatchSync($author);
```

### 2.3 Sudah Dipakai di AuthorController

File `app/Http/Controllers/AuthorController.php`:

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:authors,email',
    ]);

    $author = Author::create($validated);

    // ← Job di-dispatch di sini, user tidak perlu menunggu email terkirim
    SendWelcomeEmail::dispatch($author);

    return response()->json([
        'message' => 'Author created successfully.',
        'data' => $author,
    ]);
}
```

### 📌 Coba Sendiri: Dispatch via Tinker

```bash
php artisan tinker
> $author = \App\Models\Author::first();
> \App\Jobs\SendWelcomeEmail::dispatch($author);
# Job masuk tabel `jobs` (cek di DB)

> \App\Jobs\SendWelcomeEmail::dispatch($author)->delay(now()->addSeconds(30));
# Job masuk queue, tapi baru diproses 30 detik kemudian
```

---

## 3. Menjalankan Queue Worker

### 3.1 Coba Jalankan

```bash
# Terminal 1 — Jalankan worker
php artisan queue:work

# Terminal 2 — Dispatch job
php artisan tinker
> $author = \App\Models\Author::first();
> \App\Jobs\SendWelcomeEmail::dispatch($author);

# Di Terminal 1, akan muncul:
#   [2026-04-13 10:00:00] Processing: App\Jobs\SendWelcomeEmail
#   [2026-04-13 10:00:01] Processed:  App\Jobs\SendWelcomeEmail
# (akan error karena belum setup Mail, tapi kamu bisa lihat job diproses)
```

### 3.2 Opsi Worker

```bash
php artisan queue:work --queue=emails,default  # prioritas: emails dulu
php artisan queue:work --tries=3               # max 3 percobaan
php artisan queue:work --timeout=60            # max 60 detik per job
php artisan queue:work --sleep=3               # tunggu 3 detik jika queue kosong
php artisan queue:work --max-jobs=100          # stop setelah 100 job
php artisan queue:work --once                  # proses 1 job lalu stop
```

### 3.3 `queue:work` vs `queue:listen`

| Command        | Kecepatan | Auto-reload code? | Use Case                                 |
| -------------- | --------- | ----------------- | ---------------------------------------- |
| `queue:work`   | **Cepat** | Tidak             | **Production**                           |
| `queue:listen` | Lambat    | Ya                | Development (auto-detect perubahan kode) |

```bash
# Saat development, pakai listen agar kode terbaru langsung dipakai:
php artisan queue:listen

# Production — setelah deploy, restart worker:
php artisan queue:restart
```

---

## 4. Job Retry & Error Handling

### 4.1 Buat Job Baru: `ExportBooksToCSV`

```bash
php artisan make:job ExportBooksToCSV
```

Edit `app/Jobs/ExportBooksToCSV.php`:

```php
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

    public int $tries = 3;       // max 3 percobaan
    public int $timeout = 120;   // max 120 detik per percobaan
    public int $backoff = 10;    // tunggu 10 detik sebelum retry

    // Atau backoff bertahap:
    // public function backoff(): array { return [10, 30, 60]; }

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

    // Dipanggil jika SEMUA retry gagal
    public function failed(\Throwable $e): void
    {
        Log::error("Export gagal: {$this->filename} — {$e->getMessage()}");
    }
}
```

### 📌 Coba Sendiri: Dispatch & Lihat Hasil

```bash
# Terminal 1 — Jalankan worker
php artisan queue:work --tries=3

# Terminal 2 — Dispatch
php artisan tinker
> \App\Jobs\ExportBooksToCSV::dispatch('semua-buku.csv');
> \App\Jobs\ExportBooksToCSV::dispatch('buku-fiction.csv', 'fiction');

# Cek hasil:
> \Illuminate\Support\Facades\Storage::get('exports/semua-buku.csv');
```

### 4.2 Retry Failed Jobs dari CLI

```bash
php artisan queue:failed                 # lihat daftar failed jobs
php artisan queue:retry 5                # retry job ID 5
php artisan queue:retry all              # retry semua
php artisan queue:forget 5               # hapus failed job ID 5
php artisan queue:flush                  # hapus semua failed jobs
```

### 4.3 `retryUntil()` — Retry Berdasarkan Waktu

```php
class ExportBooksToCSV implements ShouldQueue
{
    use Queueable;

    // Tidak pakai $tries, tapi retry selama 30 menit dari dispatch
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }
}
```

---

## 5. Dispatch dari Controller + Route

### 5.1 Tambah Method `export` di BookController

Tambahkan di `app/Http/Controllers/BookController.php`:

```php
use App\Jobs\ExportBooksToCSV;

// Tambahkan method ini di BookController
public function export(Request $request): JsonResponse
{
    $filename = 'books-' . now()->format('Y-m-d-His') . '.csv';

    ExportBooksToCSV::dispatch($filename, $request->genre)
        ->onQueue('exports');

    return response()->json([
        'message' => 'Export sedang diproses di background',
        'filename' => $filename,
    ], 202);   // 202 Accepted = "diterima, sedang diproses"
}
```

### 5.2 Tambah Route

Di `routes/api.php`:

```php
Route::post('books/export', [BookController::class, 'export']);
```

### 📌 Coba Sendiri

```bash
# Terminal 1 — Jalankan worker
php artisan queue:work --queue=exports,default

# Terminal 2 — Hit API
curl -X POST http://127.0.0.1:8000/api/books/export
# → {"message":"Export sedang diproses di background","filename":"books-2026-04-13-100000.csv"}

curl -X POST http://127.0.0.1:8000/api/books/export?genre=fiction
# → export hanya buku fiction

# Cek file hasil
cat storage/app/exports/books-2026-04-13-100000.csv
```

---

## 6. Queue Priority (Multiple Queue)

### 6.1 Contoh: Email vs Export

```php
// Di AuthorController::store — masuk queue 'emails'
SendWelcomeEmail::dispatch($author)->onQueue('emails');

// Di BookController::export — masuk queue 'exports'
ExportBooksToCSV::dispatch($filename, $genre)->onQueue('exports');
```

```bash
# Worker prioritas: emails dulu baru exports
php artisan queue:work --queue=emails,exports,default

# Semua job di queue 'emails' diproses duluan sampai kosong,
# baru ambil dari 'exports', baru 'default'
```

### 6.2 Atau Set Langsung di Job Class

```php
class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public string $queue = 'emails';   // selalu masuk queue 'emails'
}
```

### 📌 Coba Sendiri

```bash
# Terminal 1 — worker
php artisan queue:work --queue=emails,exports,default

# Terminal 2
php artisan tinker
> $author = \App\Models\Author::first();
> \App\Jobs\SendWelcomeEmail::dispatch($author)->onQueue('emails');
> \App\Jobs\ExportBooksToCSV::dispatch('test.csv')->onQueue('exports');

# Lihat di Terminal 1 — SendWelcomeEmail diproses duluan
```

---

## 7. Job Middleware

### 7.1 Buat Job: `UpdateBookStock`

```bash
php artisan make:job UpdateBookStock
```

Edit `app/Jobs/UpdateBookStock.php`:

```php
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

    // Middleware: cegah 2 job update buku yang sama jalan bersamaan
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
```

### 📌 Coba Sendiri

```bash
# Terminal 1 — worker
php artisan queue:work

# Terminal 2
php artisan tinker
> \App\Jobs\UpdateBookStock::dispatch(1, 5);   # buku ID 1, tambah 5
> \App\Jobs\UpdateBookStock::dispatch(1, 3);   # buku ID 1, tambah 3
# WithoutOverlapping memastikan kedua job ini TIDAK jalan bersamaan

> \App\Models\Book::find(1)->stock;   # cek stock terbaru
```

### 7.2 Rate Limiting (Opsional)

Di `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('exports', fn () => Limit::perMinute(3));
}
```

Di job:

```php
use Illuminate\Queue\Middleware\RateLimited;

public function middleware(): array
{
    return [new RateLimited('exports')];  // max 3 export per menit
}
```

---

## 8. Job Batching

### 8.1 Buat Job: `NotifyAuthor`

```bash
php artisan make:job NotifyAuthor
```

Edit `app/Jobs/NotifyAuthor.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Author;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyAuthor implements ShouldQueue
{
    use Queueable, Batchable;   // ← Batchable wajib untuk batching

    public function __construct(
        public Author $author,
        public string $message
    ) {}

    public function handle(): void
    {
        // Cek jika batch sudah di-cancel
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Simulasi kirim notifikasi
        Log::info("Notifikasi ke {$this->author->name} ({$this->author->email}): {$this->message}");
    }
}
```

### 8.2 Dispatch Batch

```php
use App\Jobs\NotifyAuthor;
use App\Models\Author;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

// Kirim notifikasi ke SEMUA author
$jobs = Author::all()->map(function ($author) {
    return new NotifyAuthor($author, 'Terima kasih sudah bergabung!');
});

$batch = Bus::batch($jobs)
    ->then(function (Batch $batch) {
        Log::info("Semua notifikasi terkirim! Total: {$batch->totalJobs}");
    })
    ->catch(function (Batch $batch, \Throwable $e) {
        Log::error("Ada notifikasi gagal: {$e->getMessage()}");
    })
    ->finally(function (Batch $batch) {
        Log::info("Batch selesai. Sukses: {$batch->processedJobs()}, Gagal: {$batch->failedJobs}");
    })
    ->name('Notify All Authors')
    ->onQueue('emails')
    ->dispatch();

// Cek progress
$batch->progress();      // 0-100 (persen)
$batch->totalJobs;       // 10 (jumlah author)
$batch->pendingJobs;     // yang masih antri
$batch->finished();      // true / false
```

### 📌 Coba Sendiri

```bash
# Terminal 1
php artisan queue:work --queue=emails,default

# Terminal 2
php artisan tinker
> use App\Jobs\NotifyAuthor;
> use App\Models\Author;
> use Illuminate\Support\Facades\Bus;

> $jobs = Author::all()->map(fn ($a) => new NotifyAuthor($a, 'Hello!'));
> $batch = Bus::batch($jobs)->name('Test Batch')->dispatch();
> $batch->progress();
> $batch->finished();

# Di Terminal 1, lihat 10 job diproses satu per satu
# Cek log: cat storage/logs/laravel.log | grep "Notifikasi ke"
```

---

## 9. Job Chaining

### 9.1 Buat Job: `GenerateAuthorReport`

```bash
php artisan make:job GenerateAuthorReport
```

Edit `app/Jobs/GenerateAuthorReport.php`:

```php
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
```

### 9.2 Chain: Export Books → Generate Author Report → Log Selesai

```php
use App\Jobs\ExportBooksToCSV;
use App\Jobs\GenerateAuthorReport;
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new ExportBooksToCSV('books-full.csv'),               // ① export buku dulu
    new GenerateAuthorReport('authors-report.csv'),        // ② baru report author
    fn () => Log::info('Semua report selesai di-generate!'), // ③ closure terakhir
])->onQueue('exports')->dispatch();

// Kalau job ① gagal → ② dan ③ TIDAK dijalankan
```

### 📌 Coba Sendiri

```bash
# Terminal 1
php artisan queue:work --queue=exports,default

# Terminal 2
php artisan tinker
> use Illuminate\Support\Facades\Bus;
> Bus::chain([
>     new \App\Jobs\ExportBooksToCSV('chain-books.csv'),
>     new \App\Jobs\GenerateAuthorReport('chain-authors.csv'),
> ])->dispatch();

# Cek hasil berurutan:
> \Illuminate\Support\Facades\Storage::exists('exports/chain-books.csv');
> \Illuminate\Support\Facades\Storage::exists('reports/chain-authors.csv');
```

---

## 10. Event: Before / After Job

Tambahkan di `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

public function boot(): void
{
    Event::listen(JobProcessing::class, function ($event) {
        Log::info("⏳ Job dimulai: {$event->job->getName()}");
    });

    Event::listen(JobProcessed::class, function ($event) {
        Log::info("✅ Job selesai: {$event->job->getName()}");
    });

    Event::listen(JobFailed::class, function ($event) {
        Log::error("❌ Job gagal: {$event->job->getName()} — {$event->exception->getMessage()}");
    });
}
```

### 📌 Coba Sendiri

```bash
# Restart worker (agar kode baru ke-load)
php artisan queue:restart
php artisan queue:work

# Dispatch job apapun, lalu cek log:
tail -f storage/logs/laravel.log
# Akan muncul: ⏳ Job dimulai... ✅ Job selesai...
```

---

## 11. Artisan Commands — Quick Reference

```bash
# === WORKER ===
php artisan queue:work                        # jalankan worker
php artisan queue:work --queue=emails,default # prioritas queue
php artisan queue:work --tries=3              # max retry
php artisan queue:work --timeout=60           # timeout per job
php artisan queue:work --once                 # proses 1 job lalu stop
php artisan queue:listen                      # development (auto-reload)
php artisan queue:restart                     # restart worker (setelah deploy/ubah kode)

# === FAILED JOBS ===
php artisan queue:failed                      # lihat daftar failed jobs
php artisan queue:retry 5                     # retry job ID 5
php artisan queue:retry all                   # retry semua
php artisan queue:forget 5                    # hapus failed job ID 5
php artisan queue:flush                       # hapus semua failed jobs

# === MONITORING ===
php artisan queue:monitor database:default    # monitor queue size
php artisan queue:clear                       # hapus semua pending jobs (hati-hati!)

# === GENERATE ===
php artisan make:job NamaJob
```

---

## 12. Production: Supervisor

Di production, worker harus jalan terus. Gunakan **Supervisor** untuk auto-restart:

```ini
# /etc/supervisor/conf.d/laravel-worker.conf

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/book-manager/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/book-manager/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
sudo supervisorctl status
```

**`numprocs=4`** = 4 worker berjalan paralel → 4 job diproses bersamaan.

---

## 13. Testing Job (Pest)

File: `tests/Feature/QueueTest.php`

```php
<?php

use App\Jobs\SendWelcomeEmail;
use App\Jobs\ExportBooksToCSV;
use App\Models\Author;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('store author dispatches SendWelcomeEmail job', function () {
    Queue::fake();

    $this->postJson('/api/authors', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    Queue::assertPushed(SendWelcomeEmail::class, function ($job) {
        return $job->author->email === 'john@example.com';
    });
});

test('export books dispatches ExportBooksToCSV on exports queue', function () {
    Queue::fake();

    $this->postJson('/api/books/export');

    Queue::assertPushedOn('exports', ExportBooksToCSV::class);
});

test('nothing dispatched if author validation fails', function () {
    Queue::fake();

    $this->postJson('/api/authors', []);   // tanpa name & email

    Queue::assertNothingPushed();
});

test('ExportBooksToCSV creates CSV file', function () {
    Storage::fake('local');

    // Buat data test
    $author = Author::factory()->create(['name' => 'Test Author']);
    $author->books()->create([
        'title' => 'Test Book', 'genre' => 'fiction', 'stock' => 10, 'published' => true,
    ]);

    // Jalankan job langsung (test logic handle, bukan queue)
    $job = new ExportBooksToCSV('test.csv');
    $job->handle();

    Storage::assertExists('exports/test.csv');
    $content = Storage::get('exports/test.csv');
    expect($content)->toContain('Test Book');
    expect($content)->toContain('Test Author');
});
```

### 📌 Coba Sendiri

```bash
php artisan test --filter=Queue
```

---

## 14. Ringkasan: Semua Job di Project Ini

| Job                    | File                                | Fungsi                                 | Queue     |
| ---------------------- | ----------------------------------- | -------------------------------------- | --------- |
| `SendWelcomeEmail`     | `app/Jobs/SendWelcomeEmail.php`     | Kirim email ke author baru             | `emails`  |
| `ExportBooksToCSV`     | `app/Jobs/ExportBooksToCSV.php`     | Export buku ke CSV file                | `exports` |
| `UpdateBookStock`      | `app/Jobs/UpdateBookStock.php`      | Update stock buku (WithoutOverlapping) | `default` |
| `NotifyAuthor`         | `app/Jobs/NotifyAuthor.php`         | Notifikasi ke author (Batchable)       | `emails`  |
| `GenerateAuthorReport` | `app/Jobs/GenerateAuthorReport.php` | Generate laporan author + jumlah buku  | `exports` |

### Jalankan Semua Queue Sekaligus

```bash
php artisan queue:work --queue=emails,exports,default
```

---

## 15. Pertanyaan Interview + Jawaban

### Q1: Apa itu Queue dan kenapa butuh Queue?

**A:** Queue adalah antrian pekerjaan yang dijalankan di **background**. Contoh di project ini: saat create author, kita dispatch `SendWelcomeEmail` agar user tidak menunggu proses kirim email. User langsung dapat response, email dikirim oleh worker.

### Q2: Apa perbedaan `queue:work` vs `queue:listen`?

**A:** `queue:work` load app sekali lalu loop — **lebih cepat**, cocok production. `queue:listen` spawn proses baru tiap job — **lebih lambat** tapi auto-reload kode, cocok development. Setelah deploy di production, harus jalankan `queue:restart`.

### Q3: Apa yang terjadi jika job gagal?

**A:** Job di-retry sesuai `$tries`. Contoh: `ExportBooksToCSV` punya `$tries = 3`. Jika 3x gagal, masuk tabel `failed_jobs` dan method `failed()` dipanggil (bisa log error, kirim notifikasi ke admin). Retry manual: `php artisan queue:retry all`.

### Q4: Bagaimana handle job yang tidak boleh jalan bersamaan?

**A:** Pakai middleware `WithoutOverlapping`. Contoh di `UpdateBookStock` — kita lock berdasarkan `$bookId` agar 2 update stock buku yang sama tidak race condition:

```php
public function middleware(): array
{
    return [new WithoutOverlapping($this->bookId)];
}
```

### Q5: Apa bedanya `dispatch()` vs `dispatchSync()`?

**A:** `dispatch()` masukkan ke queue (background). `dispatchSync()` jalankan langsung tanpa queue. Berguna saat testing atau kasus yang harus sinkron.

### Q6: Bagaimana prioritas queue bekerja?

**A:** Parameter `--queue` menentukan urutan. Contoh di project ini:

```bash
php artisan queue:work --queue=emails,exports,default
```

Worker proses `emails` (SendWelcomeEmail) dulu sampai kosong, baru `exports` (ExportBooksToCSV), baru `default`.

### Q7: Apa itu Job Batching dan contohnya?

**A:** Mengelompokkan banyak job dan track progress. Contoh: kirim `NotifyAuthor` ke semua 10 author sekaligus. Bisa track berapa yang sudah selesai, berapa yang gagal, dan jalankan callback saat semua selesai.

### Q8: Apa itu Job Chaining?

**A:** Job berurutan. Contoh di project ini: `ExportBooksToCSV` → `GenerateAuthorReport`. Report author baru jalan setelah export buku selesai. Kalau export gagal, report **tidak** dijalankan.

### Q9: Bagaimana queue di production?

**A:** Pakai **Supervisor** untuk menjaga worker tetap jalan dan auto-restart jika crash. Set `numprocs` untuk paralel worker. Gunakan Redis untuk kecepatan. Setelah deploy, jalankan `queue:restart`.

### Q10: Apa perbedaan driver `sync` vs `database` vs `redis`?

**A:**

- `sync` — langsung jalan, tidak ada queue. Untuk debug
- `database` — disimpan di tabel `jobs`. Mudah setup (project ini pakai ini)
- `redis` — disimpan di RAM. Lebih cepat, recommended production

### Q11: Bagaimana testing job di Laravel?

**A:** Pakai `Queue::fake()` untuk intercept dispatch. Assert: `Queue::assertPushed()`, `Queue::assertPushedOn()`, `Queue::assertNothingPushed()`. Untuk test logic, panggil `$job->handle()` langsung tanpa queue.

### Q12: Apa itu `retry_after` di config queue?

**A:** Waktu (detik) sebelum job yang stuck dikembalikan ke queue. Default 90 detik. Harus lebih besar dari `$timeout` di job. Contoh: jika `$timeout = 120`, set `retry_after` minimal 130, agar job yang masih running tidak di-retry prematur.
