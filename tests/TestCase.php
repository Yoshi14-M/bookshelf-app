<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // テストが `npm run build` の有無に依存しないよう、Vite のマニフェスト解決を無効化する
        $this->withoutVite();
    }
}
