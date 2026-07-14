<?php

/** Controle de ponto e banco de horas. */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\TimeController)->index();
