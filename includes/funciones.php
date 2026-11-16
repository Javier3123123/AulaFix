<?php

function h($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function obtenerTokenCsrf(): string
{
    if (!isset($_SESSION['csrf_incidencias']) || !is_string($_SESSION['csrf_incidencias'])) {
        $_SESSION['csrf_incidencias'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_incidencias'];
}

function validarTokenCsrf($token): bool
{
    return isset($_SESSION['csrf_incidencias'])
        && is_string($_SESSION['csrf_incidencias'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_incidencias'], $token);
}
