<?php

namespace App\Http\Controllers;

use App\Jobs\CacheWarmupJob;
use App\Jobs\SendWelcomeEmail;
use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuthorController extends Controller
{
    // TTL terpusat - mudah diubah
    private const CACHE_TTL = 1800; // 30 menit

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $cacheKey = 'authors.all';

        if ($request->hasAny(['name', 'email'])) {
            $authors = Author::with('books')
                ->when($request->name, fn ($q) => $q->where('name', 'like', '%' . $request->name . '%'))
                ->when($request->email, fn ($q) => $q->where('email', 'like', '%' . $request->email . '%'))
                ->get();
        } else {
            $authors = Cache::remember($cacheKey, 3600, fn () => Author::with('books')->get());
        }

        return response()->json([
            'data' => $authors,
            'total' => count($authors),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:authors,email',
        ]);

        $author = Author::create($validated);

        SendWelcomeEmail::dispatch($author);

        return response()->json([
            'message' => 'Author created successfully.',
            'data' => $author,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Author $author)
    {
        $cacheKey = "authors.{$author->id}";

        $author = Cache::remember($cacheKey, 3600, function () use ($author) {
            return Author::with('books')->find($author->id);
        });

        return response()->json([
            'data' => $author,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Author $author)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Author $author)
    {
        $author = Author::find($author->id);
        if (!$author) {
            return response()->json(['message' => 'Author tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:authors,email,' . $author->id,
        ]);

        $author->update($validated);

        // Invalidate cache terkait author ini
        Cache::forget("authors.{$author->id}");
        Cache::forget('authors.all'); // Invalidate cache list

        return response()->json([
            'message' => 'Author updated successfully.',
            'data' => $author,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Author $author)
    {
        $author = Author::findOrFail($author->id);

        Cache::forget('authors.all'); // Invalidate cache list
        Cache::forget("authors.{$author->id}");

        $author->delete();

        CacheWarmupJob::dispatch();

        return response()->json([
            'message' => 'Author deleted successfully.',
        ]);
    }
}
