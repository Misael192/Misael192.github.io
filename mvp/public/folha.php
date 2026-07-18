<?php

/** Fechamento de folha de pagamento por competência. */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\PayrollController)->index();
