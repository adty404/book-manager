<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BookController extends Controller
{
    // TTL terpusat — mudah diubah
    private const CACHE_TTL = 1800; // 30 menit

    /**
     * GET /api/books
     * Ambil semua buku (dengan cache)
     */
    public function index(Request $request): JsonResponse
    {
        // Cache key dinamis berdasarkan query params
        $cacheKey = 'books.all.' . md5($request->getQueryString() ?? '');

        $books = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
            $query = Book::with('author');

            // Filter by genre
            if ($request->has('genre')) {
                $query->where('genre', $request->genre);
            }

            // Filter by published
            if ($request->has('published')) {
                $query->where('published', filter_var($request->published, FILTER_VALIDATE_BOOLEAN));
            }

            return $query->latest()->get()->toArray();
        });

        return response()->json([
            'data'    => $books,
            'total'   => count($books),
        ]);
    }

    /**
     * GET /api/books/{id}
     * Ambil satu buku (dengan cache)
     */
    public function show(int $id): JsonResponse
    {
        $book = Cache::remember("books.{$id}", self::CACHE_TTL, function () use ($id) {
            $found = Book::with('author')->find($id);
            return $found ? $found->toArray() : null;
        });

        if (!$book) {
            return response()->json(['message' => 'Buku tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $book]);
    }

    /**
     * POST /api/books
     * Simpan buku baru → invalidate cache
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'author_id' => 'required|exists:authors,id',
            'title'     => 'required|string|max:255',
            'genre'     => 'nullable|string|max:100',
            'stock'     => 'integer|min:0',
            'published' => 'boolean',
        ]);

        $book = Book::create($validated);
        $book->load('author');

        // Invalidate semua cache books (pakai prefix flush)
        $this->clearBooksCache();

        return response()->json(['data' => $book], 201);
    }

    /**
     * PUT /api/books/{id}
     * Update buku → invalidate cache
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json(['message' => 'Buku tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'author_id' => 'sometimes|exists:authors,id',
            'title'     => 'sometimes|string|max:255',
            'genre'     => 'nullable|string|max:100',
            'stock'     => 'sometimes|integer|min:0',
            'published' => 'sometimes|boolean',
        ]);

        $book->update($validated);
        $book->load('author');

        // Invalidate cache buku ini + list
        Cache::forget("books.{$id}");
        $this->clearBooksCache();

        return response()->json(['data' => $book]);
    }

    /**
     * DELETE /api/books/{id}
     * Hapus buku → invalidate cache
     */
    public function destroy(int $id): JsonResponse
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json(['message' => 'Buku tidak ditemukan.'], 404);
        }

        $book->delete();

        Cache::forget("books.{$id}");
        $this->clearBooksCache();

        return response()->json(['message' => 'Buku berhasil dihapus.']);
    }

    /**
     * Hapus semua cache books.all.*
     * (cache key dinamis berdasarkan query string)
     */
    private function clearBooksCache(): void
    {
        // Karena key dinamis (ada md5), kita simpan daftarnya atau
        // gunakan tag jika pakai Redis. Untuk file/database driver:
        Cache::forget('books.all.' . md5(''));     // tanpa filter
        Cache::forget('books.all.' . md5(null));   // null query
    }
}
