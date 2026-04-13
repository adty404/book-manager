<?php

namespace App\Repositories;

use App\Models\Book;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Support\Collection;

class BookRepository implements BookRepositoryInterface
{
    public function getAll(?string $genre = null): Collection
    {
        return Book::with('author')
            ->when($genre, fn($q) => $q->where('genre', $genre))
            ->get();
    }

    public function findById(int $id): Book
    {
        return Book::findOrFail($id);
    }

    public function create(array $data): Book
    {
        return Book::create($data);
    }

    public function update(int $id, array $data): Book
    {
        $book = $this->findById($id);

        $book->update($data);
        return $book;
    }

    public function delete(int $id): bool
    {
        $book = $this->findById($id);

        return $book->delete();
    }
}
