# Laravel Queue — Interview Preparation

---

## 0. Setup Queue

### Driver `sync` — Development (Default)

Job langsung dijalankan **saat itu juga**, tidak masuk queue. Berguna untuk debug.

```env
QUEUE_CONNECTION=sync
```

### Driver `database` — Paling Mudah

```env
QUEUE_CONNECTION=database
```

```bash
php artisan migrate
# Membuat tabel: jobs, job_batches, failed_jobs
```

### Driver `redis` — Production

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

```bash
composer require predis/predis
```

### Perbandingan Driver

| Driver     | Kecepatan | Persisten | Use Case                                              |
| ---------- | --------- | --------- | ----------------------------------------------------- |
| `sync`     | Instant   | -         | Development / debug (tidak ada queue, langsung jalan) |
| `database` | Sedang    | Ya        | Belum setup Redis, app kecil-menengah                 |
| `redis`    | **Cepat** | Ya        | **Production (recommended)**                          |
| `sqs`      | Cepat     | Ya        | AWS environment, serverless                           |

### Verifikasi Setup

```bash
php artisan tinker
> config('queue.default');   # → "database" / "redis" / "sync"
```

---

## 1. Konsep Dasar Queue

### Apa itu Queue?

Queue = antrian pekerjaan yang dijalankan **di background**, bukan saat request berlangsung.

```
Tanpa queue:
  User Request → Kirim Email (3 detik) → Response    ← user menunggu 3 detik

Dengan queue:
  User Request → Masukkan ke Queue → Response (instant!)
                     ↓
              Worker ambil job → Kirim Email (background)
```

### Kapan Pakai Queue?

| Pakai Queue                | Jangan Pakai Queue                |
| -------------------------- | --------------------------------- |
| Kirim email / notifikasi   | Query DB biasa (cepat)            |
| Generate PDF / report      | Response yang user butuh langsung |
| Proses upload file besar   | Simple CRUD                       |
| Resize / compress gambar   | Autentikasi                       |
| Integrasi API pihak ketiga | Redirect                          |
| Import/export data CSV     |                                   |

---

## 2. Membuat & Dispatch Job

### 2.1 Buat Job

```bash
php artisan make:job SendWelcomeEmail
```

File dibuat di `app/Jobs/SendWelcomeEmail.php`:

```php
<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user
    ) {}

    public function handle(): void
    {
        Mail::to($this->user->email)->send(new \App\Mail\WelcomeMail($this->user));
    }
}
```

**Penting:** `implements ShouldQueue` → job masuk queue. Tanpa ini, job jalan langsung (sync).

### 2.2 Dispatch Job

```php
use App\Jobs\SendWelcomeEmail;

// Dispatch ke default queue
SendWelcomeEmail::dispatch($user);

// Dispatch ke queue tertentu
SendWelcomeEmail::dispatch($user)->onQueue('emails');

// Dispatch dengan delay
SendWelcomeEmail::dispatch($user)->delay(now()->addMinutes(5));

// Dispatch ke connection tertentu
SendWelcomeEmail::dispatch($user)->onConnection('redis');

// Dispatch langsung (tanpa queue, bypass ShouldQueue)
SendWelcomeEmail::dispatchSync($user);
```

### 2.3 Contoh: Dispatch dari Controller

```php
class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = User::create($request->validated());

        // Kirim email di background — user tidak perlu menunggu
        SendWelcomeEmail::dispatch($user);

        return response()->json(['data' => $user], 201);
    }
}
```

---

## 3. Menjalankan Queue Worker

### 3.1 Command Utama

```bash
# Jalankan worker (process job dari queue)
php artisan queue:work

# Jalankan dengan opsi
php artisan queue:work --queue=emails,default  # prioritas: emails dulu
php artisan queue:work --tries=3               # max 3 percobaan
php artisan queue:work --timeout=60            # max 60 detik per job
php artisan queue:work --sleep=3               # tunggu 3 detik jika queue kosong
php artisan queue:work --max-jobs=100          # stop setelah 100 job
php artisan queue:work --max-time=3600         # stop setelah 1 jam

# Jalankan satu job saja lalu berhenti
php artisan queue:work --once

# Jalankan worker dengan driver tertentu
php artisan queue:work redis
php artisan queue:work database
```

### 3.2 `queue:work` vs `queue:listen`

| Command        | Kecepatan | Auto-reload code? | Use Case                                 |
| -------------- | --------- | ----------------- | ---------------------------------------- |
| `queue:work`   | **Cepat** | Tidak             | **Production**                           |
| `queue:listen` | Lambat    | Ya                | Development (auto-detect perubahan kode) |

```bash
# Development — auto reload saat kode berubah
php artisan queue:listen

# Production — lebih cepat, tapi harus restart jika deploy kode baru
php artisan queue:work
php artisan queue:restart   # ← jalankan setelah deploy
```

---

## 4. Job Retry & Error Handling

### 4.1 Retry di Job Class

```php
class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;           // max 3 percobaan
    public int $timeout = 30;        // max 30 detik per percobaan
    public int $backoff = 10;        // tunggu 10 detik sebelum retry

    // Backoff bertahap: retry 1 = 10s, retry 2 = 30s, retry 3 = 60s
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        Mail::to($this->user->email)->send(new WelcomeMail($this->user));
    }

    // Dipanggil jika semua retry GAGAL
    public function failed(\Throwable $exception): void
    {
        Log::error("Gagal kirim email ke {$this->user->email}: {$exception->getMessage()}");
        // Bisa: kirim notifikasi ke admin, simpan ke tabel khusus, dsb.
    }
}
```

### 4.2 Retry dari Command Line

```bash
# Lihat failed jobs
php artisan queue:failed

# Retry satu failed job by ID
php artisan queue:retry 5

# Retry semua failed jobs
php artisan queue:retry all

# Hapus satu failed job
php artisan queue:forget 5

# Hapus semua failed jobs
php artisan queue:flush
```

### 4.3 `retryUntil()` — Retry Berdasarkan Waktu

```php
class ProcessPayment implements ShouldQueue
{
    use Queueable;

    // Tidak pakai $tries, tapi retry sampai 1 jam dari sekarang
    public function retryUntil(): \DateTime
    {
        return now()->addHour();
    }

    public function handle(): void
    {
        // proses pembayaran...
    }
}
```

---

## 5. Queue Priority (Multiple Queue)

```php
// Tentukan queue saat dispatch
SendWelcomeEmail::dispatch($user)->onQueue('emails');
GenerateReport::dispatch($report)->onQueue('reports');
ProcessPayment::dispatch($order)->onQueue('payments');
```

```bash
# Worker memproses berdasarkan prioritas (kiri = lebih tinggi)
php artisan queue:work --queue=payments,emails,default

# payments diproses duluan, baru emails, baru default
```

Atau tentukan langsung di Job class:

```php
class ProcessPayment implements ShouldQueue
{
    use Queueable;

    public string $queue = 'payments';   // selalu masuk queue 'payments'

    public function handle(): void { ... }
}
```

---

## 6. Job Middleware

Middleware = logic yang jalan **sebelum** job di-handle.

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\RateLimited;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    // Tambahkan middleware ke job
    public function middleware(): array
    {
        return [
            // Cegah job yang sama jalan bersamaan (anti duplikat)
            new WithoutOverlapping($this->user->id),

            // Rate limit: max 5 job per menit
            // (harus register RateLimiter di AppServiceProvider)
            new RateLimited('emails'),
        ];
    }

    public function handle(): void { ... }
}

// Di AppServiceProvider:
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('emails', fn () => Limit::perMinute(5));
}
```

---

## 7. Job Batching

Jalankan **kumpulan job** dan track progress-nya:

```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new SendWelcomeEmail($user1),
    new SendWelcomeEmail($user2),
    new SendWelcomeEmail($user3),
])
->then(function (Batch $batch) {
    Log::info('Semua job selesai!');
})
->catch(function (Batch $batch, \Throwable $e) {
    Log::error("Ada job gagal: {$e->getMessage()}");
})
->finally(function (Batch $batch) {
    Log::info("Batch selesai. Total: {$batch->totalJobs}, Gagal: {$batch->failedJobs}");
})
->name('Send Welcome Emails')
->onQueue('emails')
->dispatch();

// Cek progress
$batch = Bus::findBatch($batchId);
$batch->progress();           // 0-100 (persen)
$batch->totalJobs;            // total job
$batch->pendingJobs;          // yang masih antri
$batch->failedJobs;           // yang gagal
$batch->finished();           // true / false
```

---

## 8. Job Chaining

Job dijalankan **berurutan** — job ke-2 baru jalan jika job ke-1 selesai:

```php
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new ProcessUpload($file),      // ① upload dulu
    new ResizeImage($file),        // ② resize setelah upload selesai
    new SendNotification($user),   // ③ notifikasi setelah resize selesai
])->onQueue('uploads')->dispatch();

// Jika job ke-2 gagal → job ke-3 TIDAK dijalankan
```

---

## 9. Event: Before / After Job

```php
// Di AppServiceProvider
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(JobProcessing::class, function ($event) {
        Log::info("Job dimulai: {$event->job->getName()}");
    });

    Event::listen(JobProcessed::class, function ($event) {
        Log::info("Job selesai: {$event->job->getName()}");
    });

    Event::listen(JobFailed::class, function ($event) {
        Log::error("Job gagal: {$event->job->getName()} — {$event->exception->getMessage()}");
    });
}
```

---

## 10. Artisan Commands — Quick Reference

```bash
# === WORKER ===
php artisan queue:work                 # jalankan worker
php artisan queue:work --queue=high,default  # prioritas queue
php artisan queue:work --tries=3       # max retry
php artisan queue:work --timeout=60    # timeout per job
php artisan queue:work --once          # proses 1 job lalu stop
php artisan queue:listen               # development (auto-reload)
php artisan queue:restart              # restart semua worker (setelah deploy)

# === FAILED JOBS ===
php artisan queue:failed               # lihat daftar failed jobs
php artisan queue:retry 5              # retry job ID 5
php artisan queue:retry all            # retry semua
php artisan queue:forget 5             # hapus failed job ID 5
php artisan queue:flush                # hapus semua failed jobs

# === MONITORING ===
php artisan queue:monitor redis:default,database:default  # monitor queue size
php artisan queue:clear                # hapus semua pending jobs (hati-hati!)

# === GENERATE ===
php artisan make:job NamaJob
```

---

## 11. Production: Supervisor

Di production, worker harus jalan terus. Gunakan **Supervisor** untuk auto-restart:

```ini
# /etc/supervisor/conf.d/laravel-worker.conf

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/book-manager/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
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
sudo supervisorctl status       # lihat status semua worker
```

**`numprocs=4`** = 4 worker berjalan paralel → 4 job diproses bersamaan.

---

## 12. Contoh Lengkap: Job dari Awal sampai Akhir

### Buat Job

```bash
php artisan make:job ExportBooksToCSV
```

### Job Class

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

    public int $tries = 3;
    public int $timeout = 120;

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
        $csv = "ID,Title,Author,Genre,Stock\n";

        foreach ($books as $book) {
            $csv .= "{$book->id},{$book->title},{$book->author->name},{$book->genre},{$book->stock}\n";
        }

        Storage::put("exports/{$this->filename}", $csv);

        Log::info("Export selesai: {$this->filename} ({$books->count()} buku)");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Export gagal: {$this->filename} — {$e->getMessage()}");
    }
}
```

### Dispatch dari Controller

```php
use App\Jobs\ExportBooksToCSV;

class BookController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        $filename = 'books-' . now()->format('Y-m-d-His') . '.csv';

        ExportBooksToCSV::dispatch($filename, $request->genre)
            ->onQueue('exports');

        return response()->json([
            'message' => 'Export sedang diproses',
            'filename' => $filename,
        ], 202);
    }
}
```

### Jalankan Worker

```bash
php artisan queue:work --queue=exports,default
```

---

## 13. Testing Job

```php
use App\Jobs\SendWelcomeEmail;
use Illuminate\Support\Facades\Queue;

// Test: pastikan job di-dispatch
test('register user dispatches welcome email job', function () {
    Queue::fake();   // ← intercept semua dispatch, job tidak benar-benar jalan

    $this->post('/api/users', ['name' => 'Ahmad', 'email' => 'ahmad@test.com']);

    Queue::assertPushed(SendWelcomeEmail::class);
});

// Test: pastikan dispatch ke queue tertentu
test('export dispatches to exports queue', function () {
    Queue::fake();

    $this->post('/api/books/export');

    Queue::assertPushedOn('exports', ExportBooksToCSV::class);
});

// Test: pastikan job TIDAK di-dispatch
test('nothing dispatched if validation fails', function () {
    Queue::fake();

    $this->post('/api/users', []);  // invalid

    Queue::assertNothingPushed();
});

// Test: jalankan job secara langsung (test logic handle)
test('export creates CSV file', function () {
    Storage::fake('local');

    $job = new ExportBooksToCSV('test.csv');
    $job->handle();

    Storage::assertExists('exports/test.csv');
});
```

---

## 14. Pertanyaan Interview + Jawaban

### Q1: Apa itu Queue dan kenapa butuh Queue?

**A:** Queue adalah antrian pekerjaan yang dijalankan di **background**. Butuh queue agar user tidak menunggu proses yang lama (kirim email, generate report, resize image). User langsung dapat response, job diproses terpisah oleh worker.

### Q2: Apa perbedaan `queue:work` vs `queue:listen`?

**A:** `queue:work` load app sekali lalu loop — **lebih cepat**, tapi tidak auto-detect perubahan kode (harus `queue:restart` setelah deploy). `queue:listen` spawn proses baru tiap job — **lebih lambat** tapi auto-reload kode, cocok untuk development.

### Q3: Apa yang terjadi jika job gagal?

**A:** Job akan di-retry sesuai `$tries` (default 1). Jika semua retry gagal, job masuk tabel `failed_jobs`. Method `failed()` di job class dipanggil. Bisa di-retry manual dengan `php artisan queue:retry`.

### Q4: Bagaimana handle job yang tidak boleh jalan bersamaan?

**A:** Pakai middleware `WithoutOverlapping`:

```php
public function middleware(): array
{
    return [new WithoutOverlapping($this->user->id)];
}
```

Atau pakai `Cache::lock()` di dalam `handle()`.

### Q5: Apa bedanya `dispatch()` vs `dispatchSync()`?

**A:** `dispatch()` masukkan ke queue (background). `dispatchSync()` jalankan langsung saat itu juga, skip queue. `dispatchSync()` berguna untuk testing atau kasus tertentu yang harus sinkron.

### Q6: Bagaimana prioritas queue bekerja?

**A:** Saat menjalankan worker:

```bash
php artisan queue:work --queue=payments,emails,default
```

Worker akan **selalu** proses queue `payments` dulu sampai kosong, baru `emails`, baru `default`.

### Q7: Apa itu Job Batching?

**A:** Mengelompokkan beberapa job dan track progress-nya. Bisa define callback `then()`, `catch()`, `finally()`. Cocok untuk proses bulk (kirim email ke 1000 user, import data).

### Q8: Apa itu Job Chaining?

**A:** Job dijalankan **berurutan**. Job ke-2 baru mulai setelah job ke-1 selesai. Jika satu job gagal, chain berhenti. Cocok untuk pipeline (upload → resize → notify).

### Q9: Bagaimana queue di production?

**A:** Worker harus selalu jalan. Pakai **Supervisor** untuk auto-restart worker jika crash. Set `numprocs` untuk jumlah worker paralel. Gunakan Redis sebagai driver queue. Jalankan `queue:restart` setelah deploy kode baru.

### Q10: Apa perbedaan driver `sync` vs `database` vs `redis`?

**A:**

- `sync` — langsung jalan, tidak ada queue. Untuk development/debug
- `database` — job disimpan di tabel `jobs`. Mudah setup, tapi lambat karena pakai disk
- `redis` — job disimpan di RAM Redis. Cepat, cocok production. Butuh Redis server

### Q11: Bagaimana testing job?

**A:** Pakai `Queue::fake()` untuk intercept dispatch tanpa benar-benar menjalankan job. Assert dengan `Queue::assertPushed()`, `Queue::assertPushedOn()`, `Queue::assertNothingPushed()`. Untuk test logic job, panggil `handle()` langsung.

### Q12: Apa itu `retry_after` di config queue?

**A:** Waktu (detik) sebelum job yang stuck dianggap gagal dan dikembalikan ke queue. Default 90 detik. Harus lebih besar dari `$timeout` di job, agar job yang masih jalan tidak di-retry secara prematur.
