<?php
function cargarVariablesEntorno(string $rutaProyecto): void
{
    $rutaEnv = rtrim($rutaProyecto, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ".env";

    if (!is_file($rutaEnv)) {
        return;
    }

    $lineas = file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lineas === false) {
        return;
    }

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }

        $pos = strpos($linea, '=');
        if ($pos === false) {
            continue;
        }

        $clave = trim(substr($linea, 0, $pos));
        $valor = trim(substr($linea, $pos + 1));

        if ($clave === '') {
            continue;
        }

        if (!array_key_exists($clave, $_ENV)) {
            $_ENV[$clave] = $valor;
        }

        if (getenv($clave) === false) {
            putenv("{$clave}={$valor}");
        }
    }
}

cargarVariablesEntorno(__DIR__ . "/..");

$host = getenv("DB_HOST") ?: "localhost";
$puerto = getenv("DB_PORT") ?: "3306";
$baseDatos = getenv("DB_NAME") ?: "db_tutorias";
$usuario = getenv("DB_USER");
$contrasena = getenv("DB_PASSWORD");

if ($usuario === false || trim($usuario) === "" || $contrasena === false || $contrasena === "") {
    http_response_code(500);
    error_log("Configuración incompleta: DB_USER y DB_PASSWORD son obligatorias.");
    exit("El servicio no está disponible. Configura DB_USER y DB_PASSWORD en un archivo .env o en el entorno del servidor.");
}

try {
    $conexion = new PDO(
        "mysql:host={$host};port={$puerto};dbname={$baseDatos};charset=utf8mb4",
        $usuario,
        $contrasena,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit(
        "El servicio no está disponible. Verifica la conexión a MySQL y configura las variables DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD en un archivo .env o en el entorno del servidor."
    );
}
