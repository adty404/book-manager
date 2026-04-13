# Laravel Repository Pattern — Interview Cheatsheet

---

## 1. Apa itu Repository Pattern?

Layer **perantara** antara Controller dan Model/Eloquent. Controller tidak query DB langsung — semua lewat Repository.

```
Tanpa Repository:
  Controller → Model (Eloquent) → Database

Dengan Repository:
  Controller → Repository → Model (Eloquent) → Database
```

**Kenapa pakai?**

- Controller tetap bersih (tidak ada query logic)
- Logic query bisa di-reuse di banyak controller/job/command
- Lebih mudah di-test (bisa mock repository)
- Kalau ganti ORM/DB, cukup ubah repository — controller tidak berubah

---

## 2. Struktur Folder

```
app/
├── Http/Controllers/
│   └── BookController.php        ← pakai repository, bukan Model langsung
├── Models/
│   └── Book.php
├── Repositories/
│   ├── Interfaces/
│   │   └── BookRepositoryInterface.php   ← kontrak (apa saja yang bisa dilakukan)
│   └── BookRepository.php                ← implementasi (cara melakukannya)
└── Providers/
    └── AppServiceProvider.php    ← bind interface ke implementasi
```

---

## 3. Step by Step Implementasi

### Step 1 — Buat Interface

```php
<?php
// app/Repositories/Interfaces/BookRepositoryInterface.php

namespace App\Repositories\Interfaces;

use Illuminate\Support\Collection;
use App\Models\Book;

interface BookRepositoryInterface
{
    public function getAll(): Collection;
    public function findById(int $id): ?Book;
    public function findByGenre(string $genre): Collection;
    public function create(array $data): Book;
    public function update(int $id, array $data): Book;
    public function delete(int $id): bool;
}
```

**Interface = kontrak.** Hanya mendefinisikan method apa saja yang HARUS ada, tanpa implementasi.

### Step 2 — Buat Implementasi

```php
<?php
// app/Repositories/BookRepository.php

namespace App\Repositories;

use App\Models\Book;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Support\Collection;

class BookRepository implements BookRepositoryInterface
{
    public function getAll(): Collection
    {
        return Book::with('author')->latest()->get();
    }

    public function findById(int $id): ?Book
    {
        return Book::with('author')->find($id);
    }

    public function findByGenre(string $genre): Collection
    {
        return Book::with('author')->where('genre', $genre)->get();
    }

    public function create(array $data): Book
    {
        return Book::create($data);
    }

    public function update(int $id, array $data): Book
    {
        $book = Book::findOrFail($id);
        $book->update($data);
        return $book;
    }

    public function delete(int $id): bool
    {
        return Book::findOrFail($id)->delete();
    }
}
```

### Step 3 — Bind di Service Provider

```php
<?php
// app/Providers/AppServiceProvider.php

use App\Repositories\Interfaces\BookRepositoryInterface;
use App\Repositories\BookRepository;

public function register(): void
{
    $this->app->bind(BookRepositoryInterface::class, BookRepository::class);
}
```

**Ini memberitahu Laravel:** kalau ada yang minta `BookRepositoryInterface`, berikan `BookRepository`.

### Step 4 — Pakai di Controller (Dependency Injection)

```php
<?php
// app/Http/Controllers/BookController.php

namespace App\Http\Controllers;

use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    // Inject via constructor — Laravel resolve otomatis
    public function __construct(
        private BookRepositoryInterface $bookRepo
    ) {}

    public function index(): JsonResponse
    {
        $books = $this->bookRepo->getAll();
        return response()->json(['data' => $books]);
    }

    public function show(int $id): JsonResponse
    {
        $book = $this->bookRepo->findById($id);

        if (!$book) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['data' => $book]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'author_id' => 'required|exists:authors,id',
            'title'     => 'required|string|max:255',
            'genre'     => 'nullable|string',
            'stock'     => 'required|integer|min:0',
            'published' => 'required|boolean',
        ]);

        $book = $this->bookRepo->create($validated);
        return response()->json(['data' => $book], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'stock' => 'required|integer|min:0',
        ]);

        $book = $this->bookRepo->update($id, $validated);
        return response()->json(['data' => $book]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->bookRepo->delete($id);
        return response()->json(['message' => 'Deleted']);
    }
}
```

---

## 4. Perbandingan: Tanpa vs Dengan Repository

### Tanpa Repository

```php
public function index()
{
    $books = Book::with('author')->latest()->get();  // query langsung di controller
    return response()->json(['data' => $books]);
}
```

### Dengan Repository

```php
public function index()
{
    $books = $this->bookRepo->getAll();  // controller tidak tahu cara query
    return response()->json(['data' => $books]);
}
```

---

## 5. Repository + Cache

```php
<?php
// app/Repositories/CachedBookRepository.php

namespace App\Repositories;

use App\Models\Book;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CachedBookRepository implements BookRepositoryInterface
{
    public function __construct(
        private BookRepository $repo  // wrap repository asli
    ) {}

    public function getAll(): Collection
    {
        return Cache::remember('books.all', 1800, fn() => $this->repo->getAll());
    }

    public function findById(int $id): ?Book
    {
        return Cache::remember("books.{$id}", 3600, fn() => $this->repo->findById($id));
    }

    public function findByGenre(string $genre): Collection
    {
        return Cache::remember("books.genre.{$genre}", 1800, fn() => $this->repo->findByGenre($genre));
    }

    public function create(array $data): Book
    {
        Cache::forget('books.all');
        return $this->repo->create($data);
    }

    public function update(int $id, array $data): Book
    {
        Cache::forget("books.{$id}");
        Cache::forget('books.all');
        return $this->repo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        Cache::forget("books.{$id}");
        Cache::forget('books.all');
        return $this->repo->delete($id);
    }
}
```

Ganti binding di provider:

```php
// AppServiceProvider
$this->app->bind(BookRepositoryInterface::class, CachedBookRepository::class);
```

Controller **tidak berubah sama sekali** — ini kekuatan Repository Pattern.

---

## 6. Konsep Kunci untuk Interview

### Dependency Injection (DI)

```php
// Controller tidak buat object sendiri — Laravel inject otomatis
public function __construct(
    private BookRepositoryInterface $bookRepo  // ← DI
) {}
```

### Interface vs Implementasi

- **Interface** = kontrak: "apa" yang bisa dilakukan
- **Implementasi** = "bagaimana" melakukannya
- Bisa ganti implementasi tanpa ubah controller

### Service Container & Binding

```php
// Memberitahu Laravel: interface → class mana
$this->app->bind(BookRepositoryInterface::class, BookRepository::class);

// Kalau mau singleton (1 instance reuse terus):
$this->app->singleton(BookRepositoryInterface::class, BookRepository::class);
```

---

## 7. Pertanyaan Interview

| Pertanyaan                                      | Jawaban Singkat                                                          |
| ----------------------------------------------- | ------------------------------------------------------------------------ |
| Kenapa pakai Repository Pattern?                | Memisahkan query logic dari controller, lebih reusable dan testable      |
| Kapan TIDAK perlu?                              | Project kecil/CRUD sederhana — over-engineering                          |
| Bedanya `bind` vs `singleton`?                  | `bind` = instance baru tiap resolve, `singleton` = 1 instance di-reuse   |
| Bagaimana cara test controller yang pakai repo? | Mock interface-nya, inject mock ke controller                            |
| Apa hubungannya DI dan Repository?              | DI = mekanisme inject dependensi, Repository = salah satu yang di-inject |
| Bagaimana tambah caching tanpa ubah controller? | Buat CachedRepository yang wrap repository asli, ganti binding           |
