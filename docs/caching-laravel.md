# Laravel Caching — Comprehensive Study Notes

## 1. Apa itu Caching?

Caching = menyimpan hasil operasi yang **mahal** (query DB, API call, kalkulasi berat)
ke tempat yang **lebih cepat** agar tidak perlu di-proses ulang setiap request.

**Tanpa cache:**

```
User Request → Query DB (50ms) → Response
User Request → Query DB (50ms) → Response   ← query ulang!
```

**Dengan cache:**

```
User Request → Query DB (50ms) → Simpan ke Cache → Response
User Request → Baca Cache (1ms) → Response        ← jauh lebih cepat!
```

---

## 2. Konfigurasi (`config/cache.php`)

### 2.1 Default Store

```php
'default' => env('CACHE_STORE', 'database'),
```

Set di `.env`:

```env
CACHE_STORE=file       # development
CACHE_STORE=redis      # production (recommended)
CACHE_STORE=database   # jika belum setup Redis
```

### 2.2 Driver yang Tersedia

| Driver      | Kecepatan    | Persisten | Use Case                           |
| ----------- | ------------ | --------- | ---------------------------------- |
| `file`      | Lambat       | Ya        | Development sederhana              |
| `database`  | Sedang       | Ya        | Tidak ada Redis/Memcached          |
| `redis`     | **Cepat**    | Ya        | **Production (recommended)**       |
| `memcached` | Cepat        | Ya        | Alternative Redis                  |
| `array`     | Sangat Cepat | **Tidak** | Testing saja (hilang tiap request) |
| `octane`    | Sangat Cepat | Tidak     | Laravel Octane                     |
| `dynamodb`  | Sedang       | Ya        | AWS environment                    |
| `null`      | -            | Tidak     | Disable cache                      |
| `failover`  | Varies       | Ya        | Fallback jika store utama mati     |

### 2.3 Setup Redis (Production)

```env
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

```bash
composer require predis/predis
# atau pakai extension php-redis (lebih cepat)
```

### 2.4 Setup Database

Migration sudah disertakan Laravel:

```bash
php artisan migrate
# Tabel `cache` dan `cache_locks` otomatis dibuat
```

### 2.5 Cache Prefix

```php
'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel')).'-cache-'),
```

Prefix mencegah **tabrakan key** jika multi-app pakai Redis/Memcached yang sama.

---

## 3. Operasi Dasar Cache

### 3.1 Import Facade

```php
use Illuminate\Support\Facades\Cache;
```

### 3.2 Simpan & Ambil

```php
// SIMPAN — dengan TTL (Time To Live)
Cache::put('key', 'value', now()->addMinutes(10));     // 10 menit
Cache::put('key', 'value', 600);                       // 600 detik
Cache::put('key', 'value');                             // selamanya (tanpa TTL)

// AMBIL
$value = Cache::get('key');                             // null jika tidak ada
$value = Cache::get('key', 'default');                  // custom default
$value = Cache::get('key', fn () => DB::table('settings')->first()); // lazy default

// CEK ADA/TIDAK
Cache::has('key');     // true/false

// AMBIL LALU HAPUS
$value = Cache::pull('key');   // ambil value, lalu hapus dari cache
```

### 3.3 Remember — Pattern Paling Penting!

```php
// Jika key ada di cache → return cache
// Jika key TIDAK ada → jalankan closure, simpan hasilnya, return
$books = Cache::remember('all-books', now()->addMinutes(30), function () {
    return Book::with('author')->get();
});

// Versi forever (tanpa TTL)
$settings = Cache::rememberForever('app-settings', function () {
    return Setting::all()->pluck('value', 'key');
});
```

**Ini pattern yang paling sering dipakai dan paling likely ditanyakan di interview.**

### 3.4 Hapus Cache

```php
// Hapus satu key
Cache::forget('all-books');

// Hapus semua cache
Cache::flush();    // ⚠️ HATI-HATI di production!

// Dari artisan
// php artisan cache:clear
```

### 3.5 Increment & Decrement

```php
Cache::put('visitors', 0, now()->addHours(24));
Cache::increment('visitors');        // 1
Cache::increment('visitors', 5);     // 6
Cache::decrement('visitors');        // 5
Cache::decrement('visitors', 3);     // 2
```

### 3.6 Store Jika Belum Ada

```php
// Simpan HANYA jika key belum ada di cache
Cache::add('key', 'value', now()->addMinutes(10)); // return true jika berhasil

// Simpan selamanya (tanpa TTL)
Cache::forever('key', 'value');
```

---

## 4. Cache di Berbagai Layer

### 4.1 Cache di Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\Cache;

class BookController extends Controller
{
    public function index()
    {
        $books = Cache::remember('books.all', now()->addMinutes(30), function () {
            return Book::with('author')->latest()->get();
        });

        return view('books.index', compact('books'));
    }

    public function show(int $id)
    {
        $book = Cache::remember("books.{$id}", now()->addHours(1), function () use ($id) {
            return Book::with('author')->findOrFail($id);
        });

        return view('books.show', compact('book'));
    }

    public function store(Request $request)
    {
        $book = Book::create($request->validated());

        // Invalidate cache setelah data berubah
        Cache::forget('books.all');

        return redirect()->route('books.index');
    }

    public function update(Request $request, Book $book)
    {
        $book->update($request->validated());

        // Invalidate cache terkait
        Cache::forget('books.all');
        Cache::forget("books.{$book->id}");

        return redirect()->route('books.show', $book);
    }

    public function destroy(Book $book)
    {
        $book->delete();

        Cache::forget('books.all');
        Cache::forget("books.{$book->id}");

        return redirect()->route('books.index');
    }
}
```

### 4.2 Cache di Model (Accessor)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Author extends Model
{
    protected $fillable = ['name', 'email'];

    public function books()
    {
        return $this->hasMany(Book::class);
    }

    // Cache jumlah buku per author
    public function getBooksCountCachedAttribute(): int
    {
        return Cache::remember(
            "author.{$this->id}.books_count",
            now()->addMinutes(30),
            fn () => $this->books()->count()
        );
    }
}

// Pakai: $author->books_count_cached
```

### 4.3 Cache di Console Command

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\Author;

class ShowAuthorStats extends Command
{
    protected $signature = 'book:stats {--min-books=0} {--fresh : Bypass cache}';
    protected $description = 'Tampilkan statistik author';

    public function handle(): int
    {
        $minBooks = (int) $this->option('min-books');
        $fresh = $this->option('fresh');

        $cacheKey = "author-stats.min-{$minBooks}";

        if ($fresh) {
            Cache::forget($cacheKey);
            $this->comment('Cache di-bypass.');
        }

        $authors = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($minBooks) {
            $this->comment('Mengambil data dari database...');
            return Author::withCount('books')
                ->having('books_count', '>=', $minBooks)
                ->get();
        });

        if ($authors->isEmpty()) {
            $this->warn("Tidak ada data author yang memenuhi kriteria.");
            return Command::SUCCESS;
        }

        $this->table(
            ['Nama Author', 'Jumlah Buku'],
            $authors->map(fn ($a) => [$a->name, $a->books_count])
        );

        return Command::SUCCESS;
    }
}
```

---

## 5. Cache Tags

> **Catatan:** Tags HANYA didukung `redis` dan `memcached`. Tidak didukung `file` atau `database`.

Tags memungkinkan **pengelompokan cache** agar bisa di-flush per grup:

```php
// Simpan dengan tag
Cache::tags(['books'])->put('books.all', $books, now()->addHours(1));
Cache::tags(['books'])->put('books.popular', $popular, now()->addHours(1));
Cache::tags(['authors'])->put('authors.all', $authors, now()->addHours(1));

// Ambil
$books = Cache::tags(['books'])->get('books.all');

// Hapus semua cache yang bertag 'books' saja
Cache::tags(['books'])->flush();
// 'authors.all' TIDAK terhapus

// Multi-tag
Cache::tags(['books', 'featured'])->put('featured-books', $data, now()->addHours(1));

// Flush hanya yang punya tag 'featured'
Cache::tags(['featured'])->flush();
```

### Contoh Real-World dengan Tags

```php
class BookController extends Controller
{
    public function index()
    {
        $books = Cache::tags(['books'])->remember('books.all', now()->addHours(1), function () {
            return Book::with('author')->get();
        });

        return view('books.index', compact('books'));
    }

    public function store(Request $request)
    {
        Book::create($request->validated());

        // Flush semua cache berlabel 'books' — lebih bersih daripada forget satu-satu
        Cache::tags(['books'])->flush();

        return redirect()->route('books.index');
    }
}
```

---

## 6. Cache Lock (Atomic Lock)

Mencegah **race condition** — pastikan hanya 1 proses yang berjalan:

```php
use Illuminate\Support\Facades\Cache;

$lock = Cache::lock('processing-report', 10); // lock selama 10 detik

if ($lock->get()) {
    try {
        // Hanya 1 proses yang bisa masuk sini
        $this->generateReport();
    } finally {
        $lock->release(); // SELALU release!
    }
} else {
    // Lock gagal — proses lain sedang jalan
    $this->info('Report sedang di-generate oleh proses lain.');
}

// Versi Block (tunggu sampai lock tersedia)
Cache::lock('processing-report', 10)->block(5, function () {
    // Tunggu max 5 detik untuk mendapatkan lock
    $this->generateReport();
});
```

---

## 7. Multiple Cache Stores

Bisa pakai **store berbeda** untuk kebutuhan berbeda:

```php
// Default store (dari CACHE_STORE)
Cache::put('key', 'value', 60);

// Pakai store tertentu
Cache::store('file')->put('logs', $data, now()->addHours(24));
Cache::store('redis')->put('sessions', $data, now()->addMinutes(30));
Cache::store('array')->put('temp', $data); // untuk testing

// Remember dengan store tertentu
$value = Cache::store('redis')->remember('key', 3600, fn () => expensiveQuery());
```

### Failover Store

```php
// config/cache.php
'failover' => [
    'driver' => 'failover',
    'stores' => ['redis', 'database', 'file'],  // coba redis dulu, gagal → database, gagal → file
],
```

---

## 8. Cache Helper Function (Tanpa Facade)

```php
// Simpan
cache(['key' => 'value'], now()->addMinutes(10));

// Ambil
$value = cache('key');
$value = cache('key', 'default');

// Akses cache store
cache()->store('redis')->get('key');

// Remember juga bisa
cache()->remember('key', 600, fn () => 'value');
```

---

## 9. Strategy: Cache Invalidation

> "There are only two hard things in Computer Science: cache invalidation and naming things."
> — Phil Karlton

### 9.1 Manual Invalidation (Paling Umum)

```php
// CRUD: invalidate saat data berubah
public function update(Request $request, Book $book)
{
    $book->update($request->validated());

    Cache::forget('books.all');
    Cache::forget("books.{$book->id}");
}
```

### 9.2 Model Observer untuk Auto-Invalidation

```php
<?php
// app/Observers/BookObserver.php

namespace App\Observers;

use App\Models\Book;
use Illuminate\Support\Facades\Cache;

class BookObserver
{
    public function created(Book $book): void
    {
        Cache::forget('books.all');
    }

    public function updated(Book $book): void
    {
        Cache::forget('books.all');
        Cache::forget("books.{$book->id}");
    }

    public function deleted(Book $book): void
    {
        Cache::forget('books.all');
        Cache::forget("books.{$book->id}");
    }
}
```

Register di `AppServiceProvider`:

```php
use App\Models\Book;
use App\Observers\BookObserver;

public function boot(): void
{
    Book::observe(BookObserver::class);
}
```

### 9.3 Event-based Invalidation

```php
// Dengan model event langsung di model
class Book extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('books.all'));
        static::deleted(fn () => Cache::forget('books.all'));
    }
}
```

### 9.4 TTL-based (Time Expiry)

Biarkan cache expire sendiri — paling simpel:

```php
// Data yang jarang berubah → TTL panjang
Cache::remember('site-settings', now()->addHours(24), fn () => Setting::all());

// Data yang sering berubah → TTL pendek
Cache::remember('trending-books', now()->addMinutes(5), fn () => Book::trending()->get());
```

---

## 10. Naming Convention untuk Cache Key

```php
// Pattern: entity.scope.identifier

'books.all'                    // semua buku
'books.5'                      // buku ID 5
'books.popular'                // buku populer
'books.author.3'               // buku dari author ID 3
'author-stats.min-0'           // statistik author min 0
'books.page.1.perpage.10'     // pagination

// Gunakan method helper untuk konsistensi
class CacheKey
{
    public static function books(): string { return 'books.all'; }
    public static function book(int $id): string { return "books.{$id}"; }
    public static function authorBooks(int $authorId): string { return "books.author.{$authorId}"; }
}

// Pakai:
Cache::remember(CacheKey::book(5), 3600, fn () => Book::find(5));
```

---

## 11. Cache di Route / Middleware

### 11.1 Cache Response (Middleware)

```php
<?php
// app/Http/Middleware/CacheResponse.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class CacheResponse
{
    public function handle($request, Closure $next, int $minutes = 10)
    {
        // Hanya cache GET request
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $key = 'response.' . md5($request->fullUrl());

        return Cache::remember($key, now()->addMinutes($minutes), function () use ($next, $request) {
            return $next($request);
        });
    }
}
```

### 11.2 Route-level Cache

```php
// routes/web.php
Route::get('/books', function () {
    return Cache::remember('page.books', 600, function () {
        return view('books.index', ['books' => Book::all()]);
    });
});
```

---

## 12. Contoh Praktis: Cache di Repository Pattern

```php
<?php
// app/Repositories/BookRepository.php

namespace App\Repositories;

use App\Models\Book;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

class BookRepository
{
    public function all(): Collection
    {
        return Cache::remember('books.all', now()->addMinutes(30), function () {
            return Book::with('author')->latest()->get();
        });
    }

    public function find(int $id): ?Book
    {
        return Cache::remember("books.{$id}", now()->addHours(1), function () use ($id) {
            return Book::with('author')->find($id);
        });
    }

    public function create(array $data): Book
    {
        $book = Book::create($data);
        $this->clearCache();
        return $book;
    }

    public function update(Book $book, array $data): Book
    {
        $book->update($data);
        $this->clearCache($book->id);
        return $book;
    }

    public function delete(Book $book): void
    {
        $book->delete();
        $this->clearCache($book->id);
    }

    private function clearCache(?int $id = null): void
    {
        Cache::forget('books.all');
        if ($id) {
            Cache::forget("books.{$id}");
        }
    }
}
```

---

## 13. Testing Cache

### 13.1 Pastikan Cache Bekerja

```php
use Illuminate\Support\Facades\Cache;

test('books.all di-cache selama 30 menit', function () {
    Cache::shouldReceive('remember')
        ->once()
        ->with('books.all', \Mockery::type('object'), \Mockery::type('Closure'))
        ->andReturn(collect());

    $this->get('/books')->assertOk();
});
```

### 13.2 Test dengan Cache Fake

```php
use Illuminate\Support\Facades\Cache;

test('store invalidates cache', function () {
    Cache::put('books.all', 'cached data', 600);

    $this->assertTrue(Cache::has('books.all'));

    // Aksi yang harus invalidate cache
    $this->post('/books', ['title' => 'New Book', 'author_id' => 1]);

    $this->assertFalse(Cache::has('books.all'));
});
```

### 13.3 Test Tanpa Cache (array driver)

Di `phpunit.xml`:

```xml
<env name="CACHE_STORE" value="array"/>
```

---

## 14. Debugging Cache

### 14.1 Artisan Commands

```bash
# Hapus semua cache
php artisan cache:clear

# Hapus semua (config, route, view, cache)
php artisan optimize:clear

# Lihat isi cache (database driver)
php artisan tinker
> Cache::get('books.all');
> Cache::has('books.all');
> Cache::forget('books.all');
```

### 14.2 Log Cache Hit/Miss

```php
$cacheKey = 'books.all';

if (Cache::has($cacheKey)) {
    Log::debug("Cache HIT: {$cacheKey}");
} else {
    Log::debug("Cache MISS: {$cacheKey}");
}

$books = Cache::remember($cacheKey, now()->addMinutes(30), fn () => Book::all());
```

---

## 15. Kapan Harus Cache / Tidak Cache

### CACHE ini:

| Data                      | TTL      | Alasan                               |
| ------------------------- | -------- | ------------------------------------ |
| Daftar semua buku         | 30 menit | Sering dipanggil, jarang berubah     |
| Detail buku               | 1 jam    | Jarang berubah                       |
| Settings app              | 24 jam   | Hampir tidak pernah berubah          |
| API response pihak ketiga | 15 menit | Mengurangi API call                  |
| Statistik / laporan       | 1 jam    | Kalkulasi berat                      |
| Menu navigasi             | 24 jam   | Query setiap request, jarang berubah |

### JANGAN cache ini:

| Data                         | Alasan                   |
| ---------------------------- | ------------------------ |
| Data real-time (stok, saldo) | Harus selalu akurat      |
| Data per-user yang unik      | Terlalu banyak key       |
| Data kecil/cepat             | Overhead cache > benefit |
| Form CSRF token              | Security                 |

---

## 16. Pertanyaan Interview yang Sering Muncul

### Q: Apa perbedaan `Cache::put()` vs `Cache::remember()`?

**A:** `put()` selalu overwrite. `remember()` cek dulu — jika ada return cache, jika tidak ada jalankan closure lalu simpan.

### Q: Bagaimana cara invalidate cache?

**A:** `Cache::forget('key')`, cache tags flush, TTL expiry, atau Model Observer.

### Q: Kapan pakai Redis vs Database untuk cache?

**A:** Redis jauh lebih cepat (in-memory). Database pakai disk. Production selalu pakai Redis jika available.

### Q: Apa itu cache stampede / thundering herd?

**A:** Banyak request bersamaan saat cache expired → semua hit database sekaligus. Solusi: `Cache::lock()` atau stagger TTL.

### Q: Apa bedanya Cache Tags vs manual forget?

**A:** Tags bisa flush sekelompok cache sekaligus tanpa tahu key-nya satu persatu. Hanya untuk Redis/Memcached.

### Q: Bagaimana handle cache di multi-server?

**A:** Pakai centralized cache (Redis/Memcached) agar semua server baca/tulis ke tempat yang sama.

### Q: Apa itu cache warming?

**A:** Pre-populate cache sebelum user request, biasanya lewat console command atau scheduled task.

```php
// Console command untuk warming cache
Artisan::command('cache:warm', function () {
    Cache::put('books.all', Book::with('author')->get(), now()->addHours(1));
    Cache::put('authors.all', Author::withCount('books')->get(), now()->addHours(1));
    $this->info('Cache warmed!');
});
```

---

## 17. Cheat Sheet

```php
use Illuminate\Support\Facades\Cache;

// === BASIC ===
Cache::put('key', $value, $seconds);           // simpan
Cache::get('key');                              // ambil
Cache::get('key', 'default');                   // ambil + default
Cache::has('key');                              // cek ada
Cache::forget('key');                           // hapus satu
Cache::flush();                                 // hapus semua

// === REMEMBER (paling sering dipakai) ===
Cache::remember('key', $seconds, fn () => ...);       // TTL
Cache::rememberForever('key', fn () => ...);           // selamanya

// === LAINNYA ===
Cache::pull('key');                             // ambil + hapus
Cache::add('key', $value, $seconds);           // simpan jika belum ada
Cache::forever('key', $value);                 // simpan selamanya
Cache::increment('key');                       // +1
Cache::decrement('key');                       // -1

// === STORE ===
Cache::store('redis')->get('key');             // pakai store tertentu

// === TAGS (Redis/Memcached only) ===
Cache::tags(['books'])->put('key', $value, $seconds);
Cache::tags(['books'])->flush();

// === LOCK ===
Cache::lock('key', $seconds)->get();
Cache::lock('key', $seconds)->block(5, fn () => ...);

// === HELPER ===
cache('key');                                  // get
cache(['key' => 'value'], $seconds);          // put
```

---

## Topik Selanjutnya

- [ ] **Queue** — job, worker, failed jobs
- [ ] **Repository Pattern** — abstraksi layer data
