<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** A raiz redireciona para a landing page estática (public/index.html). */
    public function test_the_application_redirects_to_the_landing_page(): void
    {
        $this->get('/')->assertRedirect('/index.html');
    }
}
