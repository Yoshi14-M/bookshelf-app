<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReadingPlanController extends Controller
{
    /**
     * 自分の読書計画の一覧（期日の昇順、同日は ID の昇順。ページネーションなし）。
     *
     * `?status=` で状態を絞り込む。未知の値は絞り込まずに全件を表示する。
     */
    public function index(Request $request): View
    {
        $value = $request->query('status');
        $status = is_string($value) ? ReadingPlanStatus::tryFrom($value) : null;

        $readingPlans = $request->user()->readingPlans()
            ->with('book')
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        $currentStatus = $status?->value;

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * 作成フォーム（書籍は全件をタイトルの昇順で選べる）。
     */
    public function create(): View
    {
        $this->authorize('create', ReadingPlan::class);

        $books = Book::orderBy('title')->orderBy('id')->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画を登録する（状態は常に進行中）。
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $request->user()->readingPlans()->create([
            ...$request->validated(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()->route('reading-plans.index')->with('success', '読書計画を登録しました。');
    }

    /**
     * 期日の編集フォーム（所有者のみ。完了した計画は不可）。
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->load('book');

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 期日を変更する（所有者のみ。完了した計画は不可）。期限切れの計画は進行中に戻る。
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->update([
            ...$request->validated(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()->route('reading-plans.index')->with('success', '読書計画を更新しました。');
    }

    /**
     * 読書計画を削除する（所有者のみ。状態は問わない）。
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()->route('reading-plans.index')->with('success', '読書計画を削除しました。');
    }

    /**
     * 読了にする（所有者のみ）。進行中・期限切れの計画が完了になり、完了日時が入る。
     * 既に完了している計画は何もせず一覧へ戻る。
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('complete', $readingPlan);

        if ($readingPlan->status === ReadingPlanStatus::Completed) {
            return redirect()->route('reading-plans.index');
        }

        $readingPlan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()->route('reading-plans.index')->with('success', '読書計画を完了にしました。');
    }
}
