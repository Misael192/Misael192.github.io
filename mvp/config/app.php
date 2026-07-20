<?php

declare(strict_types=1);

return [
    'name' => 'PeopleFlow',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => (getenv('APP_DEBUG') ?: 'true') === 'true',
    'timezone' => 'America/Sao_Paulo',
];
