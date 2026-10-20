<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppConfigTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function タイムゾーンは東京でロケールは日本語である(): void
    {
        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('ja', config('app.locale'));
    }
}
