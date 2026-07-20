<?php

/** Controle de usuários: listagem (GET) e criação (POST). */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\UserController)->index();
