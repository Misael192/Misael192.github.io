<?php

declare(strict_types=1);

return [
    // Argon2id — mesmo padrão de segurança da plataforma completa
    'hash_algo' => PASSWORD_ARGON2ID,
    'hash_options' => ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1],

    // Sessão
    'session_name' => 'peopleflow_session',
    'session_lifetime' => 7200, // 2h
];
