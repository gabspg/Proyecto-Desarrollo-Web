<?php

ini_set("display_errors", "0");
ini_set("log_errors", "1");

/* ============================================================
   1. CONFIGURACIÓN DE RUTAS
   ============================================================ */

$host = strtolower(explode(":", $_SERVER["HTTP_HOST"] ?? "")[0]);

define(
    "APP_BASE_URL",
    $host === "tutorlink" ? "" : "/tutorias"
);

/* ============================================================
   2. CONFIGURACIÓN DE SESIÓN
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) {

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.use_trans_sid", "0");

    $https = isset($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "" &&
        strtolower((string)$_SERVER["HTTPS"]) !== "off";
    $https = $https || (int)($_SERVER["SERVER_PORT"] ?? 0) === 443;

    session_set_cookie_params([
        "httponly" => true,
        "secure" => $https,
        "samesite" => "Lax",
        "path" => "/"
    ]);

    session_start();

    $ahora = time();
    if (
        isset($_SESSION["ultima_actividad"]) &&
        $ahora - (int)$_SESSION["ultima_actividad"] > 1800
    ) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION["ultima_actividad"] = $ahora;
}

/* ============================================================
   3. TOKEN CSRF
   ============================================================ */

if (empty($_SESSION["csrf_logout"])) {
    $_SESSION["csrf_logout"] = bin2hex(random_bytes(32));
}

$cspNonce = base64_encode(random_bytes(18));

/* ============================================================
   4. ENCABEZADOS DE SEGURIDAD
   ============================================================ */

header_remove("X-Powered-By");

header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "base-uri 'self'; "
    . "object-src 'none'; "
    . "frame-ancestors 'none'; "
    . "form-action 'self'; "
    . "img-src 'self' data:; "
    . "style-src 'self' https://cdn.jsdelivr.net 'nonce-" . $cspNonce . "'; "
    . "style-src-attr 'none'; "
    . "script-src 'self'; "
    . "script-src-attr 'none'; "
    . "font-src 'self' data: https://cdn.jsdelivr.net; "
    . "connect-src 'self'"
);
