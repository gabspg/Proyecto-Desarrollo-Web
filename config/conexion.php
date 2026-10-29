
<?php
$host = "localhost";
$puerto = "3306";
$baseDatos = "db_tutorias";
$usuario = "root";
$contrasena = "1234";

try {
    $conexion = new PDO(
        "mysql:host=$host;port=$puerto;dbname=$baseDatos;charset=utf8mb4",
        $usuario,
        $contrasena
    );

    $conexion->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Error al conectar con la base de datos.");
}
?>
