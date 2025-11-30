<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();
require_role_min(2); // operador o admin

// Cabeceras para descargar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_dispositivos.csv');

$out = fopen('php://output', 'w');

// Encabezado
fputcsv($out, ['IP', 'MAC', 'Nombre PC', 'Usuario', 'Fabricante', 'Última actualización']);

// Datos
$sql = "
  SELECT ip, mac, nombre_pc, usuario_manual, fabricante_manual, ultima_actualizacion
  FROM dispositivo
  WHERE eliminado = 0
  ORDER BY ip ASC
";
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [
            $r['ip'],
            $r['mac'],
            $r['nombre_pc'],
            $r['usuario_manual'],
            $r['fabricante_manual'],
            $r['ultima_actualizacion']
        ]);
    }
}
fclose($out);

// Auditoría
$u = current_user();
$stmtA = $conn->prepare("INSERT INTO auditoria (usuario_id, accion, detalle) VALUES (?, 'reporte_csv', 'Exportación CSV de dispositivos')");
$uid = $u['id'] ?? null;
$stmtA->bind_param("i", $uid);
$stmtA->execute();
$stmtA->close();