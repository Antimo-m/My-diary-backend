<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_redirects_to_the_frontend(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(config('app.frontend_url'));
    }
}
