<?php

/**
 * Escapa valores para texto o atributos HTML, no para contextos JavaScript o CSS.
 * @param mixed $valor Valor compatible con conversión a cadena.
 */
function h($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Recupera el token CSRF de la sesión o crea uno aleatorio si aún no existe.
 * @return string Token hexadecimal derivado de 32 bytes aleatorios.
 */
function obtenerTokenCsrf(): string
{
    if (!isset($_SESSION['csrf_incidencias']) || !is_string($_SESSION['csrf_incidencias'])) {
        $_SESSION['csrf_incidencias'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_incidencias'];
}

/**
 * Comprueba el token recibido con una comparación resistente a temporización.
 * @param mixed $token Valor recibido en la solicitud.
 */
function validarTokenCsrf($token): bool
{
    return isset($_SESSION['csrf_incidencias'])
        && is_string($_SESSION['csrf_incidencias'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_incidencias'], $token);
}
