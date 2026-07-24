<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

destruir_sessao();

header('Location: /login.php');
exit;
