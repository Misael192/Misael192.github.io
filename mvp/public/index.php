<?php

/**
 * Landing do MVP: usuário logado vai direto ao dashboard;
 * visitante vê a página institucional (landing.html estática).
 */
require __DIR__.'/../app/bootstrap.php';

if (auth_user() !== null) {
    redirect('dashboard.php');
}

require __DIR__.'/landing.html';
