<?php

/** Tela de login (GET) e autenticação (POST). */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\AuthController)->login();
