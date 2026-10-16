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
        // La raíz lleva al dashboard, que sin sesión redirige al login
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/login')->assertStatus(200);
    }
}
