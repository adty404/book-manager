# Laravel Eloquent — Interview Cheatsheet

---

## 1. Return Type — Wajib Hafal

| Method            | Return                 | Cek null      |
| ----------------- | ---------------------- | ------------- |
| `find($id)`       | `Model\|null`          | `if (!$book)` |
| `findOrFail($id)` | `Model` (atau 404)     | tidak perlu   |
| `first()`         | `Model\|null`          | `if (!$book)` |
| `firstOrFail()`   | `Model` (atau 404)     | tidak perlu   |
| `get()`           | `Collection`           | `->isEmpty()` |
| `all()`           | `Collection`           | `->isEmpty()` |
| `query()`         | `Builder`              | chain dulu    |
| `create()`        | `Model`                | tidak perlu   |
| `count()`         | `int`                  | —             |
| `exists()`        | `bool`                 | —             |
| `paginate()`      | `LengthAwarePaginator` | `->isEmpty()` |

---

## 2. Akses Data

### Model → pakai `->`

```php
$book = Book::find(1);  // Model

$book->id;
$book->title;
$book->genre;
$book->author;        // relasi → Model
$book->author->name;  // relasi → field
```

### Collection → loop atau method helper

```php
$books = Book::all();  // Collection

foreach ($books as $book) {
    echo $book->title;  // tiap $book adalah Model, akses pakai ->
}

$books->first()->title;   // item pertama, lalu ->
$books->last()->title;    // item terakhir, lalu ->
```

### Setelah `toArray()` → pakai `[]`

```php
$arr = $book->toArray();   // Model → array
$arr['title'];             // pakai bracket, bukan ->
$arr['author']['name'];    // relasi juga jadi array

$arr = $books->toArray();  // Collection → array of arrays
$arr[0]['title'];
```

### Setelah `toJson()` → string JSON, biasanya untuk response

```php
$json = $book->toJson();   // string: '{"id":1,"title":"..."}'
// untuk akses data, decode dulu:
$data = json_decode($json);         // object → $data->title
$data = json_decode($json, true);   // array  → $data['title']
```

> **Di controller Laravel**, pakai `response()->json($book)` — tidak perlu manual `toJson()`.
> Laravel otomatis convert Model/Collection ke JSON response.

---

## 3. Builder — Query Chaining

`query()` / `where()` / `with()` belum ke DB. Baru jalan saat **terminasi**:

```php
$query = Book::query();              // Builder
$query->where('genre', 'Fiction');   // masih Builder
$query->where('stock', '>', 5);      // masih Builder

// Terminasi → baru eksekusi ke DB:
$query->get();          // Collection
$query->first();        // Model|null
$query->count();        // int
$query->exists();       // bool
$query->paginate(10);   // Paginator
```

---

## 4. CRUD Dasar

```php
// CREATE
$book = Book::create(['title' => 'Laravel', 'stock' => 5]);  // return Model

// READ
Book::all();                          // semua → Collection
Book::find(1);                        // by ID → Model|null
Book::findOrFail(1);                  // by ID → Model atau 404
Book::where('genre', 'Fiction')->get(); // filter → Collection
Book::where('stock', '>', 5)->first(); // satu → Model|null

// UPDATE — cara 1: lewat model
$book = Book::findOrFail(1);
$book->title = 'New Title';
$book->save();                        // return bool

// UPDATE — cara 2: langsung (tidak trigger Model events)
Book::where('id', 1)->update(['title' => 'New Title']); // return int (rows affected)

// DELETE
$book = Book::findOrFail(1);
$book->delete();                      // return bool

Book::destroy(1);                     // by ID, return int
Book::destroy([1, 2, 3]);            // multiple ID
```

---

## 5. Relasi — Cara Akses

```php
// Eager loading (1 query + 1 query relasi) — gunakan ini
$books = Book::with('author')->get();
foreach ($books as $book) {
    echo $book->author->name;   // tidak ada N+1 query
}

// Lazy loading (query baru tiap akses relasi) — hindari di loop
$books = Book::all();
foreach ($books as $book) {
    echo $book->author->name;   // N+1 query problem!
}

// Kondisi di relasi
Book::with(['author' => function ($q) {
    $q->where('active', true);
}])->get();
```

---

## 6. Collection Methods — Yang Paling Sering Dipakai

```php
$books = Book::all();

$books->count();                      // jumlah → int
$books->isEmpty();                    // kosong? → bool
$books->isNotEmpty();                 // tidak kosong? → bool
$books->first();                      // item pertama → Model|null
$books->last();                       // item terakhir → Model|null

$books->pluck('title');               // ambil satu kolom → Collection
$books->pluck('title', 'id');         // id => title map → Collection

$books->where('genre', 'Fiction');    // filter in-memory → Collection
$books->sortBy('stock');              // sort asc → Collection
$books->sortByDesc('stock');          // sort desc → Collection

$books->sum('stock');                 // → int/float
$books->avg('stock');                 // → float
$books->max('stock');                 // → nilai max
$books->min('stock');                 // → nilai min

$books->map(fn($b) => $b->title);    // transform → Collection baru
$books->filter(fn($b) => $b->stock > 5); // filter → Collection baru

$books->toArray();                    // → array of arrays
```

---

## 7. Scopes — Query Reusable

```php
// Di Model Book:
public function scopePublished($query)
{
    return $query->where('published', true);
}

public function scopeByGenre($query, string $genre)
{
    return $query->where('genre', $genre);
}

// Pemakaian (tanpa prefix 'scope'):
Book::published()->get();
Book::byGenre('Fiction')->get();
Book::published()->byGenre('Fiction')->count();
```

---

## 8. DB:: — Query Tanpa Model

`DB::` adalah Query Builder level rendah — langsung ke database tanpa Model/Eloquent.

### Kapan pakai `DB::` vs Eloquent?

|             | `DB::`                            | Eloquent             |
| ----------- | --------------------------------- | -------------------- |
| Kecepatan   | Lebih cepat                       | Sedikit lebih lambat |
| Return type | `stdClass` / array                | Model / Collection   |
| Fitur Model | Tidak ada (no casting, no events) | Ada semua            |
| Cocok untuk | Laporan, bulk query, raw SQL      | CRUD biasa           |

### Syntax Dasar

```php
use Illuminate\Support\Facades\DB;

// SELECT semua
DB::table('books')->get();                     // Collection of stdClass
DB::table('books')->first();                   // stdClass|null

// SELECT dengan kondisi
DB::table('books')->where('genre', 'Fiction')->get();
DB::table('books')->where('stock', '>', 5)->first();

// SELECT kolom tertentu
DB::table('books')->select('id', 'title', 'stock')->get();

// INSERT
DB::table('books')->insert([
    'title' => 'Laravel', 'stock' => 5, 'author_id' => 1,
]);

// INSERT dan dapat ID
$id = DB::table('books')->insertGetId(['title' => 'Laravel', 'stock' => 5, 'author_id' => 1]);

// UPDATE
DB::table('books')->where('id', 1)->update(['stock' => 99]);  // return int (rows affected)

// DELETE
DB::table('books')->where('id', 1)->delete();  // return int

// COUNT / AGGREGATE
DB::table('books')->count();
DB::table('books')->sum('stock');
DB::table('books')->avg('stock');
DB::table('books')->max('stock');
DB::table('books')->where('genre', 'Fiction')->count();
```

### Return Type `DB::` — stdClass bukan Model

```php
$book = DB::table('books')->find(1);  // stdClass

// Akses pakai -> seperti Model, tapi TIDAK punya method Eloquent
$book->title;    // ✅
$book->genre;    // ✅
$book->save();   // ❌ ERROR — stdClass tidak punya method Eloquent
$book->author;   // ❌ ERROR — tidak ada relasi
```

### Raw SQL — Kalau butuh query yang tidak bisa di-chain

```php
// Raw select
DB::select('SELECT * FROM books WHERE genre = ?', ['Fiction']);   // array of stdClass
DB::select('SELECT COUNT(*) as total FROM books')[0]->total;

// Raw update/delete
DB::statement('UPDATE books SET stock = 0 WHERE published = 0');
DB::affectingStatement('DELETE FROM books WHERE stock = ?', [0]); // return rows affected
```

### JOIN

```php
DB::table('books')
    ->join('authors', 'books.author_id', '=', 'authors.id')
    ->select('books.title', 'authors.name as author_name', 'books.stock')
    ->where('books.genre', 'Fiction')
    ->get();
// → Collection of stdClass dengan field title, author_name, stock
```

### `DB::` vs Eloquent — Contoh yang sama

```php
// Eloquent — return Collection of Model
Book::with('author')->where('genre', 'Fiction')->get();

// DB:: — return Collection of stdClass, lebih cepat, tanpa relasi
DB::table('books')
    ->join('authors', 'books.author_id', '=', 'authors.id')
    ->where('books.genre', 'Fiction')
    ->select('books.*', 'authors.name as author_name')
    ->get();
```

---

## 9. Pertanyaan Interview yang Sering Muncul

| Pertanyaan                      | Jawaban Singkat                                                        |
| ------------------------------- | ---------------------------------------------------------------------- |
| Bedanya `find` vs `findOrFail`? | `find` return null kalau tidak ada, `findOrFail` throw 404             |
| Apa itu N+1 problem?            | Akses relasi di dalam loop tanpa eager loading → banyak query          |
| Solusi N+1?                     | `with('relasi')` saat query                                            |
| Bedanya `save()` vs `update()`? | `save()` di instance model, `update()` di Builder (mass update)        |
| `get()` vs `all()`?             | `all()` = shortcut `query()->get()` tanpa kondisi, hasilnya sama       |
| Kapan pakai `toArray()`?        | Saat perlu plain array, misal passing ke view/log, bukan response JSON |
