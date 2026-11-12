<?php

return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'gestion_incidencias',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') === false ? '' : getenv('DB_PASSWORD'),
];