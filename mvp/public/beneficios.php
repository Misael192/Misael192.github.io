<?php

/** Benefícios por colaborador (VT, VA/VR, saúde…). */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\BenefitController)->index();
