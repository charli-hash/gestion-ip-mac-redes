<?php
// auditoria_helper.php — funciones para registrar eventos en la tabla auditoria

function audit(mysqli $conn, array $data): void {
    // Datos básicos
    $usuario_id = $_SESSION['user_id'] ?? null;
    $ip_origen  = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua         = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $ts         = date('Y-m-d H:i:s');

    // Mezclar valores recibidos con los básicos
    $row = array_merge([
        'ts'             => $ts,
        'usuario_id'     => $usuario_id,
        'severidad'      => 'info',
        'modulo'         => null,
        'accion'         => '',
        'detalle'        => null,
        'detalle_json'   => null,
        'ip_origen'      => $ip_origen,
        'user_agent'     => $ua,
        'red_id'         => null,
        'dispositivo_id' => null,
        'entidad'        => null,
        'entidad_id'     => null,
    ], $data);

    // Insertar registro
    $stmt = $conn->prepare("
        INSERT INTO auditoria
        (ts, usuario_id, severidad, modulo, accion, detalle, detalle_json, ip_origen, user_agent, red_id, dispositivo_id, entidad, entidad_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'sisssssssiisi',
        $row['ts'],
        $row['usuario_id'],
        $row['severidad'],
        $row['modulo'],
        $row['accion'],
        $row['detalle'],
        $row['detalle_json'],
        $row['ip_origen'],
        $row['user_agent'],
        $row['red_id'],
        $row['dispositivo_id'],
        $row['entidad'],
        $row['entidad_id']
    );
    $stmt->execute();
}
