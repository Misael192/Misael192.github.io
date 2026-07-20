<?php

require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\AuthController)->logout();
