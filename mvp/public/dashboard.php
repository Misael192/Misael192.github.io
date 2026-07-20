<?php

/** Dashboard inicial — KPIs reais vindos do banco. */
require __DIR__.'/../app/bootstrap.php';

(new App\Controllers\DashboardController)->index();
