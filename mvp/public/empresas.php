<?php

/** Cadastro de empresas: listagem (GET) e criação (POST). */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\CompanyController)->index();
