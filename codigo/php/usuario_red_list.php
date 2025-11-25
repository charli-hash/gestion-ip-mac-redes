<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();
require_role_min(2); // operador o admin

// ===============================
// Cargar usuarios de red + asignación vigente (si existe)
// ===============================
$sql = "
  SELECT
    ur.id,
    ur.nombre,
    ur.correo,
    ur.departamento,
    ur.activo,
    d.id  AS disp_id,
    d.ip  AS disp_ip,
    d.nombre_pc AS disp_nombre
  FROM usuario_red ur
  LEFT JOIN usuario_red_dispositivo urd
    ON urd.usuario_red_id = ur.id
   AND (urd.asignado_hasta IS NULL OR urd.asignado_hasta >= NOW())
  LEFT JOIN dispositivo d
    ON d.id = urd.dispositivo_id
  ORDER BY ur.nombre ASC
";
$rows = [];
if ($res = $conn->query($sql)) {
  while ($r = $res->fetch_assoc()) $rows[] = $r;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Usuarios de Red — Gestión IP/MAC</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4"/>
  <style>
    body {
      margin: 0; padding: 0;
      font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      background: radial-gradient(circle at top, #1e293b 0, #020617 45%, #000 100%);
      color: #e5e7eb; min-height: 100vh;
    }
    .navbar {
      position: sticky; top: 0; z-index: 40;
      display: flex; justify-content: space-between; align-items: center;
      padding: 12px 32px;
      background: rgba(15,23,42,.96);
      border-bottom: 1px solid rgba(148,163,184,.3);
      backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    }
    .brand { font-weight: 600; font-size: 15px; color: #e5e7eb; }
    .nav-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .btn {
      border-radius: 9999px; border: 1px solid rgba(148,163,184,.5);
      background: transparent; padding: 6px 14px; font-size: 13px; color: #e5e7eb;
      text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
      transition: background .15s ease, border-color .15s ease, transform .05s ease;
    }
    .btn:hover { background: rgba(30,64,175,.4); border-color: rgba(129,140,248,.9); transform: translateY(-1px);}
    .btn.primary { background: linear-gradient(135deg,#6366f1,#8b5cf6); border-color: transparent; color:#fff;
      box-shadow: 0 10px 30px rgba(79,70,229,.7);}
    .btn.small { padding: 4px 10px; font-size: 12px; }
    .container { max-width: 1200px; margin: 32px auto 40px; padding: 0 20px 40px; }
    .section-title { font-size: 15px; font-weight: 600; color:#e5e7eb; margin: 8px 0 12px; }
    .card {
      background: rgba(15,23,42,.96); border-radius: 18px; padding: 18px;
      border: 1px solid rgba(148,163,184,.35);
      box-shadow: 0 16px 35px rgba(15,23,42,.85); margin-bottom: 14px;
    }
    table.table {
      width: 100%; border-collapse: collapse; margin-top: 8px;
      background: rgba(15,23,42,.98); border-radius: 14px; overflow: hidden;
      box-shadow: 0 16px 35px rgba(15,23,42,.9);
    }
    table.table thead { background: rgba(30,64,175,.9); }
    table.table th, table.table td {
      padding: 8px 10px; font-size: 12px; text-align: left;
      border-bottom: 1px solid rgba(30,64,175,.4);
    }
    table.table tbody tr:nth-child(even){ background: rgba(15,23,42,.96); }
    table.table tbody tr:nth-child(odd){ background: rgba(15,23,42,.92); }
    table.table tbody tr:hover { background: rgba(30,64,175,.45); }
    .badge {
      display: inline-block; font-size: 11px; padding: 3px 8px; border-radius: 9999px;
      border: 1px solid rgba(148,163,184,.45); color: #cbd5e1;
    }
    .ok { color:#22c55e; }
    .warn { color:#fbbf24; }
    .muted { color:#6b7280; font-size: 12px; }
    .footer { text-align:center; font-size:11px; padding:12px 0 16px; color:#6b7280; }
    @media (max-width: 768px){ .navbar{padding:10px 14px;} .nav-actions{gap:6px;} .container{margin-top:20px;} }
  </style>
</head>
<body>

  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Usuarios de Red</div>
    <div class="nav-actions">
      <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a>
      <?php if (is_operador()): ?>
        <a class="btn primary" href="/gestion_ipmac/codigo/php/usuario_red_nuevo.php">➕ Nuevo usuario de red</a>
      <?php endif; ?>
      <span class="muted">Rol: <b><?= e(current_user()['rol_nombre']) ?></b></span>
      <a class="btn" href="/gestion_ipmac/codigo/php/logout.php">Salir</a>
    </div>
  </div>

  <div class="container">
    <h2 class="section-title">Listado de usuarios de red</h2>

    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Departamento</th>
            <th>Estado</th>
            <th>Asignación vigente</th>
            <th style="white-space:nowrap;">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="muted">No hay usuarios de red registrados.</td></tr>
        <?php else: foreach ($rows as $r): 
          $asignado = $r['disp_id'] ? true : false;
        ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= e($r['correo']) ?></td>
            <td><?= e($r['departamento']) ?></td>
            <td>
              <?php if ((int)$r['activo'] === 1): ?>
                <span class="badge">Activo</span>
              <?php else: ?>
                <span class="badge">Inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($asignado): ?>
                <span class="ok">Asignado</span> — <?= e($r['disp_ip'] ?: '') ?>
                <?php if (!empty($r['disp_nombre'])): ?>
                  <span class="muted"> (<?= e($r['disp_nombre']) ?>)</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="warn">Sin asignar</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (is_operador()): ?>
                <?php if ($asignado): ?>
                  <a class="btn small" href="/gestion_ipmac/codigo/php/dispositivo_editar.php?id=<?= (int)$r['disp_id'] ?>">Ver dispositivo</a>
                <?php else: ?>
                  <a class="btn small" href="/gestion_ipmac/codigo/php/usuario_red_asignar.php?usuario_red_id=<?= (int)$r['id'] ?>">Asignar</a>
                <?php endif; ?>
                <a class="btn small" href="/gestion_ipmac/codigo/php/usuario_red_editar.php?id=<?= (int)$r['id'] ?>">Editar</a>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <p class="muted">* La asignación vigente considera registros en <code>usuario_red_dispositivo</code> con <code>asignado_hasta</code> nulo o a futuro.</p>
  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>
</body>
</html>