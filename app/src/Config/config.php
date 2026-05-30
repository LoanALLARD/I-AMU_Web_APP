<?php

return [
    'db' => [
        'host'     => $_ENV["DB_HOST"] ?? 'postgres_db', // Mets le nom de ton conteneur BDD par défaut
        'port'     => $_ENV["DB_PORT"] ?? '5432',
        'dbname'   => $_ENV["DB_NAME"] ?? 'ma_bdd',
        'user'     => $_ENV["DB_USER"] ?? 'root',
        'password' => $_ENV["DB_PASSWORD"] ?? '',
    ]
];