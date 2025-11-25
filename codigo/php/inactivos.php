<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();

/* ===============================
   Parámetros
   =============================== */
$minutosActivo = 30;
$porPagina = 50;
$pagina = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($pagina - 1) * $porPagina;

/* ===============================
   Total de inactivos
   =============================== */
$sqlTotal = "
    SELECT COUNT(*) AS c
    FROM dispositivo
    WHERE ultima_actualizacion IS NULL
       OR ultima_actualizacion < (NOW() - INTERVAL {$minutosActivo} MINUTE)
";

$resTotal = $conn->query($sqlTotal);
$rowTotal = $resTotal->fetch_assoc();
$totalInactivos = (int)$rowTotal['c'];

$totalPaginas = max(1, ceil($totalInactivos / $porPagina));

/* ===============================
   Consulta de inactivos
   =============================== */
$sql = "
    SELECT 
        d.id,
        d.ip,
        d.mac,
        d.nombre_pc,
        d.usuario_manual,
        d.fabricante_manual,
        u.nombre AS usuario_bd,
        v.fabricante AS fabricante_oui,
        d.ultima_actualizacion
    FROM dispositivo d
    LEFT JOIN usuario u ON d.usuario_id = u.id
    LEFT JOIN vendor_oui v ON v.prefix = d.mac_prefix
    WHERE d.ultima_actualizacion IS NULL
       OR d.ultima_actualizacion < (NOW() - INTERVAL {$minutosActivo} MINUTE)
    ORDER BY d.ip ASC
    LIMIT {$porPagina} OFFSET {$offset}
";

$res = $conn->query($sql);
$items = [];

while ($row = $res->fetch_assoc()) {

    $usuarioMostrar    = $row['usuario_manual'] ?: ($row['usuario_bd'] ?: '--');
    $fabricanteMostrar = $row['fabricante_manual'] ?: ($row['fabricante_oui'] ?: 'Desconocido');

    $items[] = [
        'id'         => (int)$row['id'],
        'ip'         => $row['ip'],
        'mac'        => $row['mac'],
        'nombre_pc'  => $row['nombre_pc'] ?: '(sin nombre)',
        'usuario'    => $usuarioMostrar,
        'fabricante' => $fabricanteMostrar,
        'ultima'     => $row['ultima_actualizacion'],
    ];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dispositivos Inactivos</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4">
</head>
<body>

  <!-- NAVBAR -->
  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Dispositivos Inactivos</div>
    <div class="nav-actions">

      <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">🏠 Dashboard</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/inventario.php">📦 Inventario</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/activos.php">Ver activos</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/logout.php">Salir</a>
    </div>
  </div>

  <!-- CONTENIDO -->
  <div class="container">

    <h2 class="section-title">🔴 Dispositivos inactivos (más de <?= (int)$minutosActivo ?> minutos)</h2>

    <!-- TABLA -->
    <table class="table">
      <thead>
        <tr>
          <th>IP</th>
          <th>MAC</th>
          <th>Equipo</th>
          <th>Usuario</th>
          <th>Fabricante</th>
          <th>Última actualización</th>
          <th>Acciones</th>
        </tr>
      </thead>

      <tbody>
      <?php if (empty($items)): ?>
        <tr>
          <td colspan="7" class="muted">No hay dispositivos inactivos.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($items as $d): ?>
        <tr>
          <td><?= e($d['ip']) ?></td>

          <td>
            <?php if ($d['mac']): ?>
              <?= e($d['mac']) ?>
            <?php else: ?>
              <span class="muted">⚠ no detectada</span>
            <?php endif; ?>
          </td>

          <td><?= e($d['nombre_pc']) ?></td>
          <td><?= e($d['usuario']) ?></td>
          <td><?= e($d['fabricante']) ?></td>
          <td><?= e($d['ultima']) ?></td>

          <td>
            <a class="btn small" href="dispositivo_editar.php?id=<?= $d['id'] ?>">✏️ Editar</a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <!-- PAGINACIÓN -->
    <?php if ($totalPaginas > 1): ?>
      <div style="margin-top:18px; display:flex; justify-content:center; gap:6px; flex-wrap:wrap;">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
          <a
            class="btn small <?= $i === $pagina ? 'primary' : '' ?>"
            href="?p=<?= $i ?>"
          >
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>

</body>
</html>