<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/auditoria_helper.php';
require_login();
require_role_min(2);

// --- filtros ---
$fq = [
  'q'         => trim($_GET['q'] ?? ''),
  'modulo'    => trim($_GET['modulo'] ?? ''),
  'sev'       => trim($_GET['sev'] ?? ''),
  'usuario'   => trim($_GET['usuario'] ?? ''),
  'red_id'    => trim($_GET['red_id'] ?? ''),
  'desde'     => trim($_GET['desde'] ?? ''), // YYYY-MM-DD
  'hasta'     => trim($_GET['hasta'] ?? ''),
];
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$off   = ($page-1)*$limit;

$where = [];
$args  = [];
if ($fq['q'] !== '')       { $where[] = '(a.detalle LIKE ? OR JSON_SEARCH(a.detalle_json, "all", ?) IS NOT NULL OR a.accion LIKE ?)'; $args[]="%{$fq['q']}%"; $args[]=$fq['q']; $args[]="%{$fq['q']}%"; }
if ($fq['modulo'] !== '')  { $where[] = 'a.modulo = ?';     $args[]=$fq['modulo']; }
if ($fq['sev'] !== '')     { $where[] = 'a.severidad = ?';  $args[]=$fq['sev']; }
if ($fq['usuario'] !== '') { $where[] = 'u.email = ?';      $args[]=$fq['usuario']; }
if ($fq['red_id'] !== '')  { $where[] = 'a.red_id = ?';     $args[]=$fq['red_id']; }
if ($fq['desde'] !== '')   { $where[] = 'a.ts >= ?';        $args[]=$fq['desde'].' 00:00:00'; }
if ($fq['hasta'] !== '')   { $where[] = 'a.ts <= ?';        $args[]=$fq['hasta'].' 23:59:59'; }
$sqlWhere = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// total
$countSql = "SELECT COUNT(*) c FROM auditoria a LEFT JOIN usuario u ON u.id=a.usuario_id $sqlWhere";
$stmt = $conn->prepare($countSql);
if ($args) $stmt->bind_param(str_repeat('s', count($args)), ...$args);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['c'] ?? 0;

// datos
$listSql = "
 SELECT a.id, a.ts, a.severidad, a.modulo, a.accion, a.detalle, a.detalle_json,
        a.ip_origen, a.user_agent, a.red_id, a.dispositivo_id,
        u.email AS user_email, r.nombre AS red_nombre, d.ip AS disp_ip
 FROM auditoria a
 LEFT JOIN usuario u ON u.id=a.usuario_id
 LEFT JOIN red r ON r.id=a.red_id
 LEFT JOIN dispositivo d ON d.id=a.dispositivo_id
 $sqlWhere
 ORDER BY a.ts DESC
 LIMIT $limit OFFSET $off";
$stmt2 = $conn->prepare($listSql);
if ($args) $stmt2->bind_param(str_repeat('s', count($args)), ...$args);
$stmt2->execute();
$res = $stmt2->get_result();

// para filtros de combos
$usuarios = $conn->query("SELECT DISTINCT email FROM usuario ORDER BY email");
$modulos  = $conn->query("SELECT DISTINCT modulo FROM auditoria WHERE modulo IS NOT NULL ORDER BY modulo");
$redes    = $conn->query("SELECT id, nombre FROM red ORDER BY nombre");

// export CSV (si lo piden)
if (isset($_GET['export']) && $_GET['export']==='csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=auditoria.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ts','usuario','severidad','modulo','accion','detalle','ip_origen','red','dispositivo','detalle_json']);
    // Reejecutar sin LIMIT para exportar todo con mismos filtros
    $expSql = "
     SELECT a.ts, u.email, a.severidad, a.modulo, a.accion, a.detalle, a.ip_origen,
            r.nombre, d.ip, a.detalle_json
     FROM auditoria a
     LEFT JOIN usuario u ON u.id=a.usuario_id
     LEFT JOIN red r ON r.id=a.red_id
     LEFT JOIN dispositivo d ON d.id=a.dispositivo_id
     $sqlWhere
     ORDER BY a.ts DESC";
    $st = $conn->prepare($expSql);
    if ($args) $st->bind_param(str_repeat('s', count($args)), ...$args);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_row()) fputcsv($out, $row);
    fclose($out);
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Auditoría — Gestión IP/MAC</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4"/>
  <style>
    .badge{padding:.2rem .5rem;border-radius:8px;font-size:.85rem}
    .b-info{background:#e6f2ff;color:#084c9e}
    .b-warn{background:#fff4e5;color:#a86500}
    .b-error{background:#ffe6e6;color:#9e0b0f}
    .muted{color:#777}
    details summary{cursor:pointer; user-select:none;}
    .filters .row{display:flex; gap:.5rem; flex-wrap:wrap}
    .filters input,.filters select{padding:.45rem}
    .table td, .table th{vertical-align:top}
    pre.json{white-space:pre-wrap; font-size:.85rem; background:#fafafa; padding:.5rem; border:1px solid #eee; border-radius:6px;}
    .pager{display:flex; gap:.5rem; align-items:center}
  </style>
</head>
<body>
  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Auditoría</div>
    <div class="nav-actions">
      <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">⬅ Volver</a>
    </div>
  </div>

  <div class="container">
    <h2 class="section-title">Auditoría (eventos recientes)</h2>

    <form class="filters" method="get">
      <div class="row">
        <input type="text" name="q" placeholder="Buscar texto..." value="<?=e($fq['q'])?>">
        <select name="modulo">
          <option value="">Todos los módulos</option>
          <?php while($m = $modulos->fetch_assoc()): ?>
            <option value="<?=e($m['modulo'])?>" <?= $fq['modulo']===$m['modulo']?'selected':'' ?>><?=e($m['modulo'])?></option>
          <?php endwhile; ?>
        </select>
        <select name="sev">
          <option value="">Severidad (todas)</option>
          <?php foreach (['info','warn','error'] as $s): ?>
            <option value="<?=$s?>" <?= $fq['sev']===$s?'selected':'' ?>><?=$s?></option>
          <?php endforeach; ?>
        </select>
        <select name="usuario">
          <option value="">Todos los usuarios</option>
          <?php while($u = $usuarios->fetch_assoc()): ?>
            <option value="<?=e($u['email'])?>" <?= $fq['usuario']===$u['email']?'selected':'' ?>><?=e($u['email'])?></option>
          <?php endwhile; ?>
        </select>
        <select name="red_id">
          <option value="">Todas las redes</option>
          <?php while($r = $redes->fetch_assoc()): ?>
            <option value="<?=$r['id']?>" <?= $fq['red_id']==$r['id']?'selected':'' ?>><?=e($r['nombre'])?></option>
          <?php endwhile; ?>
        </select>
        <input type="date" name="desde" value="<?=e($fq['desde'])?>">
        <input type="date" name="hasta" value="<?=e($fq['hasta'])?>">
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn" href="?<?=http_build_query(array_merge($fq,['export'=>'csv']))?>">⬇ Exportar CSV</a>
      </div>
    </form>

    <table class="table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Sev</th>
          <th>Módulo / Acción</th>
          <th>Usuario / IP</th>
          <th>Red / Dispositivo</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows): ?>
          <?php while ($r = $res->fetch_assoc()): ?>
            <tr>
              <td><?=e($r['ts'])?></td>
              <td>
                <?php
                  $cls = $r['severidad']==='error'?'b-error':($r['severidad']==='warn'?'b-warn':'b-info');
                ?>
                <span class="badge <?=$cls?>"><?=e($r['severidad'] ?? 'info')?></span>
              </td>
              <td>
                <strong><?=e($r['modulo'] ?: '(sistema)')?></strong><br/>
                <span class="muted"><?=e($r['accion'])?></span>
              </td>
              <td>
                <?= e($r['user_email'] ?: '(sistema)') ?><br/>
                <span class="muted"><?= e($r['ip_origen'] ?: '—') ?></span>
              </td>
              <td>
                <?= e($r['red_nombre'] ?: '—') ?><br/>
                <?php if ($r['dispositivo_id']): ?>
                  <a href="/gestion_ipmac/codigo/php/dispositivo_ver.php?id=<?=$r['dispositivo_id']?>">Dispositivo #<?= (int)$r['dispositivo_id'] ?> (<?=e($r['disp_ip'])?>)</a>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['detalle'])): ?>
                  <div><?= nl2br(e($r['detalle'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($r['detalle_json'])): ?>
                  <details>
                    <summary>Ver JSON</summary>
                    <pre class="json"><?= e(json_encode(json_decode($r['detalle_json'], true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
                  </details>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" class="muted">Sin eventos registrados con estos filtros.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php
      $pages = max(1, ceil($total / $limit));
      $qs = $fq; unset($qs['page']);
    ?>
    <div class="pager">
      <?php if ($page>1): ?>
        <a class="btn" href="?<?=http_build_query(array_merge($qs,['page'=>$page-1]))?>">◀ Anterior</a>
      <?php endif; ?>
      <span class="muted">Página <?=$page?> / <?=$pages?> (<?=$total?> eventos)</span>
      <?php if ($page<$pages): ?>
        <a class="btn" href="?<?=http_build_query(array_merge($qs,['page'=>$page+1]))?>">Siguiente ▶</a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
