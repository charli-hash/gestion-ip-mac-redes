<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();

// Leer ruta del log desde la tabla config
$res = $conn->query("SELECT valor FROM config WHERE clave='pihole_log'");
$row = $res ? $res->fetch_assoc() : null;
$logfile = $row['valor'] ?? null;

// Si no hay log configurado o el archivo no existe, mostramos mensaje amable y salimos
if (!$logfile || !file_exists($logfile)) {
    echo '<p class="muted">No se encontró el log de Pi-hole. Revisa la clave <code>pihole_log</code> en la tabla <code>config</code>.</p>';
    return;
}

// Leemos las últimas 40 líneas del log real
$lineas = @file($logfile);
if (!$lineas) {
    echo '<p class="muted">No se pudo leer el archivo de log de Pi-hole.</p>';
    return;
}
$lineas = array_slice($lineas, -40);

$resultado = [];

// Ejemplos que matchea:
//  query[A] google.com from 172.18.0.1
//  query[AAAA] clients4.google.com from 172.18.0.1
//  query[HTTPS] storage-cdn.wemod.com from 172.18.0.1
foreach ($lineas as $l) {
    if (preg_match('/query\[(A|AAAA|HTTPS)\]\s+([^\s]+)\s+from\s+([0-9a-fA-F\:\.]+)/', $l, $m)) {
        $resultado[] = [
            'tipo'    => strtoupper($m[1]),
            'dominio' => strtolower($m[2]),
            'ip'      => $m[3],
        ];
    }
}

// Dejamos solo los últimos 20 eventos detectados
$resultado = array_slice(array_reverse($resultado), 0, 20);

// Si no hay resultados, mostramos mensaje
if (empty($resultado)) {
    echo '<p class="muted">Sin consultas recientes en el log de Pi-hole.</p>';
    return;
}
?>

<table class="table">
  <thead>
    <tr>
      <th>IP</th>
      <th>Dominio</th>
      <th>Tipo</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($resultado as $r): ?>
      <?php
        // Definimos la clase para el tipo (A / AAAA / HTTPS)
        $tipo = $r['tipo'];
        $class = 'tag-dns tag-other';
        if ($tipo === 'A')        $class = 'tag-dns tag-a';
        elseif ($tipo === 'AAAA') $class = 'tag-dns tag-aaaa';
        elseif ($tipo === 'HTTPS')$class = 'tag-dns tag-https';
      ?>
      <tr>
        <td><?= e($r['ip']) ?></td>
        <td><?= e($r['dominio']) ?></td>
        <td><span class="<?= $class ?>"><?= e($tipo) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>