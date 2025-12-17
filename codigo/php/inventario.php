<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();

/* ===============================
   Parámetros
   =============================== */
$porPagina     = 50;
$minutosActivo = 30;

$page   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $porPagina;
$q      = trim($_GET['q'] ?? '');

/* ===============================
   Red activa desde config
   =============================== */
$cfg = [];
if ($resCfg = $conn->query("SELECT clave, valor FROM config")) {
    while ($r = $resCfg->fetch_assoc()) $cfg[$r['clave']] = $r['valor'];
}
$activeRedId = isset($cfg['active_red_id']) ? (int)$cfg['active_red_id'] : 1;

/* ===============================
   WHERE (incluye soft delete + red activa)
   =============================== */
$where = "(d.eliminado IS NULL OR d.eliminado=0) AND d.red_id = ?";
$paramsTypes = "i";
$params = [$activeRedId];

if ($q !== '') {
    $where .= " AND (
        d.ip LIKE ?
        OR d.mac LIKE ?
        OR d.nombre_pc LIKE ?
        OR d.usuario_manual LIKE ?
        OR d.fabricante_manual LIKE ?
    )";
    $paramsTypes .= "sssss";
    $like = "%{$q}%";
    array_push($params, $like, $like, $like, $like, $like);
}

/* ===============================
   Total de filas
   =============================== */
$totalFilas = 0;
$sqlCount = "SELECT COUNT(*) AS c FROM dispositivo d WHERE $where";
$stmt = $conn->prepare($sqlCount);
$stmt->bind_param($paramsTypes, ...$params);
$stmt->execute();
$totalFilas = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPaginas = max(1, (int)ceil($totalFilas / $porPagina));
if ($page > $totalPaginas) $page = $totalPaginas;

/* ===============================
   Consulta principal
   =============================== */
$sql = "
    SELECT 
        d.id,
        d.ip,
        d.mac,
        d.nombre_pc,
        d.usuario_manual,
        d.fabricante_manual,
        u.nombre     AS usuario_bd,
        v.fabricante AS fabricante_oui,
        d.ultima_actualizacion
    FROM dispositivo d
    LEFT JOIN usuario    u ON d.usuario_id = u.id
    LEFT JOIN vendor_oui v ON v.prefix     = d.mac_prefix
    WHERE $where
    ORDER BY d.ip ASC
    LIMIT ? OFFSET ?
";

$params2Types = $paramsTypes . "ii";
$params2 = array_merge($params, [$porPagina, $offset]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($params2Types, ...$params2);
$stmt->execute();
$res = $stmt->get_result();

$filas = [];
while ($row = $res->fetch_assoc()) {
    $usuarioMostrar    = $row['usuario_manual'] ?: ($row['usuario_bd'] ?: '--');
    $fabricanteMostrar = $row['fabricante_manual'] ?: ($row['fabricante_oui'] ?: 'Desconocido');

    $ultima = $row['ultima_actualizacion'];
    $ts     = $ultima ? strtotime($ultima) : 0;
    $activo = $ts && $ts >= (time() - $minutosActivo * 60);

    $filas[] = [
        'id'         => (int)$row['id'],
        'ip'         => $row['ip'],
        'mac'        => $row['mac'],
        'nombre_pc'  => $row['nombre_pc'] ?: '(sin nombre)',
        'usuario'    => $usuarioMostrar,
        'fabricante' => $fabricanteMostrar,
        'estado'     => $activo ? 'ACTIVO' : 'INACTIVO',
        'ultima'     => $ultima ?: '',
    ];
}
$stmt->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Inventario — Gestión IP/MAC</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4"/>
  <style>
    .btn.danger { background:#ef4444; color:#fff; }
    .btn.danger:hover { background:#dc2626; }
  </style>
</head>
<body>

  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Inventario</div>
    <div class="nav-actions">

      <form action="/gestion_ipmac/codigo/php/scan.php" method="post" style="display:inline;">
        <button class="btn primary" type="submit">🔍 Escanear red</button>
      </form>

      <form action="/gestion_ipmac/codigo/php/import_pihole.php" method="post" style="display:inline;">
        <button class="btn" type="submit">📥 Importar DNS</button>
      </form>

      <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">🏠 Dashboard</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/activos.php">Ver activos</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/inactivos.php">Ver inactivos</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/logout.php">Salir</a>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert" data-flash style="max-width:1100px; margin:12px auto;">
      <?php
        $msg = $_GET['msg'];
        echo match ($msg) {
          'eliminado_ok'     => '✅ Dispositivo eliminado correctamente (soft delete).',
          'eliminado_error'  => '❌ No se pudo eliminar el dispositivo.',
          'restaurado_ok'    => '✅ Dispositivo restaurado correctamente.',
          'restaurado_error' => '❌ No se pudo restaurar el dispositivo.',
          default            => ''
        };
      ?>
    </div>
  <?php endif; ?>

  <div class="container">

    <h2 class="section-title">Inventario de dispositivos — Red ID <?= (int)$activeRedId ?></h2>

    <div class="card">
      <form method="get" class="row" style="display:flex; gap:12px; align-items:center;">
        <input
          type="text"
          name="q"
          value="<?= e($q) ?>"
          placeholder="Buscar por IP, MAC, equipo, usuario o fabricante..."
          style="flex:1; min-width:220px; padding:8px; border-radius:10px; border:1px solid rgba(148,163,184,0.3); background:rgba(15,23,42,0.6); color:#e5e7eb;"
        >
        <button class="btn" type="submit">Buscar</button>
        <?php if ($q !== ''): ?>
          <a class="btn" href="/gestion_ipmac/codigo/php/inventario.php">Limpiar</a>
        <?php endif; ?>
        <span class="muted" style="margin-left:auto;">
          Total: <?= (int)$totalFilas ?> dispositivos
        </span>
      </form>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>IP</th>
          <th>MAC</th>
          <th>Equipo</th>
          <th>Usuario</th>
          <th>Fabricante</th>
          <th>Estado</th>
          <th>Actualizado</th>
          <th>Acciones</th>
        </tr>
      </thead>

      <tbody>
        <?php if (empty($filas)): ?>
          <tr>
            <td colspan="8" class="muted">No hay dispositivos para mostrar en esta red.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($filas as $d): ?>
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

              <td class="<?= $d['estado']==='ACTIVO' ? 'status ok' : 'status bad' ?>">
                <?= e($d['estado']) ?>
              </td>

              <td><?= e($d['ultima']) ?></td>

              <td>
                <a class="btn small" href="/gestion_ipmac/codigo/php/dispositivo_editar.php?id=<?= (int)$d['id'] ?>">
                  ✏️ Editar
                </a>
                <?php if (function_exists('has_role_min') ? has_role_min(2) : true): ?>
                  <a class="btn small danger"
                     href="/gestion_ipmac/codigo/php/dispositivo_eliminar.php?id=<?= (int)$d['id'] ?>"
                     onclick="return confirm('¿Seguro que deseas eliminar este dispositivo? Se ocultará del inventario (soft delete).');">
                    🗑️ Eliminar
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($totalPaginas > 1): ?>
      <div class="row" style="margin-top:18px; display:flex; justify-content:center; gap:6px; flex-wrap:wrap;">
        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
          <?php
            $url = '/gestion_ipmac/codigo/php/inventario.php?p='.$p;
            if ($q !== '') $url .= '&q='.urlencode($q);
          ?>
          <a class="btn small <?= ($p === $page ? 'primary' : '') ?>" href="<?= $url ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>

</body>
</html>
