<?php
// conexion.php — conexión a MySQL (XAMPP)

$host = 'localhost';         // o 127.0.0.1
$user = 'root';              // usuario por defecto de XAMPP
$pass = '';                  // contraseña (vacía si no la has cambiado)
$db   = 'gestion_ip_mac_v2'; // 👈 nombre de tu base de datos

$conn = new mysqli($host, $user, $pass, $db);

// Verificar errores de conexión
if ($conn->connect_error) {
    die('Error de conexión a MySQL: ' . $conn->connect_error);
}

// Forzar juego de caracteres
$conn->set_charset('utf8mb4');
?>
