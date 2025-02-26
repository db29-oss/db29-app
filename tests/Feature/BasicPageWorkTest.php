<?php

namespace Tests\Feature;

use Tests\TestCase;

class BasicPageWorkTest extends TestCase
{
    public function test_generic(): void
    {
        // homepage
        $response = $this->get('/');

        $response->assertStatus(200);

        // faq
        $response = $this->get('faq');

        $response->assertStatus(200);

        // login
        $response = $this->get('faq');

        $response->assertStatus(200);
    }
}
