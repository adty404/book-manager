# Laravel Caching — Interview Preparation

---

## 0. Setup Caching (Sebelum Mulai)

### Driver `file` — Default, Langsung Pakai

Tidak perlu setup apapun. Pastikan `.env`:

```env
CACHE_STORE=file
```

Pastikan folder writable:

```bash
chmod -R 775 storage/framework/cache
```

---

### Driver `database` — Pakai Tabel Cache

```env
CACHE_STORE=database
```

Buat tabel cache (sudah include di Laravel):

```bash
php artisan migrate
# Membuat tabel: cache, cache_locks
```

---

### Driver `redis` — Production

**1. Install Redis server** (jika belum ada):

```bash
# macOS
brew install redis
brew services start redis

# Ubuntu
sudo apt install redis-server
sudo systemctl start redis
```

**2. Install package PHP:**

```bash
composer require predis/predis
# atau pakai php-redis extension (lebih cepat, perlu install manual)
```

**3. Set `.env`:**

```env
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CACHE_DB=0
REDIS_PREFIX=
```

**4. Verifikasi Redis berjalan:**

```bash
redis-cli ping   # harus return: PONG
```

---

### Driver `array` — Untuk Testing

Set di `phpunit.xml` — tidak perlu ubah `.env`:

```xml
<php>
    <env name="CACHE_STORE" value="array"/>
</php>
```

---

### Verifikasi Setup Berhasil

```bash
php artisan tinker

# Test simpan & ambil
Cache::put('test', 'ok', 60);
Cache::get('test');   # → "ok"

# Cek driver aktif
config('cache.default');   # → "file" / "redis" / "database"
```

---

## 1. Konsep Dasar (Wajib Tahu)

Caching = menyimpan hasil operasi **mahal** (query DB, API call) ke tempat **lebih cepat** agar tidak diproses ulang setiap request.

```
Tanpa cache:  Request → Query DB (50ms) → Response  (setiap kali!)
Dengan cache: Request → Baca Cache (1ms) → Response (langsung!)
```

---

## 2. Cheat Sheet + Contoh Penerapan

### 2.1 `Cache::remember()` — Yang Paling Sering Dipakai

```php
// Logika: ada di cache? return. Tidak ada? jalankan closure → simpan → return.
$books = Cache::remember('books.all', now()->addMinutes(30), function () {
    return Book::with('author')->get();
});
```

**Contoh di Controller:**

```php
public function index(Request $request): JsonResponse
{
    $cacheKey = 'books.all.' . md5($request->getQueryString() ?? '');

    $books = Cache::remember($cacheKey, 1800, function () use ($request) {
        $query = Book::with('author');

        if ($request->has('genre')) {
            $query->where('genre', $request->genre);
        }

        return $query->latest()->get();
    });

    return response()->json(['data' => $books]);
}
```

### 2.2 `Cache::put()` & `Cache::get()` — Simpan & Ambil Manual

```php
Cache::put('settings', $data, now()->addHours(24));    // simpan 24 jam
Cache::put('key', 'value', 600);                       // simpan 600 detik
Cache::put('key', 'value');                             // simpan selamanya

$value = Cache::get('key');                             // null jika tidak ada
$value = Cache::get('key', 'default');                  // dengan default
```

**Contoh: Cache warming via console command**

```php
Artisan::command('cache:warm', function () {
    Cache::put('books.all', Book::with('author')->get(), now()->addHours(1));
    $this->info('Cache warmed!');
});
```

### 2.3 `Cache::forget()` — Invalidasi Cache

```php
Cache::forget('books.all');          // hapus satu key
Cache::flush();                       // hapus SEMUA cache (hati-hati!)
```

**Contoh di Controller saat data berubah:**

```php
public function store(Request $request): JsonResponse
{
    $book = Book::create($request->validated());

    Cache::forget('books.all');   // ← data berubah, cache harus dihapus

    return response()->json(['data' => $book], 201);
}

public function update(Request $request, int $id): JsonResponse
{
    $book = Book::findOrFail($id);
    $book->update($request->validated());

    Cache::forget('books.all');
    Cache::forget("books.{$id}");  // ← hapus cache detail juga

    return response()->json(['data' => $book]);
}
```

### 2.4 `Cache::rememberForever()` — Tanpa Expiry

```php
$settings = Cache::rememberForever('app-settings', function () {
    return Setting::all()->pluck('value', 'key');
});
```

Cocok untuk data yang **hampir tidak pernah berubah** (settings, menu, dsb).

### 2.5 `Cache::has()`, `Cache::pull()`, `Cache::add()`

```php
Cache::has('key');                    // cek ada / tidak → true/false
Cache::pull('key');                   // ambil lalu hapus
Cache::add('key', 'value', 600);     // simpan HANYA jika belum ada
```

### 2.6 `Cache::increment()` & `Cache::decrement()`

```php
Cache::put('visitors', 0, now()->addHours(24));
Cache::increment('visitors');         // 1
Cache::increment('visitors', 5);      // 6
Cache::decrement('visitors');         // 5
```

**Contoh: Hit counter endpoint**

```php
public function show(int $id): JsonResponse
{
    Cache::increment("books.{$id}.views");

    $book = Cache::remember("books.{$id}", 3600, fn () => Book::find($id));
    return response()->json(['data' => $book]);
}
```

### 2.7 `Cache::tags()` — Grouping (Redis/Memcached Only!)

```php
// Simpan dengan tag
Cache::tags(['books'])->remember('books.all', 3600, fn () => Book::all());
Cache::tags(['books'])->remember('books.popular', 3600, fn () => Book::popular()->get());

// Flush semua cache bertag 'books' sekaligus
Cache::tags(['books'])->flush();
// Tidak perlu forget satu-satu!
```

### 2.8 `Cache::lock()` — Mencegah Race Condition

```php
$lock = Cache::lock("book.borrow.{$bookId}", 10);  // lock 10 detik

if ($lock->get()) {
    try {
        $book = Book::find($bookId);
        if ($book->stock <= 0) {
            return response()->json(['message' => 'Stok habis'], 400);
        }
        $book->decrement('stock');
        return response()->json(['message' => 'Berhasil pinjam']);
    } finally {
        $lock->release();   // WAJIB release
    }
}

return response()->json(['message' => 'Coba lagi'], 429);
```

**Versi block (tunggu lock tersedia):**

```php
Cache::lock('generate-report', 60)->block(10, function () {
    // Tunggu max 10 detik untuk dapat lock
    // Jika timeout → throw LockTimeoutException
    $this->generateReport();
});
```

### 2.9 `Cache::store()` — Pakai Driver Tertentu

```php
Cache::store('file')->put('logs', $data, 86400);
Cache::store('redis')->remember('key', 3600, fn () => query());
```

### 2.10 Helper `cache()` — Tanpa Facade

```php
cache('key');                                   // get
cache('key', 'default');                        // get + default
cache(['key' => 'value'], 600);                // put
cache()->remember('key', 600, fn () => ...);   // remember
```

---

## 3. Cache Driver — Kapan Pakai yang Mana?

| Driver      | Kapan             | Alasan                                                                                 |
| ----------- | ----------------- | -------------------------------------------------------------------------------------- |
| `file`      | Development       | Tidak perlu install apapun, bisa dilihat filenya langsung untuk debug                  |
| `redis`     | **Production**    | In-memory (RAM) → sub-millisecond, support tags & lock, centralized untuk multi-server |
| `database`  | Belum ada Redis   | Pakai DB yang sudah ada, tidak perlu setup server baru                                 |
| `array`     | Testing / PHPUnit | Reset otomatis tiap test, tidak ada side effect, tidak butuh I/O                       |
| `memcached` | Alternative Redis | Lebih simpel dari Redis, tapi kurang fitur (no persistence)                            |

**Kenapa Redis di production?**

- **In-memory** — data di RAM, bukan disk → sangat cepat
- **Centralized** — multi-server baca/tulis ke satu Redis → data konsisten
- **Support tags** — `file` dan `database` TIDAK support tags
- **Atomic operations** — increment, lock → aman dari race condition

---

## 4. Cache Invalidation — 4 Strategy

> "There are only two hard things in CS: cache invalidation and naming things."

### 4.1 Manual — `Cache::forget()` di Controller

```php
public function update(Request $request, Book $book)
{
    $book->update($request->validated());
    Cache::forget('books.all');
    Cache::forget("books.{$book->id}");
}
```

### 4.2 Model Observer — Auto-Invalidation

```php
// app/Observers/BookObserver.php
class BookObserver
{
    public function created(Book $book): void {
        Cache::forget('books.all');
    }

    public function updated(Book $book): void {
        Cache::forget('books.all');
        Cache::forget("books.{$book->id}");
    }

    public function deleted(Book $book): void {
        Cache::forget('books.all');
        Cache::forget("books.{$book->id}");
    }
}

// Register di AppServiceProvider:
Book::observe(BookObserver::class);
```

**Keuntungan:** Controller bersih, tidak perlu ingat `Cache::forget()` di setiap method.

### 4.3 Model Event — Versi Simpel Observer

```php
class Book extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('books.all'));    // create + update
        static::deleted(fn () => Cache::forget('books.all'));
    }
}
```

### 4.4 TTL Expiry — Biarkan Expire Sendiri

```php
Cache::remember('trending', now()->addMinutes(5), fn () => Book::trending()->get());
// Setelah 5 menit, otomatis kosong → query ulang
```

---

## 5. Cache Key Naming Convention

```php
'books.all'                     // semua buku
'books.5'                       // buku ID 5
'books.author.3'                // buku dari author 3
'books.all.' . md5($query)     // list + filter (dynamic key)
```

Pakai `md5()` jika key bisa panjang/ada karakter khusus:

```php
$cacheKey = 'books.all.' . md5($request->getQueryString() ?? '');
// 'books.all.d41d8cd98f...'  ← 32 char, aman untuk semua driver
```

---

## 6. Kapan Cache / Jangan Cache

| Cache                        | Jangan Cache                      |
| ---------------------------- | --------------------------------- |
| Daftar data (books, authors) | Data real-time (stok live, saldo) |
| Settings / konfigurasi       | Data per-user yang unik           |
| API response pihak ketiga    | Data yang sangat kecil/cepat      |
| Statistik / laporan berat    | Form token (CSRF)                 |

---

## 7. Pertanyaan Interview + Jawaban

### Q1: Apa perbedaan `Cache::put()` vs `Cache::remember()`?

**A:** `put()` selalu overwrite value. `remember()` cek dulu — ada di cache? return langsung. Tidak ada? jalankan closure, simpan, lalu return.

### Q2: Bagaimana cara invalidate cache?

**A:** 4 cara — `Cache::forget('key')` manual, Cache Tags flush, TTL expiry otomatis, atau Model Observer untuk auto-invalidate saat data berubah.

### Q3: Kenapa Redis untuk production, bukan file/database?

**A:** Redis simpan data di RAM (in-memory) → sub-millisecond. File/database pakai disk → lambat. Redis juga centralized (bisa multi-server), support tags, dan atomic operations.

### Q4: Apa itu cache stampede / thundering herd?

**A:** Saat cache expired, banyak request **bersamaan** hit database sekaligus → DB overload. Solusi: `Cache::lock()` agar hanya 1 proses yang regenerate cache, sisanya menunggu.

### Q5: Apa bedanya Cache Tags vs `Cache::forget()`?

**A:** Tags bisa flush **sekelompok** cache tanpa tahu nama key satu-satu. Contoh: `Cache::tags(['books'])->flush()` hapus semua cache bertag 'books'. **Limitasi:** hanya Redis/Memcached.

### Q6: Bagaimana handle cache di multi-server / horizontal scaling?

**A:** Harus pakai **centralized** cache (Redis/Memcached). File cache tidak bisa karena setiap server punya filesystem sendiri → data tidak konsisten.

### Q7: Apa itu cache warming?

**A:** Pre-populate cache **sebelum** user request, via console command atau scheduled task. Menghindari cache miss saat traffic pertama kali masuk.

### Q8: Kapan pakai `Cache::lock()`?

**A:** Saat ada operasi yang **tidak boleh** dijalankan bersamaan — kurangi stok, transfer saldo, generate report. Mencegah race condition.

### Q9: Apa itu TTL (Time To Live)?

**A:** Durasi cache aktif sebelum otomatis dihapus. `now()->addMinutes(30)` = cache bertahan 30 menit. Setelah itu, request selanjutnya query ulang ke DB.

### Q10: Gimana kalau cache dan database tidak sinkron?

**A:** Ini masalah **stale cache**. Solusi: (1) invalidate cache saat data berubah via Observer, (2) gunakan TTL pendek untuk data yang sering berubah, (3) jangan cache data real-time.

---

## 8. Contoh Lengkap: Controller dengan Cache + Invalidation

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BookController extends Controller
{
    private const CACHE_TTL = 1800; // 30 menit

    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'books.all.' . md5($request->getQueryString() ?? '');

        $books = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
            $query = Book::with('author');

            if ($request->has('genre')) {
                $query->where('genre', $request->genre);
            }

            return $query->latest()->get();
        });

        return response()->json(['data' => $books, 'total' => $books->count()]);
    }

    public function show(int $id): JsonResponse
    {
        $book = Cache::remember("books.{$id}", self::CACHE_TTL, function () use ($id) {
            return Book::with('author')->find($id);
        });

        if (!$book) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['data' => $book]);
    }

    public function store(Request $request): JsonResponse
    {
        $book = Book::create($request->validated());

        Cache::forget('books.all.' . md5(''));  // invalidate list cache

        return response()->json(['data' => $book->load('author')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $book = Book::findOrFail($id);
        $book->update($request->validated());

        Cache::forget("books.{$id}");
        Cache::forget('books.all.' . md5(''));

        return response()->json(['data' => $book->load('author')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $book = Book::findOrFail($id);
        $book->delete();

        Cache::forget("books.{$id}");
        Cache::forget('books.all.' . md5(''));

        return response()->json(['message' => 'Deleted']);
    }
}
```

---

## 9. Quick Reference — Semua Method

```php
use Illuminate\Support\Facades\Cache;

// SIMPAN & AMBIL
Cache::put('key', $value, $ttl);              // simpan
Cache::get('key');                             // ambil (null if miss)
Cache::get('key', 'default');                  // ambil + default

// REMEMBER (pattern utama)
Cache::remember('key', $ttl, fn () => ...);    // TTL
Cache::rememberForever('key', fn () => ...);   // selamanya

// HAPUS
Cache::forget('key');                          // hapus satu
Cache::flush();                                // hapus SEMUA

// CEK & LAINNYA
Cache::has('key');                             // true / false
Cache::pull('key');                            // ambil + hapus
Cache::add('key', $value, $ttl);              // simpan jika belum ada
Cache::forever('key', $value);                 // simpan permanent
Cache::increment('key');                       // +1
Cache::decrement('key');                       // -1

// TAGS (Redis / Memcached only)
Cache::tags(['books'])->remember('key', $ttl, fn () => ...);
Cache::tags(['books'])->flush();

// LOCK (prevent race condition)
Cache::lock('key', $seconds)->get();           // non-blocking
Cache::lock('key', $seconds)->block(5, fn () => ...);  // wait max 5s

// MULTI STORE
Cache::store('redis')->get('key');
Cache::store('file')->put('key', $value, $ttl);
```
