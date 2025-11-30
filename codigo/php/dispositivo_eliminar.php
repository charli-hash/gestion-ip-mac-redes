<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();
require_role_min(2); // operador o admin

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die('ID inválido.');

$stmt = $conn->prepare("UPDATE dispositivo SET eliminado=1 WHERE id=?");
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$stmt->close();

// Registrar en auditoría
$u = current_user();
$stmtA = $conn->prepare("INSERT INTO auditoria (usuario_id, accion, detalle) VALUES (?, 'eliminar_dispositivo', CONCAT('id=', ?))");
$uid = $u['id'] ?? null;
$stmtA->bind_param("ii", $uid, $id);
$stmtA->execute();
$stmtA->close();

// Redirigir con mensaje
header("Location: /gestion_ipmac/codigo/php/inventario.php?msg=".($ok ? 'eliminado_ok' : 'eliminado_error'));
exit;