<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test không cần file CSS/JS đã build (npm run build); CI trình duyệt mới build thật.
        $this->withoutVite();
    }
}
