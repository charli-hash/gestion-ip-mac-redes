<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();

// === Usuario actual (para mostrar rol y filtrar acciones) ===
$me = current_user(); // ['rol_nivel'=>1/2/3, 'rol_nombre'=>'lector|operador|admin']

/* ===============================
   Parámetros
   =============================== */
$minutosActivo = 30;

/* ===============================
   KPIs: Activos / Inactivos (excluye eliminados)
   =============================== */

// Activos
$sqlCountActivos = "
    SELECT COUNT(*) AS c
    FROM dispositivo
    WHERE (eliminado IS NULL OR eliminado=0)
      AND ultima_actualizacion IS NOT NULL
      AND ultima_actualizacion >= (NOW() - INTERVAL {$minutosActivo} MINUTE)
";
$activos = 0;
if ($res = $conn->query($sqlCountActivos)) {
    $row     = $res->fetch_assoc();
    $activos = (int)$row['c'];
}

// Inactivos
$sqlCountInactivos = "
    SELECT COUNT(*) AS c
    FROM dispositivo
    WHERE (eliminado IS NULL OR eliminado=0)
      AND (
            ultima_actualizacion IS NULL
         OR ultima_actualizacion < (NOW() - INTERVAL {$minutosActivo} MINUTE)
      )
";
$inactivos = 0;
if ($res = $conn->query($sqlCountInactivos)) {
    $row       = $res->fetch_assoc();
    $inactivos = (int)$row['c'];
}

// Total dispositivos (visibles)
$total = $activos + $inactivos;

/* ===============================
   Usuarios de Red (KPIs)
   =============================== */

// Total de usuarios de red
$totalUsuariosRed = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM usuario_red");
if ($res) $totalUsuariosRed = (int)$res->fetch_assoc()['c'];

// Usuarios con asignación vigente a algún dispositivo
$usuariosAsignados = 0;
$res = $conn->query("
    SELECT COUNT(DISTINCT usuario_red_id) AS c
    FROM usuario_red_dispositivo
    WHERE asignado_hasta IS NULL OR asignado_hasta >= NOW()
");
if ($res) $usuariosAsignados = (int)$res->fetch_assoc()['c'];

// Usuarios sin asignar
$usuariosSinAsignar = max(0, $totalUsuariosRed - $usuariosAsignados);

/* ===============================
   Últimos 10 dispositivos (excluye eliminados)
   =============================== */
/*
   Tomamos el usuario de red vigente usando usuario_red_dispositivo:
   - asignado_hasta NULL o futuro
   - la asignación más reciente por dispositivo
*/
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
    ORDER BY d.ultima_actualizacion DESC, d.ip ASC
    LIMIT 10
";

$ultimos = [];
if ($res = $conn->query($sqlUltimos)) {
    while ($row = $res->fetch_assoc()) {
        // usuario mostrado: manual > usuario_red asignado vigente > "--"
        $usuarioMostrar = $row['usuario_manual'] ?: ($row['usuario_red'] ?: '--');

        // fabricante mostrado: manual > OUI > "Desconocido"
        $fabricanteMostrar = $row['fabricante_manual'] ?: ($row['fabricante_oui'] ?: 'Desconocido');

        // estado (ACTIVO si fue visto en los últimos X minutos)
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
}

/* ===============================
   Consultas DNS (últimos 50)
   =============================== */
$logs = [];
$resLogs = $conn->query("
    SELECT ip, dominio, tipo, contador, ts
    FROM dns_logs
    ORDER BY ts DESC
    LIMIT 50
");
if ($resLogs) {
    while ($row = $resLogs->fetch_assoc()) {
        $logs[] = $row;
    }
}

/* ===============================
   Métricas DNS (KPIs)
   =============================== */

// total de consultas de hoy (suma de contador)
$totalDnsHoy = 0;
$res = $conn->query("
    SELECT COALESCE(SUM(contador),0) AS c
    FROM dns_logs
    WHERE DATE(ts) = CURDATE()
");
if ($res) {
    $totalDnsHoy = (int)$res->fetch_assoc()['c'];
}

// dominios únicos
$dominiosUnicos = 0;
$res = $conn->query("
    SELECT COUNT(DISTINCT dominio) AS c
    FROM dns_logs
");
if ($res) {
    $dominiosUnicos = (int)$res->fetch_assoc()['c'];
}

// dominio más consultado
$topDominio      = '—';
$topDominioHits  = 0;
$res = $conn->query("
    SELECT dominio, SUM(contador) AS hits
    FROM dns_logs
    GROUP BY dominio
    ORDER BY hits DESC
    LIMIT 1
");
if ($res && $row = $res->fetch_assoc()) {
    $topDominio     = $row['dominio'];
    $topDominioHits = (int)$row['hits'];
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

    @media (max-width: 768px) {
        .navbar {
            padding: 10px 14px;
        }
        .nav-actions {
            gap: 6px;
        }
        .container {
            margin-top: 20px;
        }
    }
  </style>
</head>
<body>

  <!-- Barra superior -->
  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Dashboard</div>
    <div class="nav-actions">

      <?php if (is_operador()): // operador (2) y admin (3) pueden ejecutar acciones ?>
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

      <?php if (is_admin()): ?>
        <a class="btn" href="/gestion_ipmac/codigo/php/backup.php">🧰 Backup BD</a>
      <?php endif; ?>

      <a class="btn" href="/gestion_ipmac/codigo/php/inventario.php">📦 Inventario</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/activos.php">Ver activos</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/inactivos.php">Ver inactivos</a>

      <span class="muted">Rol: <b><?= e($me['rol_nombre']) ?></b></span>
      <a class="btn" href="/gestion_ipmac/codigo/php/logout.php">Salir</a>
    </div>
  </div>

  <!-- CONTENIDO -->
  <div class="container">
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
        <div class="muted">registrados</div>
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
        <div class="muted">en tabla <code>dns_logs</code></div>
      </div>

      <div class="card">
        <h3>Dominio más consultado</h3>
        <div class="metric" style="font-size:1rem;">
          <?= $topDominio ? e($topDominio) : '—' ?>
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
        <tr><td colspan="8" class="muted">Aún no hay dispositivos.</td></tr>
        <?php else: ?>
        <?php foreach ($ultimos as $d): ?>
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

    <!-- CONSULTAS DNS (últimos 50) -->
    <h3 class="section-title" style="margin-top:24px;">Consultas DNS (últimos 50)</h3>

    <table class="table">
      <thead>
        <tr>
          <th>IP</th>
          <th>Dominio</th>
          <th>Tipo</th>
          <th>Consultas</th>
          <th>Fecha</th>
          <th>Fuente</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr>
          <td colspan="6" class="muted">Sin consultas recientes importadas desde Pi-hole.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <?php
          $tipo  = strtoupper($log['tipo']);
          $class = 'tag-dns tag-other';
          if     ($tipo === 'A')     $class = 'tag-dns tag-a';
          elseif ($tipo === 'AAAA')  $class = 'tag-dns tag-aaaa';
          elseif ($tipo === 'HTTPS') $class = 'tag-dns tag-https';
        ?>
        <tr>
          <td><?= e($log['ip']) ?></td>
          <td><?= e($log['dominio']) ?></td>
          <td><span class="<?= $class ?>"><?= e($tipo) ?></span></td>
          <td><?= (int)$log['contador'] ?></td>
          <td><?= e($log['ts']) ?></td>
          <td>Pi-hole</td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>

</body>
</html>