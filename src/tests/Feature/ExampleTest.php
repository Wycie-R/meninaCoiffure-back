<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Sin sesión, la raíz lleva a la página pública del salón
        $this->get('/')->assertRedirect('/salon');
        $this->get('/login')->assertStatus(200);
    }
}
