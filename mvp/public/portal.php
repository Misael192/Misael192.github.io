<?php

/** Portal do Colaborador — autosserviço (ponto, holerites, férias, documentos). */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\PortalController)->index();
