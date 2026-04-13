<?php

namespace App\Http\Controllers;

use App\Jobs\ExportBooksToCSV;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $cacheKey = $request->has('genre') ? 'books.all.' . $request->genre : 'books.all';

        $books = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
            $query = Book::query();

            if ($request->has('genre')) {
                $query->where('genre', $request->genre);
            }

            return $query->get();
        });

        if ($books->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada buku yang ditemukan',
                'data' => [],
                'total' => 0,
            ]);
        }

        return response()->json([
            'data' => $books,
            'total' => $books->count(),
        ]);
    }

    /**
     * GET /api/books/{id}
     * Ambil satu buku (dengan cache)
     */
    public function show(int $id): JsonResponse
    {
        $cacheKey = "books.{$id}";

        $book = Cache::remember($cacheKey, 3600, function () use ($id) {
            return Book::find($id);
        });


        if (!$book) {
            return response()->json([
                'message' => 'Buku tidak ditemukan',
                'data' => null,
            ], 404);
        }

        Cache::increment($cacheKey . '.views', 1);
        Log::info("[VIEW] Book ID {$id} sudah dilihat " . Cache::get($cacheKey . '.views', 0) . " kali");

        return response()->json([
            'data' => $book,
        ]);
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
            'stock'     => 'required|integer|min:0',
            'published' => 'required|boolean',
        ]);

        $book = Book::create($validated);

        Cache::forget('books.all');
        if ($book->genre) {
            Cache::forget('books.all.' . $book->genre);
        }

        return response()->json(['data' => $book], 201);
    }

    /**
     * PUT /api/books/{id}
     * Update buku → invalidate cache
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $book = Book::findOrFail($id);
        $oldGenre = $book->genre;

        $validated = $request->validate([
            'author_id' => 'required|exists:authors,id',
            'title'     => 'required|string|max:255',
            'genre'     => 'nullable|string|max:100',
            'stock'     => 'required|integer|min:0',
            'published' => 'required|boolean',
        ]);

        $book->update($validated);

        if ($oldGenre) Cache::forget("books.all.{$oldGenre}");
        if ($book->genre && $book->genre !== $oldGenre) {
            Cache::forget("books.all.{$book->genre}");
        }
        Cache::forget("books.{$id}");
        Cache::forget('books.all');

        return response()->json(['data' => $book]);
    }

    /**
     * DELETE /api/books/{id}
     * Hapus buku → invalidate cache
     */
    public function destroy(int $id): JsonResponse
    {
        $book = Book::findOrFail($id);
        $book->delete();

        Cache::forget("books.{$id}");
        Cache::forget('books.all');

        if ($book->genre) {
            Cache::forget("books.all.{$book->genre}");
        }

        return response()->json(
            [
                'message' => 'Buku berhasil dihapus',
            ],
            200
        );
    }

    /**
     * POST /api/books/export
     * Export buku ke CSV (via queue)
     */
    public function export(Request $request): JsonResponse
    {
        $filename = 'books-' . now()->format('Y-m-d-His') . '.csv';

        ExportBooksToCSV::dispatch($filename, $request->genre)
            ->onQueue('exports');

        return response()->json([
            'message' => 'Export sedang diproses di background',
            'filename' => $filename,
        ], 202);
    }
}
