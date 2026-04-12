<?php

namespace App\Http\Controllers;

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
        $cacheKey = 'authors.all.' . md5($request->getQueryString() ?? '');

        $authors = Cache::remember('get-all-authors', self::CACHE_TTL, function () use ($request) {
            $query = Author::with('books');

            if ($request->has('name')) {
                $query->where('name', $request->name);
            }

            if ($request->has('email')) {
                $query->where('email', $request->email);
            }

            return $query->latest()->get()->toArray();
        });

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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Author $author)
    {
        //
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
        Cache::forget('get-all-authors'); // Invalidate cache list
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Author $author)
    {
        //
    }
}
