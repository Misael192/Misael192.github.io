<?php

/** Integrações & API — gestão de chaves da API pública. */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\ApiKeyController)->index();
