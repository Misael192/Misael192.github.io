<?php

/** GED — documentos com versionamento e assinatura. */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\DocumentController)->index();
