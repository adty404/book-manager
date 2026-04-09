# Laravel Console Commands — Comprehensive Study Notes

## 1. Membuat Command

```bash
php artisan make:command NamaCommand
```

File dibuat di `app/Console/Commands/NamaCommand.php`.
Laravel auto-discover semua command di folder tersebut (Laravel 11+).

Bisa juga langsung dengan signature:

```bash
php artisan make:command SendWeeklyReport --command=report:weekly
```

---

## 2. Struktur Dasar

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NamaCommand extends Command
{
    protected $signature = 'namespace:nama-command {argument} {--option=}';
    protected $description = 'Deskripsi command ini';

    public function handle(): int
    {
        // logic di sini

        return Command::SUCCESS;
    }
}
```

### Konvensi Penamaan Signature

```
domain:action          → book:list, user:sync, cache:clear
domain:action-detail   → book:import-csv, user:reset-password
```

---

## 3. Signature — Argument & Option

### 3.1 Tabel Referensi

| Syntax            | Tipe                    | Wajib? | Contoh Input                           |
| ----------------- | ----------------------- | ------ | -------------------------------------- |
| `{name}`          | Argument wajib          | Ya     | `artisan cmd Ahmad`                    |
| `{name?}`         | Argument opsional       | Tidak  | `artisan cmd` atau `artisan cmd Ahmad` |
| `{name=default}`  | Argument + default      | Tidak  | `artisan cmd` → "default"              |
| `{name*}`         | Argument array wajib    | Ya     | `artisan cmd 1 2 3`                    |
| `{name?*}`        | Argument array opsional | Tidak  | `artisan cmd` atau `artisan cmd 1 2`   |
| `{--flag}`        | Option boolean          | -      | `artisan cmd --flag`                   |
| `{--key=}`        | Option value (null)     | -      | `artisan cmd --key=value`              |
| `{--key=default}` | Option value + default  | -      | `artisan cmd` → "default"              |
| `{--S\|key=}`     | Option + shortcut       | -      | `artisan cmd -S value`                 |

### 3.2 Contoh Argument Wajib

```php
protected $signature = 'book:show {id}';
```

```bash
php artisan book:show 5
# $this->argument('id')  → "5"
```

```bash
php artisan book:show
# ERROR: Not enough arguments (missing: "id")
```

### 3.3 Contoh Argument Opsional

```php
protected $signature = 'book:list {genre?}';
```

```bash
php artisan book:list
# $this->argument('genre') → null

php artisan book:list fiction
# $this->argument('genre') → "fiction"
```

### 3.4 Contoh Argument dengan Default

```php
protected $signature = 'book:list {genre=all}';
```

```bash
php artisan book:list
# $this->argument('genre') → "all"

php artisan book:list fiction
# $this->argument('genre') → "fiction"
```

### 3.5 Contoh Argument Array

```php
protected $signature = 'book:delete {ids*}';
```

```bash
php artisan book:delete 1 2 3
# $this->argument('ids') → ["1", "2", "3"]
```

### 3.6 Contoh Option Boolean (Flag)

```php
protected $signature = 'book:list {--published}';
```

```bash
php artisan book:list
# $this->option('published') → false

php artisan book:list --published
# $this->option('published') → true
```

### 3.7 Contoh Option dengan Value

```php
protected $signature = 'book:list {--author=}';
```

```bash
php artisan book:list
# $this->option('author') → null

php artisan book:list --author=Ahmad
# $this->option('author') → "Ahmad"

php artisan book:list --author="Ahmad Dahlan"
# $this->option('author') → "Ahmad Dahlan"
```

### 3.8 Contoh Option dengan Default

```php
protected $signature = 'book:list {--limit=10}';
```

```bash
php artisan book:list
# $this->option('limit') → "10"

php artisan book:list --limit=25
# $this->option('limit') → "25"
```

### 3.9 Contoh Option Shortcut

```php
protected $signature = 'book:list {--L|limit=10} {--P|published}';
```

```bash
php artisan book:list -L 5 -P
# sama dengan:
php artisan book:list --limit=5 --published
```

### 3.10 Contoh Option Array

```php
protected $signature = 'book:list {--tag=*}';
```

```bash
php artisan book:list --tag=php --tag=laravel
# $this->option('tag') → ["php", "laravel"]
```

### 3.11 Deskripsi pada Argument & Option

```php
protected $signature = 'book:create
    {title : Judul buku yang akan dibuat}
    {author? : Nama author (opsional)}
    {--isbn= : Nomor ISBN buku}
    {--P|published : Tandai sebagai sudah diterbitkan}';
```

Deskripsi muncul di `php artisan help book:create`:

```
Arguments:
  title       Judul buku yang akan dibuat
  author      Nama author (opsional)

Options:
  --isbn      Nomor ISBN buku
  -P, --published  Tandai sebagai sudah diterbitkan
```

---

## 4. Mengambil Input

```php
public function handle(): int
{
    // === ARGUMENT ===
    $title = $this->argument('title');           // satu argument
    $allArgs = $this->arguments();               // semua argument sebagai array
    // Return: ['command' => 'book:create', 'title' => 'Laravel', ...]

    // === OPTION ===
    $limit = $this->option('limit');             // satu option
    $allOpts = $this->options();                 // semua option sebagai array
    // Return: ['limit' => '10', 'published' => false, ...]

    // === CASTING (argument & option selalu return string) ===
    $limit = (int) $this->option('limit');
    $minBooks = (int) $this->option('min-books');

    return Command::SUCCESS;
}
```

---

## 5. Output ke Terminal

### 5.1 Text Output

```php
$this->info('Operasi berhasil!');           // teks hijau
$this->warn('Perhatian: data kosong');      // teks kuning
$this->error('Terjadi kesalahan!');         // teks merah (background merah)
$this->line('Teks biasa tanpa warna');      // teks tanpa style
$this->newLine();                           // baris kosong
$this->newLine(3);                          // 3 baris kosong
$this->comment('Ini komentar');             // teks kuning (tanpa background)

// Laravel 9+ — custom styling
$this->components->info('Pesan info');
$this->components->warn('Pesan warning');
$this->components->error('Pesan error');
$this->components->alert('ALERT!');
```

### 5.2 Tabel

```php
$headers = ['ID', 'Judul', 'Author'];
$rows = [
    [1, 'Laravel Basics', 'Ahmad'],
    [2, 'PHP Advanced', 'Budi'],
    [3, 'Vue.js Guide', 'Citra'],
];

$this->table($headers, $rows);
```

Output:

```
+----+----------------+--------+
| ID | Judul          | Author |
+----+----------------+--------+
| 1  | Laravel Basics | Ahmad  |
| 2  | PHP Advanced   | Budi   |
| 3  | Vue.js Guide   | Citra  |
+----+----------------+--------+
```

### 5.3 Tabel dari Eloquent Collection

```php
$authors = Author::withCount('books')->get();

$this->table(
    ['ID', 'Nama', 'Jumlah Buku'],
    $authors->map(fn ($a) => [$a->id, $a->name, $a->books_count])
);
```

### 5.4 Progress Bar — Basic

```php
$books = Book::all();

$bar = $this->output->createProgressBar($books->count());
$bar->start();

foreach ($books as $book) {
    // proses setiap buku...
    sleep(1); // simulasi
    $bar->advance();
}

$bar->finish();
$this->newLine(); // penting agar output berikutnya tidak nyambung
$this->info('Selesai!');
```

### 5.5 Progress Bar — withProgressBar (shortcut)

```php
$books = Book::all();

$this->withProgressBar($books, function ($book) {
    // proses setiap buku...
});

$this->newLine();
$this->info('Selesai!');
```

---

## 6. Input Interaktif

### 6.1 Semua Method Interaktif

```php
// Jawaban bebas
$name = $this->ask('Siapa nama author?');

// Jawaban bebas dengan default
$name = $this->ask('Siapa nama author?', 'Anonymous');

// Input tersembunyi (untuk password dsb)
$password = $this->secret('Masukkan password:');

// Ya/Tidak
$confirmed = $this->confirm('Yakin ingin menghapus?');           // default: false
$confirmed = $this->confirm('Yakin ingin menghapus?', true);     // default: true

// Pilihan (single select)
$role = $this->choice('Pilih role:', ['admin', 'editor', 'viewer'], 0);
// parameter 3 = index default

// Autocomplete
$name = $this->anticipate('Cari author:', ['Ahmad', 'Budi', 'Citra']);

// Autocomplete dari closure (dynamic)
$name = $this->anticipate('Cari author:', function ($input) {
    return Author::where('name', 'like', "%{$input}%")->pluck('name')->toArray();
});
```

### 6.2 Contoh Lengkap: Command Interaktif

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Book;
use App\Models\Author;

class CreateBookInteractive extends Command
{
    protected $signature = 'book:create-interactive';
    protected $description = 'Buat buku baru secara interaktif';

    public function handle(): int
    {
        $title = $this->ask('Judul buku?');

        $authorName = $this->anticipate('Nama author:', function ($input) {
            return Author::where('name', 'like', "%{$input}%")
                ->pluck('name')->toArray();
        });

        $author = Author::firstOrCreate(['name' => $authorName]);

        $year = $this->ask('Tahun terbit?', date('Y'));

        $this->info("Ringkasan:");
        $this->table(
            ['Field', 'Value'],
            [
                ['Judul', $title],
                ['Author', $authorName],
                ['Tahun', $year],
            ]
        );

        if (!$this->confirm('Simpan data ini?', true)) {
            $this->warn('Dibatalkan.');
            return Command::SUCCESS;
        }

        Book::create([
            'title' => $title,
            'author_id' => $author->id,
            'year' => $year,
        ]);

        $this->info("Buku '{$title}' berhasil disimpan!");

        return Command::SUCCESS;
    }
}
```

---

## 7. Menjalankan Command

### 7.1 Dari Terminal

```bash
# Jalankan command
php artisan book:stats
php artisan book:stats --min-books=3
php artisan book:list Ahmad --limit=5

# Lihat semua command yang tersedia
php artisan list

# Filter command berdasarkan namespace
php artisan list book

# Detail satu command (argument, option, deskripsi)
php artisan help book:stats
```

### 7.2 Dari Kode PHP (Controller, Service, dll)

```php
use Illuminate\Support\Facades\Artisan;

// Panggil command, tunggu sampai selesai
Artisan::call('book:stats', ['--min-books' => 3]);

// Ambil output-nya
$output = Artisan::output();

// Panggil di background via queue
Artisan::queue('book:stats', ['--min-books' => 3]);

// Dengan argument
Artisan::call('book:show', ['id' => 5]);

// Dengan option array
Artisan::call('book:list', ['--tag' => ['php', 'laravel']]);
```

### 7.3 Dari Command Lain

```php
public function handle(): int
{
    // Panggil command lain (output tetap tampil)
    $this->call('book:stats', ['--min-books' => 3]);

    // Panggil command lain (output disembunyikan)
    $this->callSilently('cache:clear');

    return Command::SUCCESS;
}
```

---

## 8. Closure Command (di routes/console.php)

Untuk command sederhana, tidak perlu buat file class terpisah:

```php
// routes/console.php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Book;

// Command sederhana
Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// Closure command dengan argument & option
Artisan::command('book:quick-list {--limit=5}', function () {
    $limit = $this->option('limit');
    $books = Book::with('author')->limit($limit)->get();

    $this->table(
        ['ID', 'Judul', 'Author'],
        $books->map(fn ($b) => [$b->id, $b->title, $b->author->name])
    );
})->purpose('Tampilkan daftar buku cepat');
```

---

## 9. Schedule Command (Cron)

### 9.1 Registrasi Schedule

Di `routes/console.php` (Laravel 11+):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('book:stats')->daily();
Schedule::command('book:stats --min-books=5')->weeklyOn(1, '08:00'); // Senin jam 8

// Schedule closure
Schedule::call(function () {
    \Log::info('Custom task berjalan');
})->hourly();
```

### 9.2 Frekuensi Lengkap

| Method                     | Keterangan                        |
| -------------------------- | --------------------------------- |
| `->everyMinute()`          | Setiap menit                      |
| `->everyTwoMinutes()`      | Setiap 2 menit                    |
| `->everyFiveMinutes()`     | Setiap 5 menit                    |
| `->everyTenMinutes()`      | Setiap 10 menit                   |
| `->everyFifteenMinutes()`  | Setiap 15 menit                   |
| `->everyThirtyMinutes()`   | Setiap 30 menit                   |
| `->hourly()`               | Setiap jam                        |
| `->hourlyAt(15)`           | Setiap jam di menit ke-15         |
| `->daily()`                | Setiap hari jam 00:00             |
| `->dailyAt('13:00')`       | Setiap hari jam 13:00             |
| `->twiceDaily(1, 13)`      | Jam 01:00 dan 13:00               |
| `->weekly()`               | Setiap Minggu jam 00:00           |
| `->weeklyOn(1, '08:00')`   | Setiap Senin jam 08:00 (0=Minggu) |
| `->monthly()`              | Setiap bulan tanggal 1            |
| `->monthlyOn(15, '09:00')` | Tanggal 15 jam 09:00              |
| `->quarterly()`            | Setiap kuartal                    |
| `->yearly()`               | Setiap tahun                      |
| `->cron('0 8 * * 1-5')`    | Custom cron expression            |

### 9.3 Constraint Tambahan

```php
Schedule::command('report:daily')
    ->daily()
    ->weekdays()               // hanya Senin-Jumat
    ->between('08:00', '17:00') // hanya jam kerja
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()     // jangan jalankan jika masih berjalan
    ->onOneServer()            // hanya 1 server di multi-server
    ->runInBackground();       // jalankan di background
```

### 9.4 Output Schedule

```php
Schedule::command('book:stats')
    ->daily()
    ->sendOutputTo('/tmp/stats.log')           // simpan output ke file
    ->emailOutputTo('admin@example.com')       // kirim output via email
    ->emailOutputOnFailure('dev@example.com'); // email hanya jika gagal
```

### 9.5 Hooks (Before/After)

```php
Schedule::command('book:stats')
    ->daily()
    ->before(function () {
        \Log::info('book:stats akan dijalankan');
    })
    ->after(function () {
        \Log::info('book:stats selesai');
    })
    ->onSuccess(function () {
        \Log::info('book:stats berhasil');
    })
    ->onFailure(function () {
        \Log::error('book:stats gagal!');
    });
```

### 9.6 Aktifkan Scheduler di Server

Cukup satu entri cron:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Cek schedule yang terdaftar:

```bash
php artisan schedule:list
```

---

## 10. Exit Code & Error Handling

### 10.1 Exit Code

```php
public function handle(): int
{
    return Command::SUCCESS;   // 0 — berhasil
    return Command::FAILURE;   // 1 — gagal
    return Command::INVALID;   // 2 — input tidak valid
}
```

### 10.2 Error Handling

```php
public function handle(): int
{
    try {
        $this->info('Memproses...');

        // logic yang mungkin error
        $count = Book::where('year', '<', 2000)->delete();
        $this->info("Menghapus {$count} buku lama.");

        return Command::SUCCESS;

    } catch (\Exception $e) {
        $this->error("Error: {$e->getMessage()}");
        return Command::FAILURE;
    }
}
```

### 10.3 Validasi Input Manual

```php
public function handle(): int
{
    $id = $this->argument('id');

    if (!is_numeric($id)) {
        $this->error('ID harus berupa angka!');
        return Command::INVALID;
    }

    $book = Book::find($id);

    if (!$book) {
        $this->error("Buku dengan ID {$id} tidak ditemukan.");
        return Command::FAILURE;
    }

    $this->info("Judul: {$book->title}");
    return Command::SUCCESS;
}
```

---

## 11. Dependency Injection di Command

Laravel otomatis inject dependency lewat constructor atau method `handle()`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BookService;

class SyncBooks extends Command
{
    protected $signature = 'book:sync';
    protected $description = 'Sinkronisasi data buku';

    // Inject di constructor
    public function __construct(
        private BookService $bookService
    ) {
        parent::__construct();
    }

    // Atau inject di handle()
    public function handle(BookService $bookService): int
    {
        $result = $bookService->syncAll();
        $this->info("Berhasil sync {$result} buku.");

        return Command::SUCCESS;
    }
}
```

---

## 12. Testing Console Command

### 12.1 Basic Test

```php
// tests/Feature/BookStatsCommandTest.php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('book:stats menampilkan tabel author', function () {
    // Arrange
    $author = \App\Models\Author::factory()->create(['name' => 'Ahmad']);
    \App\Models\Book::factory()->count(3)->create(['author_id' => $author->id]);

    // Act & Assert
    $this->artisan('book:stats')
        ->expectsTable(
            ['Nama Author', 'Jumlah Buku'],
            [['Ahmad', 3]]
        )
        ->assertExitCode(0);
});

test('book:stats filter by min-books', function () {
    $author1 = \App\Models\Author::factory()->create(['name' => 'Ahmad']);
    $author2 = \App\Models\Author::factory()->create(['name' => 'Budi']);
    \App\Models\Book::factory()->count(5)->create(['author_id' => $author1->id]);
    \App\Models\Book::factory()->count(1)->create(['author_id' => $author2->id]);

    $this->artisan('book:stats --min-books=3')
        ->expectsOutputToContain('Ahmad')
        ->doesntExpectOutputToContain('Budi')
        ->assertExitCode(0);
});

test('book:stats tanpa data menampilkan warning', function () {
    $this->artisan('book:stats')
        ->expectsOutputToContain('Tidak ada data author')
        ->assertExitCode(0);
});
```

### 12.2 Test Input Interaktif

```php
test('book:create-interactive membuat buku', function () {
    $author = \App\Models\Author::factory()->create(['name' => 'Ahmad']);

    $this->artisan('book:create-interactive')
        ->expectsQuestion('Judul buku?', 'Laravel Pro')
        ->expectsQuestion('Nama author:', 'Ahmad')
        ->expectsQuestion('Tahun terbit?', '2026')
        ->expectsConfirmation('Simpan data ini?', 'yes')
        ->expectsOutputToContain('berhasil disimpan')
        ->assertExitCode(0);

    $this->assertDatabaseHas('books', ['title' => 'Laravel Pro']);
});
```

### 12.3 Test Assert Methods

```php
$this->artisan('command')
    ->assertExitCode(0)                          // exit code tertentu
    ->assertSuccessful()                         // exit code 0
    ->assertFailed()                             // exit code bukan 0
    ->expectsOutput('teks persis')               // output persis sama
    ->expectsOutputToContain('sebagian teks')    // output mengandung
    ->doesntExpectOutput('teks')                 // output TIDAK mengandung
    ->doesntExpectOutputToContain('teks')        // output TIDAK mengandung
    ->expectsQuestion('prompt?', 'jawaban')      // simulasi input
    ->expectsConfirmation('Yakin?', 'yes')       // simulasi confirm
    ->expectsChoice('Pilih:', 'opsi1', ['opsi1', 'opsi2'])
    ->expectsTable(['Header'], [['Row']]);       // tabel persis
```

---

## 13. Build-in Artisan Commands yang Penting

```bash
# Database
php artisan migrate
php artisan migrate:rollback
php artisan migrate:fresh --seed
php artisan db:seed
php artisan db:seed --class=BookSeeder

# Cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear        # clear semua sekaligus

# Generate
php artisan make:model Book -mfsc
#   -m  Migration
#   -f  Factory
#   -s  Seeder
#   -c  Controller

php artisan make:command NamaCommand
php artisan make:controller BookController --resource
php artisan make:middleware CheckRole

# Info
php artisan route:list
php artisan schedule:list
php artisan about
```

---

## 14. Best Practices

1. **Gunakan `$signature` property**, bukan `#[Signature]` attribute, agar tidak konflik
2. **Return exit code** — selalu return `Command::SUCCESS`, `FAILURE`, atau `INVALID`
3. **Pisahkan logic** ke Service/Repository — command hanya sebagai entry point (thin command)
4. **Validasi argument/option** di awal `handle()` sebelum proses
5. **Tambahkan deskripsi** pada setiap argument/option di signature
6. **Gunakan dependency injection** di `handle()` untuk testability
7. **Handle exception** dengan try-catch dan return exit code yang tepat
8. **Tulis test** untuk command penting menggunakan `$this->artisan()`
9. **Jangan cetak data sensitif** (password, token) ke output

---

## 15. Contoh Nyata di Project Ini

File: `app/Console/Commands/ShowAutorStats.php`

```php
protected $signature = 'book:stats {--min-books=0 : Hanya tampilkan author dengan minimal buku tertentu}';

public function handle(): void
{
    $minBooks = $this->option('min-books');

    $authors = Author::withCount('books')
        ->groupBy('id')
        ->having('books_count', '>=', $minBooks)
        ->get();

    if ($authors->isEmpty()) {
        $this->warn("Tidak ada data author yang memenuhi kriteria");
        return;
    }

    $this->info("Menampilkan Statistik Author:");
    $this->table(
        ['Nama Author', 'Jumlah Buku'],
        $authors->map(fn($a) => [$a->name, $a->books_count])
    );
}
```

---

## Topik Selanjutnya

- [ ] **Caching** — `Cache::remember()`, driver, TTL
- [ ] **Queue** — job, worker, failed jobs
- [ ] **Repository Pattern** — abstraksi layer data
