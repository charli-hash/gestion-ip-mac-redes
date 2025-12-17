<?php
// generar_hash.php
$passwords = [
    'admin123',
    'operador123',
    'lector123'
];

foreach ($passwords as $p) {
    echo $p . " => " . password_hash($p, PASSWORD_DEFAULT) . "<br>";
}