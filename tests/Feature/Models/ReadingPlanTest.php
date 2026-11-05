<?php

namespace Tests\Feature\Models;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 計画者と対象書籍を取得できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->for($user)->for($book)->create();

        $this->assertTrue($plan->user->is($user));
        $this->assertTrue($plan->book->is($book));
    }

    /** @test */
    public function ユーザーと書籍から読書計画を取得できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        ReadingPlan::factory()->count(2)->for($user)->for($book)->create();
        ReadingPlan::factory()->create();

        $this->assertCount(2, $user->readingPlans);
        $this->assertCount(2, $book->readingPlans);
    }

    /** @test */
    public function 状態は列挙型として扱われ文字列で保存される(): void
    {
        $plan = ReadingPlan::factory()->create(['status' => ReadingPlanStatus::Expired]);

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id, 'status' => 'expired']);
    }

    /** @test */
    public function 状態を指定しなければデータベースの既定値で進行中になる(): void
    {
        $plan = new ReadingPlan([
            'user_id' => User::factory()->create()->id,
            'book_id' => Book::factory()->create()->id,
            'target_date' => '2026-12-01',
        ]);
        $plan->save();

        $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);
        $this->assertNull($plan->fresh()->completed_at);
    }

    /** @test */
    public function 不正な状態の値はデータベースから読み込めない(): void
    {
        $plan = ReadingPlan::factory()->create();
        ReadingPlan::whereKey($plan->id)->update(['status' => 'unknown']);

        $this->expectException(\ValueError::class);

        $plan->fresh()->status;
    }

    /** @test */
    public function 期日は日付で扱われ保存と出力は年月日の形式になる(): void
    {
        $plan = ReadingPlan::factory()->create(['target_date' => '2026-12-24']);

        $plan->refresh();
        $this->assertInstanceOf(Carbon::class, $plan->target_date);
        $this->assertSame('2026-12-24', $plan->target_date->format('Y-m-d'));
        $this->assertSame('2026-12-24', $plan->toArray()['target_date']);
    }

    /** @test */
    public function 完了日時は日時として扱われ未完了ならnullである(): void
    {
        $plan = ReadingPlan::factory()->completed()->create();

        $this->assertInstanceOf(Carbon::class, $plan->fresh()->completed_at);
        $this->assertNull(ReadingPlan::factory()->create()->completed_at);
    }

    /** @test */
    public function ユーザーを削除するとその読書計画も削除される(): void
    {
        $plan = ReadingPlan::factory()->create();

        $plan->user->delete();

        $this->assertModelMissing($plan);
    }

    /** @test */
    public function 書籍を削除するとその読書計画も削除される(): void
    {
        $plan = ReadingPlan::factory()->create();

        $plan->book->delete();

        $this->assertModelMissing($plan);
    }

    /** @test */
    public function 存在しない書籍の読書計画は外部キー制約で登録できない(): void
    {
        $this->expectException(QueryException::class);

        ReadingPlan::factory()->create(['book_id' => 99999]);
    }

    /** @test */
    public function 存在しないユーザーの読書計画は外部キー制約で登録できない(): void
    {
        $this->expectException(QueryException::class);

        ReadingPlan::factory()->create(['user_id' => 99999]);
    }
}
