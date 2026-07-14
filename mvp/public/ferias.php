<?php

/** Gestão de férias. */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\VacationController)->index();
