<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function トップページにアクセスできる(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
