<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();
require_role_min(2);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Auditoría — Gestión IP/MAC</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=3"/>
</head>
<body>
  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Auditoría</div>
    <div class="nav-actions">
      <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">⬅ Volver</a>
    </div>
  </div>

  <div class="container">
    <h2 class="section-title">Eventos recientes</h2>
    <table class="table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Usuario</th>
          <th>Acción</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $res = $conn->query("
          SELECT a.ts, u.email AS user_email, a.accion, a.detalle
          FROM auditoria a
          LEFT JOIN usuario u ON u.id = a.usuario_id
          ORDER BY a.ts DESC
          LIMIT 50
        ");
        if ($res && $res->num_rows > 0) {
          while ($r = $res->fetch_assoc()) {
            echo '<tr>';
            echo '<td>'.e($r['ts']).'</td>';
            echo '<td>'.e($r['user_email'] ?: '(sistema)').'</td>';
            echo '<td>'.e($r['accion']).'</td>';
            echo '<td>'.e($r['detalle']).'</td>';
            echo '</tr>';
          }
        } else {
          echo '<tr><td colspan="4" class="muted">Sin eventos registrados.</td></tr>';
        }
        ?>
      </tbody>
    </table>
  </div>
</body>
</html>