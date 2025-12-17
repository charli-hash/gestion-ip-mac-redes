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
   Red activa desde config
   =============================== */
$cfg = [];
if ($resCfg = $conn->query("SELECT clave, valor FROM config")) {
    while ($r = $resCfg->fetch_assoc()) $cfg[$r['clave']] = $r['valor'];
}
$activeRedId = isset($cfg['active_red_id']) ? (int)$cfg['active_red_id'] : 1;

/* ===============================
   Conteo total de activos (filtrado por red + no eliminados)
   =============================== */
$totalActivos = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM dispositivo
    WHERE (eliminado IS NULL OR eliminado=0)
      AND red_id = ?
      AND ultima_actualizacion >= (NOW() - INTERVAL ? MINUTE)
");
$stmt->bind_param("ii", $activeRedId, $minutosActivo);
$stmt->execute();
$stmt->bind_result($totalActivos);
$stmt->fetch();
$stmt->close();

$totalActivos = (int)$totalActivos;
$totalPaginas = max(1, (int)ceil($totalActivos / $porPagina));

/* ===============================
   Consulta principal
   =============================== */
$items = [];

$stmt = $conn->prepare("
    SELECT 
        d.id,
        d.ip,
        d.mac,
        d.nombre_pc,
        d.usuario_manual,
        d.fabricante_manual,
        u.nombre        AS usuario_bd,
        v.fabricante    AS fabricante_oui,
        d.ultima_actualizacion
    FROM dispositivo d
    LEFT JOIN usuario    u ON d.usuario_id = u.id
    LEFT JOIN vendor_oui v ON v.prefix = d.mac_prefix
    WHERE (d.eliminado IS NULL OR d.eliminado=0)
      AND d.red_id = ?
      AND d.ultima_actualizacion >= (NOW() - INTERVAL ? MINUTE)
    ORDER BY d.ip ASC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iiii", $activeRedId, $minutosActivo, $porPagina, $offset);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $usuarioMostrar = $row['usuario_manual'] ?: ($row['usuario_bd'] ?: '--');
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
$stmt->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dispositivos activos</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4">
</head>
<body>

  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Dispositivos Activos</div>
    <div class="nav-actions">
      <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">🏠 Dashboard</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/inventario.php">📦 Inventario</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/inactivos.php">Ver inactivos</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/logout.php">Salir</a>
    </div>
  </div>

  <div class="container">

    <h2 class="section-title">
      🟢 Dispositivos activos (últimos <?= (int)$minutosActivo ?> min) — Red ID <?= (int)$activeRedId ?>
    </h2>

    <table class="table">
      <thead>
        <tr>
          <th>IP</th>
          <th>MAC</th>
          <th>Equipo</th>
          <th>Usuario</th>
          <th>Fabricante</th>
          <th>Actualizado</th>
          <th>Acciones</th>
        </tr>
      </thead>

      <tbody>
      <?php if (empty($items)): ?>
        <tr>
          <td colspan="7" class="muted">No hay activos en este período para esta red.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($items as $d): ?>
        <tr>
          <td><?= e($d['ip']) ?></td>

          <td>
            <?php if (!empty($d['mac'])): ?>
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
            <a class="btn small" href="dispositivo_editar.php?id=<?= (int)$d['id'] ?>">✏️ Editar</a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <?php if ($totalPaginas > 1): ?>
      <div style="margin-top:18px; display:flex; justify-content:center; gap:6px; flex-wrap:wrap;">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
          <a class="btn small <?= $i === $pagina ? 'primary' : '' ?>" href="?p=<?= $i ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>

</body>
</html>
