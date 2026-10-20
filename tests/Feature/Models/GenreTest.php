<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ジャンルに紐付く書籍数を集計できる(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->count(2)->create());

        $this->assertSame(2, Genre::withCount('books')->find($genre->id)->books_count);
    }

    /** @test */
    public function 書籍が紐付いているジャンルはデータベースの制約で削除できない(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->create());

        $this->expectException(QueryException::class);

        $genre->delete();
    }

    /** @test */
    public function 書籍が紐付いていないジャンルは削除できる(): void
    {
        $genre = Genre::factory()->create();

        $genre->delete();

        $this->assertModelMissing($genre);
    }

    /** @test */
    public function 同名のジャンルは登録できない(): void
    {
        Genre::factory()->create(['name' => '小説']);

        $this->expectException(QueryException::class);

        Genre::factory()->create(['name' => '小説']);
    }
}
