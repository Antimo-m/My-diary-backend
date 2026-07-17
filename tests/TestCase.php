<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The API is localized via the Accept-Language header (default: it).
        // Symfony's test client would otherwise send "en-us", so we pin the
        // Italian locale to keep the existing feature tests deterministic.
        $this->withHeader('Accept-Language', 'it');
    }
}
