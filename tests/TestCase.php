<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A developer's .env may enable the LLM driver; tests must never call it by accident.
        config(['summary.driver' => 'rules']);
    }
}
