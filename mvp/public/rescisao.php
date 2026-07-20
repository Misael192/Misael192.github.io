<?php

/** Rescisão: simulação e efetivação das verbas rescisórias. */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\TerminationController)->index();
