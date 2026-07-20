<?php

/** Download de documento com controle de acesso (?id=). */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\DocumentController)->download();
