<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Demostración del balanceo con HAProxy: la página es pública y el ping dice qué nodo
 * respondió, sin abrir sesión (si no, cada petición de la demo crearía una fila en sessions).
 */
class BalanceoTest extends TestCase
{
    public function test_la_pagina_del_balanceo_es_publica(): void
    {
        $this->get('/balanceo')
            ->assertStatus(200)
            ->assertSee('Balanceo de carga')
            ->assertSee('/api/balanceo/ping', false);
    }

    public function test_el_ping_dice_que_nodo_respondio(): void
    {
        $this->getJson('/api/balanceo/ping')
            ->assertStatus(200)
            ->assertJsonPath('nodo', gethostname())
            ->assertJsonPath('espera_ms', 0)
            ->assertJsonStructure(['nodo', 'pid', 'espera_ms', 'hora']);
    }

    public function test_la_espera_negativa_o_invalida_queda_en_cero(): void
    {
        $this->getJson('/api/balanceo/ping?espera=-500')->assertJsonPath('espera_ms', 0);
        $this->getJson('/api/balanceo/ping?espera=abc')->assertJsonPath('espera_ms', 0);
    }

    public function test_el_ping_no_abre_sesion(): void
    {
        $this->getJson('/api/balanceo/ping')->assertCookieMissing(config('session.cookie'));
    }
}
