<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();
require_role_min(2); // operador o admin

$filename = 'reporte_dispositivos_'.date('Ymd_His').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename='.$filename);

$out = fopen('php://output', 'w');

// BOM + separador regional para Excel
fwrite($out, "\xEF\xBB\xBF");
fwrite($out, "sep=;\r\n");

$delimiter = ';';
$enclosure = '"';

// limpia saltos y previene fórmulas
$clean = function($v) {
    $v = (string)($v ?? '');
    $v = preg_replace("/\r\n|\r|\n/", ' ', $v);
    if ($v !== '' && preg_match('/^[=\+\-@]/', $v)) $v = "'".$v;
    return trim($v);
};
// fuerza TEXTO (Excel no lo formatea ni lo convierte)
$asText = function($v) use ($clean) {
    $v = $clean($v);
    return $v === '' ? '' : "'".$v; // apostrofo invisible en Excel
};

fputcsv($out, ['IP', 'MAC', 'Nombre del PC', 'Usuario', 'Fabricante', 'Última actualización'], $delimiter, $enclosure);

$sql = "
  SELECT ip, mac, nombre_pc, usuario_manual, fabricante_manual, ultima_actualizacion
  FROM dispositivo
  WHERE eliminado = 0
  ORDER BY ip ASC
";
if ($res = $conn->query($sql)) {
    while ($r = $res->fetch_assoc()) {
        $ip         = $asText($r['ip'] ?? '');
        $mac        = $asText($r['mac'] ?? '');
        $nombre_pc  = $clean($r['nombre_pc'] ?? '');
        $usuario    = $clean($r['usuario_manual'] ?? '');
        $fabricante = $clean($r['fabricante_manual'] ?? '');

        // FECHA como TEXTO para que no salga ####### ni número de serie
        $fecha = '';
        if (!empty($r['ultima_actualizacion'])) {
            $fechaISO = date('Y-m-d H:i:s', strtotime($r['ultima_actualizacion']));
            $fecha = $asText($fechaISO);
        }

        fputcsv($out, [$ip, $mac, $nombre_pc, $usuario, $fabricante, $fecha], $delimiter, $enclosure);
    }
}
fclose($out);

// Auditoría
$u = current_user();
$uid = $u['id'] ?? null;
$stmtA = $conn->prepare("INSERT INTO auditoria (usuario_id, accion, detalle) VALUES (?, 'reporte_csv', 'Exportación CSV de dispositivos')");
$stmtA->bind_param("i", $uid);
$stmtA->execute();
$stmtA->close();
