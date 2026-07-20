<?php

/** Download do XML de um evento eSocial (?id=). */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\EsocialController)->download();
