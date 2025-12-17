<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape HTML (NO usamos e() para evitar redeclare si ya existe en funciones.php)
 */
if (!function_exists('esc')) {
    function esc($v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// === Usuario actual (para mostrar rol y filtrar acciones) ===
$me = current_user(); // ['rol_nivel'=>1/2/3, 'rol_nombre'=>'lector|operador|admin']

/* ===============================
   Parámetros
   =============================== */
$minutosActivo = 30;

/* ===============================
   Config (red activa + avisos)
   =============================== */
$cfg = [];
if ($res = $conn->query("SELECT clave, valor FROM config")) {
    while ($r = $res->fetch_assoc()) {
        $cfg[$r['clave']] = $r['valor'];
    }
}

$activeRedId = isset($cfg['active_red_id']) ? (int)$cfg['active_red_id'] : 1;
$activeCidr  = trim($cfg['active_cidr'] ?? ($cfg['nmap_range'] ?? ''));

// Banner SOLO cuando haces reset (flash)
$resetBanner = null;
if (!empty($_SESSION['flash_reset'])) {
    $resetBanner = $_SESSION['flash_reset'];
    unset($_SESSION['flash_reset']); // se muestra 1 vez
}

$flashOk  = null;
$flashErr = null;
if (!empty($_SESSION['flash_ok'])) {
    $flashOk = $_SESSION['flash_ok'];
    unset($_SESSION['flash_ok']);
}
if (!empty($_SESSION['flash_err'])) {
    $flashErr = $_SESSION['flash_err'];
    unset($_SESSION['flash_err']);
}

/* ===============================
   RESET manual desde dashboard
   - Solo Operador/Admin
   - SOLO afecta a la red activa
   - NO TRUNCATE dns_logs (borra solo por red_id)
   - Banner solo via flash
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_reset'])) {
    if (!is_operador()) {
        $_SESSION['flash_err'] = "Permiso denegado: solo Operador/Admin puede ejecutar Reset.";
        header("Location: dashboard.php");
        exit;
    }

    $motivo = trim($_POST['motivo'] ?? '');
    if ($motivo === '') $motivo = 'Reset manual desde Dashboard';

    $conn->begin_transaction();
    try {
        // 1) Ocultar SOLO dispositivos de la red activa
        $stmt = $conn->prepare("
            UPDATE dispositivo
            SET eliminado = 1
            WHERE (eliminado IS NULL OR eliminado = 0)
              AND red_id = ?
        ");
        $stmt->bind_param("i", $activeRedId);
        $stmt->execute();
        $stmt->close();

        // 2) Borrar SOLO DNS de la red activa (NO TRUNCATE)
        //$stmt = $conn->prepare("DELETE FROM dns_logs WHERE red_id = ?");
        //$stmt->bind_param("i", $activeRedId);
        //$stmt->execute();
        //$stmt->close();

        // 3) Guardar estado en config (opcional para auditoría)
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("
            INSERT INTO config (clave, valor)
            VALUES
              ('last_reset_ts', ?),
              ('last_reset_reason', ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ");
        $stmt->bind_param("ss", $now, $motivo);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        $_SESSION['flash_reset'] = "Se ejecutó un RESET ({$now}). Motivo: {$motivo}";
        $_SESSION['flash_ok']    = "Reset ejecutado: se limpiaron datos SOLO de la red activa (ID {$activeRedId}).";

    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['flash_err'] = "Error al ejecutar Reset: ".$e->getMessage();
    }

    header("Location: dashboard.php");
    exit;
}

/* ===============================
   KPIs: Activos / Inactivos (excluye eliminados)
   (filtrado por red activa)
   =============================== */

// Activos
$activos = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM dispositivo
    WHERE (eliminado IS NULL OR eliminado=0)
      AND red_id = ?
      AND ultima_actualizacion IS NOT NULL
      AND ultima_actualizacion >= (NOW() - INTERVAL ? MINUTE)
");
$stmt->bind_param("ii", $activeRedId, $minutosActivo);
$stmt->execute();
$stmt->bind_result($activos);
$stmt->fetch();
$stmt->close();
$activos = (int)$activos;

// Inactivos
$inactivos = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM dispositivo
    WHERE (eliminado IS NULL OR eliminado=0)
      AND red_id = ?
      AND (
            ultima_actualizacion IS NULL
         OR ultima_actualizacion < (NOW() - INTERVAL ? MINUTE)
      )
");
$stmt->bind_param("ii", $activeRedId, $minutosActivo);
$stmt->execute();
$stmt->bind_result($inactivos);
$stmt->fetch();
$stmt->close();
$inactivos = (int)$inactivos;

$total = $activos + $inactivos;

/* ===============================
   Usuarios de Red (KPIs)
   =============================== */
$totalUsuariosRed = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM usuario_red");
if ($res) $totalUsuariosRed = (int)$res->fetch_assoc()['c'];

$usuariosAsignados = 0;
$res = $conn->query("
    SELECT COUNT(DISTINCT usuario_red_id) AS c
    FROM usuario_red_dispositivo
    WHERE asignado_hasta IS NULL OR asignado_hasta >= NOW()
");
if ($res) $usuariosAsignados = (int)$res->fetch_assoc()['c'];

$usuariosSinAsignar = max(0, $totalUsuariosRed - $usuariosAsignados);

/* ===============================
   Últimos 10 dispositivos (excluye eliminados)
   (filtrado por red activa)
   =============================== */
$sqlUltimos = "
    SELECT 
        d.id,
        d.ip,
        d.mac,
        d.nombre_pc,
        d.usuario_manual,
        d.fabricante_manual,
        v.fabricante AS fabricante_oui,
        d.ultima_actualizacion,
        asig.usuario_red
    FROM dispositivo d
    LEFT JOIN vendor_oui v
           ON v.prefix = d.mac_prefix
    LEFT JOIN (
        SELECT urd.dispositivo_id,
               ur.nombre AS usuario_red,
               urd.asignado_desde
        FROM usuario_red_dispositivo urd
        JOIN usuario_red ur
          ON ur.id = urd.usuario_red_id
        WHERE (urd.asignado_hasta IS NULL OR urd.asignado_hasta >= NOW())
          AND urd.asignado_desde = (
              SELECT MAX(urd2.asignado_desde)
              FROM usuario_red_dispositivo urd2
              WHERE urd2.dispositivo_id = urd.dispositivo_id
                AND (urd2.asignado_hasta IS NULL OR urd2.asignado_hasta >= NOW())
          )
    ) as asig
      ON asig.dispositivo_id = d.id
    WHERE (d.eliminado IS NULL OR d.eliminado=0)
      AND d.red_id = ?
    ORDER BY d.ultima_actualizacion DESC, d.ip ASC
    LIMIT 10
";

$ultimos = [];
$stmt = $conn->prepare($sqlUltimos);
$stmt->bind_param("i", $activeRedId);
$stmt->execute();
$res = $stmt->get_result();
while ($res && ($row = $res->fetch_assoc())) {
    $usuarioMostrar = $row['usuario_manual'] ?: ($row['usuario_red'] ?: '--');
    $fabricanteMostrar = $row['fabricante_manual'] ?: ($row['fabricante_oui'] ?: 'Desconocido');

    $ultima = $row['ultima_actualizacion'];
    $ts     = $ultima ? strtotime($ultima) : 0;
    $activo = $ts && $ts >= (time() - $minutosActivo * 60);

    $ultimos[] = [
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

/* ===============================
   KPIs DNS (FILTRADO por red activa)
   =============================== */
// total consultas hoy (red activa)
$totalDnsHoy = 0;
$stmt = $conn->prepare("
  SELECT COALESCE(SUM(contador),0) AS c
  FROM dns_logs
  WHERE red_id = ?
    AND DATE(ts) = CURDATE()
");
$stmt->bind_param("i", $activeRedId);
$stmt->execute();
$stmt->bind_result($totalDnsHoy);
$stmt->fetch();
$stmt->close();
$totalDnsHoy = (int)$totalDnsHoy;

// dominios únicos (red activa)
$dominiosUnicos = 0;
$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT dominio) AS c
  FROM dns_logs
  WHERE red_id = ?
");
$stmt->bind_param("i", $activeRedId);
$stmt->execute();
$stmt->bind_result($dominiosUnicos);
$stmt->fetch();
$stmt->close();
$dominiosUnicos = (int)$dominiosUnicos;

// top dominio (red activa)
$topDominio = '—';
$topDominioHits = 0;
$stmt = $conn->prepare("
  SELECT dominio, SUM(contador) AS hits
  FROM dns_logs
  WHERE red_id = ?
  GROUP BY dominio
  ORDER BY hits DESC
  LIMIT 1
");
$stmt->bind_param("i", $activeRedId);
$stmt->execute();
$res = $stmt->get_result();
if ($res && ($row = $res->fetch_assoc())) {
    $topDominio = $row['dominio'];
    $topDominioHits = (int)$row['hits'];
}
$stmt->close();

/* ===============================
   DNS: filtros + orden + paginación (50 por página)
   (FILTRADO por red activa)
   =============================== */
$q    = trim($_GET['q'] ?? '');
$dias = (int)($_GET['dias'] ?? 30);
if ($dias < 1) $dias = 1;
if ($dias > 365) $dias = 365;

$sort = $_GET['sort'] ?? 'ts';
$dir  = strtoupper($_GET['dir'] ?? 'DESC');
$dir  = ($dir === 'ASC') ? 'ASC' : 'DESC';

$allowedSort = ['ip','dominio','tipo','contador','ts'];
if (!in_array($sort, $allowedSort, true)) $sort = 'ts';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$where = "WHERE red_id = ? AND ts >= (NOW() - INTERVAL ? DAY)";
$paramsTypes = "ii";
$params = [$activeRedId, $dias];

if ($q !== '') {
    $where .= " AND (ip LIKE ? OR dominio LIKE ? OR tipo LIKE ?)";
    $paramsTypes .= "sss";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Total rows
$totalRows = 0;
$sqlTotal = "SELECT COUNT(*) AS c FROM dns_logs {$where}";
$stmt = $conn->prepare($sqlTotal);
$stmt->bind_param($paramsTypes, ...$params);
$stmt->execute();
$totalRows = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Logs paginados
$logs = [];
$sqlLogs = "
  SELECT ip, dominio, tipo, contador, ts
  FROM dns_logs
  {$where}
  ORDER BY {$sort} {$dir}
  LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $conn->prepare($sqlLogs);
$stmt->bind_param($paramsTypes, ...$params);
$stmt->execute();
$resLogs = $stmt->get_result();
while ($resLogs && ($row = $resLogs->fetch_assoc())) $logs[] = $row;
$stmt->close();

// helper: link de orden
function sortLink($col, $label, $sort, $dir, $dias, $q, $page) {
    $nextDir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $params = http_build_query([
        'dias' => $dias,
        'q'    => $q,
        'sort' => $col,
        'dir'  => $nextDir,
        'page' => $page,
    ]);
    return "<a href=\"?{$params}\" style=\"color:#e5e7eb; text-decoration:none;\">{$label} ↕</a>";
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Dashboard — Gestión IP/MAC</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=3"/>

  <style>
    /* Estilo general tipo dashboard oscuro */
    body {
        margin: 0;
        padding: 0;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: radial-gradient(circle at top, #1e293b 0, #020617 45%, #000 100%);
        color: #e5e7eb;
        min-height: 100vh;
    }

    .navbar {
        position: sticky;
        top: 0;
        z-index: 40;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 32px;
        background: rgba(15, 23, 42, 0.96);
        border-bottom: 1px solid rgba(148, 163, 184, 0.3);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }

    .brand {
        font-weight: 600;
        font-size: 15px;
        color: #e5e7eb;
    }

    .nav-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .btn {
        border-radius: 9999px;
        border: 1px solid rgba(148, 163, 184, 0.5);
        background: transparent;
        padding: 6px 14px;
        font-size: 13px;
        color: #e5e7eb;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s ease, border-color 0.15s ease, transform 0.05s ease;
    }

    .btn:hover {
        background: rgba(30, 64, 175, 0.4);
        border-color: rgba(129, 140, 248, 0.9);
        transform: translateY(-1px);
    }

    .btn.primary {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 10px 30px rgba(79, 70, 229, 0.7);
    }

    .btn.primary:hover {
        filter: brightness(1.06);
        transform: translateY(-1px);
    }

    .btn.small {
        padding: 4px 10px;
        font-size: 12px;
    }

    .btn.danger {
        border-color: rgba(248, 113, 113, 0.9);
        color: #fff;
        background: rgba(248, 113, 113, 0.25);
    }
    .btn.danger:hover {
        background: rgba(248, 113, 113, 0.4);
    }

    .container {
        max-width: 1200px;
        margin: 32px auto 40px;
        padding: 0 20px 40px;
    }

    .section-title {
        font-size: 15px;
        font-weight: 600;
        color: #e5e7eb;
        margin: 10px 0 12px;
    }

    .grid.cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 12px;
    }

    .card {
        background: rgba(15, 23, 42, 0.96);
        border-radius: 18px;
        padding: 18px 18px 16px;
        border: 1px solid rgba(148, 163, 184, 0.35);
        box-shadow: 0 16px 35px rgba(15, 23, 42, 0.85);
    }

    .card h3 {
        margin: 0 0 8px;
        font-size: 13px;
        font-weight: 600;
        color: #9ca3af;
    }

    .metric {
        font-size: 28px;
        font-weight: 700;
        color: #f9fafb;
        margin-bottom: 4px;
    }

    .muted {
        font-size: 12px;
        color: #6b7280;
    }

    table.table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        background: rgba(15, 23, 42, 0.98);
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 16px 35px rgba(15,23,42,0.9);
    }

    table.table thead {
        background: rgba(30, 64, 175, 0.9);
    }

    table.table th,
    table.table td {
        padding: 8px 10px;
        font-size: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(30, 64, 175, 0.4);
    }

    table.table th {
        font-weight: 600;
        color: #e5e7eb;
        white-space: nowrap;
    }

    table.table tbody tr:nth-child(even) {
        background: rgba(15, 23, 42, 0.96);
    }

    table.table tbody tr:nth-child(odd) {
        background: rgba(15, 23, 42, 0.92);
    }

    table.table tbody tr:hover {
        background: rgba(30, 64, 175, 0.45);
    }

    .status.ok {
        color: #22c55e;
        font-weight: 600;
        font-size: 12px;
    }

    .status.bad {
        color: #f97373;
        font-weight: 600;
        font-size: 12px;
    }

    .footer {
        text-align: center;
        font-size: 11px;
        padding: 12px 0 16px;
        color: #6b7280;
    }

    /* mini estilos para los tipos DNS */
    .tag-dns {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.80rem;
        font-weight: 600;
        display: inline-block;
    }
    .tag-a      { background:#1f6feb; color:#fff; }
    .tag-aaaa   { background:#8250df; color:#fff; }
    .tag-https  { background:#2da44e; color:#fff; }
    .tag-other  { background:#6e7781; color:#fff; }

    /* banner reset */
    .banner {
        margin: 14px 0 18px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid rgba(251, 191, 36, 0.35);
        background: rgba(251, 191, 36, 0.12);
        color: #fde68a;
        font-size: 13px;
        display:flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
    }
    .banner strong { color:#fff; }

    .toast-ok {
        margin: 12px 0;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid rgba(34,197,94,0.35);
        background: rgba(34,197,94,0.12);
        color: #bbf7d0;
        font-size: 13px;
    }
    .toast-err {
        margin: 12px 0;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid rgba(248,113,113,0.35);
        background: rgba(248,113,113,0.12);
        color: #fecaca;
        font-size: 13px;
    }

    @media (max-width: 768px) {
        .navbar { padding: 10px 14px; }
        .nav-actions { gap: 6px; }
        .container { margin-top: 20px; }
        .banner { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>

  <!-- Barra superior -->
  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Dashboard</div>
    <div class="nav-actions">

      <?php if (is_operador()): ?>
        <form action="/gestion_ipmac/codigo/php/scan.php" method="post" style="display:inline;">
          <button class="btn primary" type="submit">🔍 Escanear red (Nmap)</button>
        </form>

        <form action="/gestion_ipmac/codigo/php/import_pihole.php" method="post" style="display:inline;">
          <button class="btn" type="submit">📥 Importar DNS (Pi-hole)</button>
        </form>

        <form action="/gestion_ipmac/codigo/php/export_csv.php" method="get" style="display:inline;">
          <button class="btn" type="submit">⬇ Generar reporte CSV</button>
        </form>

        <a class="btn" href="/gestion_ipmac/codigo/php/auditoria.php">📝 Auditoría</a>
      <?php endif; ?>

      <a class="btn" href="/gestion_ipmac/codigo/php/inventario.php">📦 Inventario</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/activos.php">Ver activos</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/inactivos.php">Ver inactivos</a>

      <span class="muted">Red activa: <b><?= esc($activeCidr ?: '—') ?></b></span>
      <span class="muted">Rol: <b><?= esc($me['rol_nombre'] ?? '') ?></b></span>
      <a class="btn" href="/gestion_ipmac/codigo/php/logout.php">Salir</a>
    </div>
  </div>

  <!-- CONTENIDO -->
  <div class="container">

    <?php if ($resetBanner): ?>
      <div class="banner">
        <div>
          <strong>⚠ Aviso:</strong> <?= esc($resetBanner) ?>
          <div class="muted" style="margin-top:6px;">
            Esto evita mezclar equipos de redes distintas (por ejemplo, casa vs. otra red).
          </div>
        </div>
        <?php if (is_operador()): ?>
          <button class="btn danger" onclick="openResetConfirm()">🧹 Reset (limpiar red)</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (is_operador()): ?>
      <div style="margin: 14px 0 18px;">
        <button class="btn danger" onclick="openResetConfirm()">🧹 Reset (limpiar red)</button>
        <span class="muted" style="margin-left:10px;">(Úsalo solo si cambiaste de red o quieres limpiar datos)</span>
      </div>
    <?php endif; ?>

    <?php if ($flashOk): ?><div class="toast-ok"><?= esc($flashOk) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="toast-err"><?= esc($flashErr) ?></div><?php endif; ?>

    <h2 class="section-title">Resumen general</h2>

    <div class="grid cards">
      <div class="card">
        <h3>Dispositivos activos</h3>
        <div class="metric"><?= (int)$activos ?></div>
        <div class="muted">en los últimos <?= (int)$minutosActivo ?> minutos</div>
      </div>

      <div class="card">
        <h3>Dispositivos inactivos</h3>
        <div class="metric"><?= (int)$inactivos ?></div>
        <div class="muted">sin actualización reciente</div>
      </div>

      <div class="card">
        <h3>Total dispositivos</h3>
        <div class="metric"><?= (int)$total ?></div>
        <div class="muted">registrados (red activa)</div>
      </div>
    </div>

    <!-- KPIs Usuarios de Red -->
    <h2 class="section-title" style="margin-top:12px;">Usuarios de red</h2>
    <div class="grid cards">
      <div class="card">
        <h3>Total usuarios de red</h3>
        <div class="metric"><?= (int)$totalUsuariosRed ?></div>
        <div class="muted">en tabla <code>usuario_red</code></div>
      </div>
      <div class="card">
        <h3>Usuarios asignados</h3>
        <div class="metric"><?= (int)$usuariosAsignados ?></div>
        <div class="muted">con asignación vigente a un dispositivo</div>
      </div>
      <div class="card">
        <h3>Usuarios sin asignar</h3>
        <div class="metric"><?= (int)$usuariosSinAsignar ?></div>
        <div class="muted">disponibles para asignación</div>
      </div>
    </div>

    <!-- Resumen DNS -->
    <h2 class="section-title" style="margin-top:24px;">Resumen DNS (Pi-hole)</h2>

    <div class="grid cards">
      <div class="card">
        <h3>Consultas DNS (hoy)</h3>
        <div class="metric"><?= (int)$totalDnsHoy ?></div>
        <div class="muted">según importaciones desde Pi-hole</div>
      </div>

      <div class="card">
        <h3>Dominios distintos</h3>
        <div class="metric"><?= (int)$dominiosUnicos ?></div>
        <div class="muted">en tabla <code>dns_logs</code> (red activa)</div>
      </div>

      <div class="card">
        <h3>Dominio más consultado</h3>
        <div class="metric" style="font-size:1rem;">
          <?= $topDominio ? esc($topDominio) : '—' ?>
        </div>
        <div class="muted"><?= (int)$topDominioHits ?> consultas</div>
      </div>
    </div>

    <!-- Últimos dispositivos -->
    <h3 class="section-title" style="margin-top:16px">Últimos 10 dispositivos actualizados</h3>
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
        <?php if (empty($ultimos)): ?>
        <tr><td colspan="8" class="muted">Aún no hay dispositivos para esta red.</td></tr>
        <?php else: ?>
        <?php foreach ($ultimos as $d): ?>
        <tr>
          <td><?= esc($d['ip']) ?></td>
          <td>
            <?php if (!empty($d['mac'])): ?>
              <?= esc($d['mac']) ?>
            <?php else: ?>
              <span class="muted">⚠ no detectada</span>
            <?php endif; ?>
          </td>
          <td><?= esc($d['nombre_pc']) ?></td>
          <td><?= esc($d['usuario']) ?></td>
          <td><?= esc($d['fabricante']) ?></td>
          <td class="<?= ($d['estado']==='ACTIVO') ? 'status ok' : 'status bad' ?>">
            <?= esc($d['estado']) ?>
          </td>
          <td><?= esc($d['ultima']) ?></td>
          <td>
            <?php if (is_operador()): ?>
              <a class="btn small" href="/gestion_ipmac/codigo/php/dispositivo_editar.php?id=<?= (int)$d['id'] ?>">
                ✏️ Editar
              </a>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- CONSULTAS DNS EN TIEMPO REAL -->
    <h3 class="section-title" style="margin-top:24px;">Consultas DNS en tiempo real (últimos 20)</h3>
    <?php include __DIR__ . '/dns_realtime.php'; ?>

    <!-- CONSULTAS DNS (con filtro + paginación) -->
    <h3 class="section-title" style="margin-top:24px;">Consultas DNS (50 por página)</h3>

    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin: 10px 0 6px;">
      <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <input type="hidden" name="sort" value="<?= esc($sort) ?>">
        <input type="hidden" name="dir" value="<?= esc($dir) ?>">

        <select name="dias" class="btn" style="border-radius:12px;">
          <?php foreach ([1,3,7,15,30,60,90,180,365] as $d): ?>
            <option value="<?= $d ?>" <?= ($dias===$d?'selected':'') ?>><?= $d ?> días</option>
          <?php endforeach; ?>
        </select>

        <input
          class="btn"
          style="border-radius:12px; width:260px; text-align:left;"
          type="text"
          name="q"
          placeholder="Buscar IP / dominio / tipo…"
          value="<?= esc($q) ?>"
        />

        <button class="btn" type="submit">Aplicar</button>
      </form>

      <div class="muted">Mostrando <?= (int)count($logs) ?> de <?= (int)$totalRows ?> registros</div>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th><?= sortLink('ip','IP',$sort,$dir,$dias,$q,$page) ?></th>
          <th><?= sortLink('dominio','Dominio',$sort,$dir,$dias,$q,$page) ?></th>
          <th><?= sortLink('tipo','Tipo',$sort,$dir,$dias,$q,$page) ?></th>
          <th><?= sortLink('contador','Consultas',$sort,$dir,$dias,$q,$page) ?></th>
          <th><?= sortLink('ts','Fecha',$sort,$dir,$dias,$q,$page) ?></th>
          <th>Fuente</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr>
          <td colspan="6" class="muted">Sin consultas para la red activa en el rango seleccionado.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <?php
          $tipo  = strtoupper($log['tipo'] ?? '');
          $class = 'tag-dns tag-other';
          if     ($tipo === 'A')     $class = 'tag-dns tag-a';
          elseif ($tipo === 'AAAA')  $class = 'tag-dns tag-aaaa';
          elseif ($tipo === 'HTTPS') $class = 'tag-dns tag-https';
        ?>
        <tr>
          <td><?= esc($log['ip']) ?></td>
          <td><?= esc($log['dominio']) ?></td>
          <td><span class="<?= $class ?>"><?= esc($tipo) ?></span></td>
          <td><?= (int)$log['contador'] ?></td>
          <td><?= esc($log['ts']) ?></td>
          <td>Pi-hole</td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div style="display:flex; gap:8px; align-items:center; justify-content:flex-end; margin-top:10px; flex-wrap:wrap;">
      <?php
        $base = ['dias'=>$dias,'q'=>$q,'sort'=>$sort,'dir'=>$dir];
        $prev = max(1, $page-1);
        $next = min($totalPages, $page+1);
      ?>
      <a class="btn small" href="?<?= http_build_query($base + ['page'=>1]) ?>">«</a>
      <a class="btn small" href="?<?= http_build_query($base + ['page'=>$prev]) ?>">‹</a>

      <span class="muted">Página <b><?= (int)$page ?></b> / <?= (int)$totalPages ?></span>

      <a class="btn small" href="?<?= http_build_query($base + ['page'=>$next]) ?>">›</a>
      <a class="btn small" href="?<?= http_build_query($base + ['page'=>$totalPages]) ?>">»</a>
    </div>

  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>

  <!-- Form oculto reset -->
  <form id="resetForm" method="post" style="display:none;">
    <input type="hidden" name="do_reset" value="1">
    <input type="hidden" name="motivo" id="resetMotivo" value="">
  </form>

  <script>
    function openResetConfirm() {
      const msg =
`⚠ ADVERTENCIA: Reset de red (solo red activa)
- Oculta dispositivos actuales (eliminado=1) SOLO en esta red
- Borra dns_logs SOLO en esta red (no afecta otras redes)

Úsalo SOLO si cambiaste de red o necesitas limpiar.

¿Deseas continuar?`;

      if (!confirm(msg)) return;

      const motivo = prompt("Motivo del reset (opcional):", "Reset manual desde Dashboard");
      document.getElementById('resetMotivo').value = motivo || "Reset manual desde Dashboard";
      document.getElementById('resetForm').submit();
    }
  </script>

</body>
</html>
