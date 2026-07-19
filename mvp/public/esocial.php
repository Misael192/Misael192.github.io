<?php

/** eSocial — geração de eventos S-2200/S-1200. */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\EsocialController)->index();
