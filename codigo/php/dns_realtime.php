<?php
// dns_realtime.php (compacto)
// Se incluye desde dashboard.php (ya tienes $conn, $cfg, esc())

if (!isset($conn)) {
    echo "<div class='muted'>❌ Error: no hay conexión a BD (\$conn).</div>";
    return;
}

// Fallback config si dashboard.php no cargó $cfg
if (!isset($cfg) || !is_array($cfg) || empty($cfg)) {
    $cfg = [];
    if ($res = $conn->query("SELECT clave, valor FROM config")) {
        while ($r = $res->fetch_assoc()) {
            $cfg[$r['clave']] = $r['valor'];
        }
    }
}

if (!function_exists('esc')) {
    function esc($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// Config
$dockerExe       = trim($cfg['docker_exe'] ?? 'docker');
$piholeContainer = trim($cfg['pihole_container'] ?? 'pihole');
$logInside       = trim($cfg['pihole_log_inside'] ?? '/var/log/pihole/pihole.log');
$logLocal        = trim($cfg['pihole_log'] ?? ''); // fallback local

// Ajustes compactos
$TAIL_LINES = 400;   // lee más para encontrar queries reales
$SHOW_LAST  = 10;    // ✅ menos filas
$IGNORE_PIHOLE_DOMAIN = true;

// --- 1) Leer log: Docker tail primero ---
$lines = [];
$method = 'docker';
$cmd = null;
$exitCode = 1;

$dockerQuoted = '"' . str_replace('"', '', $dockerExe) . '"';
$cmd = $dockerQuoted
    . " exec " . escapeshellarg($piholeContainer)
    . " tail -n " . (int)$TAIL_LINES . " " . escapeshellarg($logInside);

$output = [];
@exec($cmd, $output, $exitCode);

if ($exitCode === 0 && !empty($output)) {
    $lines = $output;
} else {
    // --- 2) Fallback: archivo local ---
    $method = 'archivo_local';
    if ($logLocal !== '' && file_exists($logLocal)) {
        $tmp = @file($logLocal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($tmp)) {
            if (count($tmp) > $TAIL_LINES) $tmp = array_slice($tmp, -$TAIL_LINES);
            $lines = $tmp;
        }
    }
}

if (empty($lines)) {
    echo "<div class='toast-err'>❌ No se pudo leer el log para tiempo real.<br>";
    echo "<div class='muted' style='margin-top:6px;'>";
    echo "Método: <b>" . esc($method) . "</b><br>";
    echo "CMD: <code>" . esc((string)$cmd) . "</code><br>";
    echo "exitCode: <b>" . (int)$exitCode . "</b><br>";
    echo "Fallback local: <code>" . esc($logLocal) . "</code>";
    echo "</div></div>";
    return;
}

// --- 3) Parsear queries (extrae SOLO HH:MM:SS) ---
// Ejemplo: Dec 17 02:10:53 dnsmasq[197]: query[A] google.com from 127.0.0.1
$regex = '/^[A-Z][a-z]{2}\s+\d+\s+(\d{2}:\d{2}:\d{2}).*?\bquery\[(A|AAAA|HTTPS)\]\s+([^\s]+)\s+from\s+([0-9a-fA-F\.:]+)/';

$items = [];
foreach ($lines as $l) {
    if (preg_match($regex, $l, $m)) {
        $hora    = $m[1];                 // ✅ solo hora
        $tipo    = strtoupper($m[2]);
        $dominio = rtrim(strtolower($m[3]), '.');
        $ip      = $m[4];

        if ($IGNORE_PIHOLE_DOMAIN && $dominio === 'pi.hole') {
            continue;
        }

        $items[] = [
            'hora' => $hora,
            'ip' => $ip,
            'dominio' => $dominio,
            'tipo' => $tipo
        ];
    }
}

// últimos N (sin inflar pantalla)
if (count($items) > $SHOW_LAST) {
    $items = array_slice($items, -$SHOW_LAST);
}

if (empty($items)) {
    echo "<div class='toast-err'>⚠ No hay consultas nuevas válidas para mostrar en tiempo real.";
    echo "<div class='muted' style='margin-top:6px;'>";
    echo "Método: <b>" . esc($method) . "</b> · Tip: si ves solo <code>pi.hole</code>, el PC aún no genera tráfico DNS útil.";
    echo "</div></div>";
    return;
}

// --- 4) Render compacto ---
?>
<style>
  .rt-wrap{margin-top:10px}
  .rt-card{
    background:rgba(15, 23, 42, 0.96);
    border-radius:16px;
    padding:12px;
    border:1px solid rgba(148,163,184,0.28);
    box-shadow:0 12px 26px rgba(15,23,42,0.75)
  }
  .rt-head{
    display:flex;justify-content:space-between;align-items:center;
    gap:10px;margin-bottom:8px;flex-wrap:wrap
  }
  .rt-meta{font-size:12px;color:#9ca3af}
  .rt-badge{font-size:11px;padding:5px 9px;border-radius:9999px;background:rgba(148,163,184,.14);color:#cbd5f5}

  /* ✅ caja fija con scroll */
  .rt-box{
    max-height:260px;
    overflow:auto;
    border-radius:12px;
    border:1px solid rgba(148,163,184,0.16);
  }

  /* ✅ tabla compacta */
  .rt-table{width:100%;border-collapse:collapse;font-size:13px}
  .rt-table thead th{
    position:sticky; top:0;
    background:rgba(2,6,23,.96);
    color:#cbd5e1;
    text-align:left;
    padding:8px 10px;
    border-bottom:1px solid rgba(148,163,184,0.18);
    z-index:2;
  }
  .rt-table tbody td{
    padding:7px 10px;
    border-bottom:1px solid rgba(148,163,184,0.10);
    color:#e5e7eb;
    vertical-align:middle;
  }
  .rt-table tbody tr:hover{background:rgba(148,163,184,0.06)}

  /* ✅ columnas angostas */
  .rt-col-hora{width:92px;white-space:nowrap}
  .rt-col-ip{width:120px;white-space:nowrap}
  .rt-col-tipo{width:70px;white-space:nowrap}

  /* ✅ dominio con ... */
  .rt-dom{
    max-width:420px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
</style>

<div class="rt-wrap">
  <div class="rt-card">
    <div class="rt-head">
      <div class="rt-meta">
        ✅ Tiempo real: <b>últimas <?= (int)count($items) ?></b> · Método: <b><?= esc($method) ?></b>
      </div>
      <div class="rt-badge">Pi-hole (tail)</div>
    </div>

    <div class="rt-box">
      <table class="rt-table">
        <thead>
          <tr>
            <th class="rt-col-hora">Hora</th>
            <th class="rt-col-ip">IP</th>
            <th>Dominio</th>
            <th class="rt-col-tipo">Tipo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <?php
              $tipo = $it['tipo'];
              $class = 'tag-dns tag-other';
              if ($tipo === 'A') $class = 'tag-dns tag-a';
              elseif ($tipo === 'AAAA') $class = 'tag-dns tag-aaaa';
              elseif ($tipo === 'HTTPS') $class = 'tag-dns tag-https';
            ?>
            <tr>
              <td class="rt-col-hora"><?= esc($it['hora']) ?></td>
              <td class="rt-col-ip"><?= esc($it['ip']) ?></td>
              <td class="rt-dom" title="<?= esc($it['dominio']) ?>"><?= esc($it['dominio']) ?></td>
              <td class="rt-col-tipo"><span class="<?= $class ?>"><?= esc($tipo) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="muted" style="margin-top:8px;font-size:12px;">
      
    </div>
  </div>
</div>
