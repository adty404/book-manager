<?php

namespace App\Repositories\Interfaces;

use App\Models\Book;
use Illuminate\Support\Collection;

interface BookRepositoryInterface
{
    public function getAll(?string $genre = null): Collection;
    public function findById(int $id): Book;
    public function create(array $data): Book;
    public function update(int $id, array $data): Book;
    public function delete(int $id): bool;
}
