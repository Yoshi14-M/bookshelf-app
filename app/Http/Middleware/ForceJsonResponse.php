<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API は Accept ヘッダの有無にかかわらず常に JSON で応答する（認証・404・422 のリダイレクトを避ける）。
 */
class ForceJsonResponse
{
    /**
     * `Accept: application/json` を付けて、API のエラー（認証・検証・404）を常に JSON で返させる。
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
