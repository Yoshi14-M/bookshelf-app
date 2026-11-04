<?php

namespace App\Http\Controllers;

use App\Services\ReadingReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * マイ読書レポート（要ログイン）。ログインユーザー自身のレビューの集計を表示する。
     */
    public function index(Request $request, ReadingReportService $reports): View
    {
        $stats = $reports->build($request->user());

        return view('reports.index', compact('stats'));
    }
}
