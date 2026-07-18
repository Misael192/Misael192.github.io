<?php

/** Holerite individual (versão para impressão/PDF). */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\PayrollController)->payslip();
