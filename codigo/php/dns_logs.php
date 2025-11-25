<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();

$me = current_user();

/* ===============================
   Parámetros de búsqueda
   =============================== */
$ip      = trim($_GET['ip']      ?? '');
$dominio = trim($_GET['dominio'] ?? '');
$tipo    = strtoupper(trim($_GET['tipo'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 50;
$offset  = ($page - 1) * $limit;

$where = [];
$params = [];
$types  = '';

if ($ip !== '') {
  $where[] = 'ip = ?';
  $params[] = $ip;
  $types   .= 's';
}
if ($dominio !== '') {
  $where[] = 'dominio LIKE ?';
  $params[] = '%'.$dominio.'%';
  $types   .= 's';
}
if ($tipo !== '' && in_array($tipo, ['A','AAAA','HTTPS'], true)) {
  $where[] = 'tipo = ?';
  $params[] = $tipo;
  $types   .= 's';
}

$whereSQL = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* ===============================
   Total de filas para paginación
   =============================== */
$sqlCount = "SELECT COUNT(*) AS c FROM dns_logs $whereSQL";
$stmt = $conn->prepare($sqlCount);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRows = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
$totalPages = max(1, ceil($totalRows / $limit));

/* ===============================
   Datos principales
   =============================== */
$sql = "
  SELECT ip, dominio, tipo, contador, ts
  FROM dns_logs
  $whereSQL
  ORDER BY ts DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
if ($types) {
  $types2  = $types.'ii';
  $params2 = array_merge($params, [$limit, $offset]);
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function tagClass($t) {
  $t = strtoupper($t);
  if ($t==='A') return 'tag-dns tag-a';
  if ($t==='AAAA') return 'tag-dns tag-aaaa';
  if ($t==='HTTPS') return 'tag-dns tag-https';
  return 'tag-dns tag-other';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Consultas DNS — Gestión IP/MAC</title>
<link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4"/>
<style>
body {
  margin: 0; padding: 0;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  background: radial-gradient(circle at top, #1e293b 0, #020617 45%, #000 100%);
  color: #e5e7eb;
  min-height: 100vh;
}
.navbar {
  position: sticky; top: 0; z-index: 40;
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 32px;
  background: rgba(15, 23, 42, 0.96);
  border-bottom: 1px solid rgba(148, 163, 184, 0.3);
  backdrop-filter: blur(12px);
}
.brand {font-weight: 600; font-size: 15px; color: #e5e7eb;}
.nav-actions {display: flex; flex-wrap: wrap; gap: 8px; align-items: center;}
.btn {
  border-radius: 9999px; border: 1px solid rgba(148, 163, 184, 0.5);
  background: transparent; padding: 6px 14px; font-size: 13px;
  color: #e5e7eb; text-decoration: none; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
  transition: all 0.15s ease;
}
.btn:hover {background: rgba(30, 64, 175, 0.4); border-color: rgba(129, 140, 248, 0.9); transform: translateY(-1px);}
.btn.primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  border-color: transparent; color: #fff;
  box-shadow: 0 10px 30px rgba(79,70,229,0.7);
}
.container {max-width:1200px;margin:32px auto 40px;padding:0 20px;}
.section-title {font-size:15px;font-weight:600;color:#e5e7eb;margin:10px 0 12px;}
.filters {display:flex;flex-wrap:wrap;gap:8px;margin:12px 0;}
.filters input,.filters select {
  padding:8px 10px;border-radius:10px;
  border:1px solid rgba(148,163,184,.35);
  background:rgba(15,23,42,.7);color:#e5e7eb;
}
table.table {
  width:100%;border-collapse:collapse;margin-top:8px;
  background:rgba(15,23,42,.98);border-radius:14px;
  overflow:hidden;box-shadow:0 16px 35px rgba(15,23,42,.9);
}
thead {background:rgba(30,64,175,.9);}
th,td {padding:8px 10px;font-size:12px;border-bottom:1px solid rgba(30,64,175,.4);text-align:left;}
tr:hover {background:rgba(30,64,175,.45);}
.tag-dns{padding:2px 8px;border-radius:12px;font-size:.8rem;font-weight:600;display:inline-block}
.tag-a{background:#1f6feb;color:#fff}.tag-aaaa{background:#8250df;color:#fff}.tag-https{background:#2da44e;color:#fff}.tag-other{background:#6e7781;color:#fff}
.pagination{display:flex;gap:8px;justify-content:center;margin-top:14px}
.footer{text-align:center;font-size:11px;padding:12px 0 16px;color:#6b7280;}
</style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
  <div class="brand">Gestión IP/MAC — Consultas DNS</div>
  <div class="nav-actions">
    <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver</a>
    <span class="muted">Rol: <b><?= e($me['rol_nombre']) ?></b></span>
  </div>
</div>

<div class="container">
  <h2 class="section-title">Consultas DNS importadas</h2>

  <form method="get" class="filters">
    <input type="text" name="ip" placeholder="Filtrar IP" value="<?= e($ip) ?>">
    <input type="text" name="dominio" placeholder="Filtrar dominio" value="<?= e($dominio) ?>">
    <select name="tipo">
      <option value="">Tipo</option>
      <option value="A" <?= $tipo==='A'?'selected':'' ?>>A</option>
      <option value="AAAA" <?= $tipo==='AAAA'?'selected':'' ?>>AAAA</option>
      <option value="HTTPS" <?= $tipo==='HTTPS'?'selected':'' ?>>HTTPS</option>
    </select>
    <button type="submit" class="btn primary">Buscar</button>
    <a href="/gestion_ipmac/codigo/php/dns_logs.php" class="btn">Limpiar</a>
  </form>

  <table class="table">
    <thead>
      <tr>
        <th>Fecha/Hora</th><th>IP</th><th>Dominio</th><th>Tipo</th><th>Consultas</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
      <tr><td colspan="5" class="muted">No hay registros en los filtros seleccionados.</td></tr>
      <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['ts']) ?></td>
        <td><?= e($r['ip']) ?></td>
        <td><?= e($r['dominio']) ?></td>
        <td><span class="<?= tagClass($r['tipo']) ?>"><?= e($r['tipo']) ?></span></td>
        <td><?= (int)$r['contador'] ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <div class="pagination">
    <?php if ($page>1): ?>
      <a class="btn" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">← Anterior</a>
    <?php endif; ?>
    <span>Página <?= $page ?> de <?= $totalPages ?></span>
    <?php if ($page<$totalPages): ?>
      <a class="btn" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">Siguiente →</a>
    <?php endif; ?>
  </div>
</div>

<div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>
</body>
</html>