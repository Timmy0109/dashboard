<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Make Sanctum treat test requests as stateful (SPA session), so $request->session() works.
        $this->withHeaders([
            'Origin'  => 'http://localhost',
            'Referer' => 'http://localhost/',
        ])->withSession([]);
    }
}
