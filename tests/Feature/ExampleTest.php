<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_reports_the_api_status(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('frontend', config('app.frontend_url'));
    }
}
