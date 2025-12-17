<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';

require_login();
require_role_min(2); // Operador (2) o Admin (3)

// ===============================
// 1) Cargar config desde BD
// ===============================
$cfg = [];
$res = $conn->query("SELECT clave, valor FROM config");
while ($res && ($r = $res->fetch_assoc())) {
    $cfg[$r['clave']] = $r['valor'];
}

$logfile     = $cfg['pihole_log'] ?? "C:\\pihole\\var-log-pihole\\pihole.log";
$activeRedId = isset($cfg['active_red_id']) ? (int)$cfg['active_red_id'] : 1;

// Docker config
$dockerExe        = $cfg['docker_exe'] ?? 'docker';
$piholeContainer  = $cfg['pihole_container'] ?? 'pihole';
$piholeLogInside  = $cfg['pihole_log_inside'] ?? '/var/log/pihole/pihole.log';

// ===============================
// 2) Leer últimas N líneas (performance)
//    ✅ Primero intenta Docker (recomendado en Windows)
//    ✅ Si falla, fallback al archivo local
// ===============================
$ULTIMAS_LINEAS = 4000;
$IGNORAR_PIHOLE = true;

$lineas   = false;
$metodo   = 'N/A';
$cmd      = '';
$exitCode = 999;

// ---- 2A) Docker exec tail
$dockerExeQuoted = '"' . str_replace('"', '\"', $dockerExe) . '"';
$cmd = "{$dockerExeQuoted} exec {$piholeContainer} tail -n {$ULTIMAS_LINEAS} {$piholeLogInside}";
$output = [];

@exec($cmd, $output, $exitCode);

if ($exitCode === 0 && is_array($output) && count($output) > 0) {
    $lineas = $output;
    $metodo = 'docker';
} else {
    // ---- 2B) Archivo local
    if ($logfile && file_exists($logfile)) {
        $tmp = @file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($tmp !== false && count($tmp) > 0) {
            $lineas = $tmp;
            $metodo = 'archivo_local';
        }
    }
}

// Si no se pudo leer por ningún método
if ($lineas === false || empty($lineas)) {
    http_response_code(500);
    die(
        "No se pudo leer el log de Pi-hole.<br><br>" .
        "<b>Intento Docker:</b> " . htmlspecialchars($cmd, ENT_QUOTES, 'UTF-8') . "<br>" .
        "<b>ExitCode:</b> " . (int)$exitCode . "<br><br>" .
        "<b>Fallback archivo:</b> " . htmlspecialchars($logfile, ENT_QUOTES, 'UTF-8')
    );
}

// Si por archivo local vino enorme, cortar
if (is_array($lineas) && count($lineas) > $ULTIMAS_LINEAS) {
    $lineas = array_slice($lineas, -$ULTIMAS_LINEAS);
}

// ===============================
// 3) Parseo + agregación
// ===============================
$regex = '/\bquery\[(A|AAAA|HTTPS)\]\s+([^\s]+)\s+from\s+([0-9a-fA-F\.:]+)/';

$agregado = []; // "ip|dominio|tipo" => contador
$totLineasValidas = 0;

foreach ($lineas as $l) {
    if (preg_match($regex, $l, $m)) {
        $tipo    = strtoupper($m[1]);
        $dominio = strtolower($m[2]);
        $ip      = $m[3];

        // Quitar punto final del dominio
        $dominio = rtrim($dominio, '.');

        if ($IGNORAR_PIHOLE && $dominio === 'pi.hole') {
            continue;
        }

        $key = $ip.'|'.$dominio.'|'.$tipo;
        $agregado[$key] = ($agregado[$key] ?? 0) + 1;
        $totLineasValidas++;
    }
}

// Nada que insertar
if (empty($agregado)) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Importación Pi-hole</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4">
  <style>
    body{margin:0;padding:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at top,#1e293b 0,#020617 45%,#000 100%);color:#e5e7eb;min-height:100vh;display:flex;align-items:center;justify-content:center}
    .wrapper{width:100%;max-width:900px;padding:24px}
    .card{background:rgba(15,23,42,.96);border-radius:18px;box-shadow:0 18px 40px rgba(15,23,42,.9);padding:32px 28px;border:1px solid rgba(148,163,184,.25)}
    .status-icon{width:54px;height:54px;border-radius:9999px;background:radial-gradient(circle at 30% 20%,#f97373,#b91c1c);display:flex;align-items:center;justify-content:center;margin-bottom:16px}
    .status-icon span{font-size:30px;color:white}
    .btn-primary{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:9999px;border:none;cursor:pointer;text-decoration:none;font-size:14px;font-weight:600;color:#fff;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 10px 30px rgba(79,70,229,.7)}
    .btn-primary:hover{filter:brightness(1.05)}
    code{background:rgba(148,163,184,.12);padding:2px 6px;border-radius:8px}
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="card">
      <div class="status-icon"><span>!</span></div>
      <h2>No hay consultas nuevas para importar</h2>
      <p class="muted">No se encontraron entradas válidas en las últimas líneas del log (o solo <code>pi.hole</code>).</p>
      <p class="muted">Método: <code><?= htmlspecialchars($metodo, ENT_QUOTES, 'UTF-8') ?></code></p>
      <a class="btn-primary" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a>
    </div>
  </div>
</body>
</html>
<?php
    exit;
}

// ===============================
// 4) UPSERT en bloque (transacción)
// ===============================
$conn->begin_transaction();

$stmt = $conn->prepare("
    INSERT INTO dns_logs (red_id, ip, dominio, tipo, contador, ts)
    VALUES (?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
      contador = contador + VALUES(contador),
      ts       = GREATEST(ts, VALUES(ts))
");
if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    die("Error preparando UPSERT: ".$conn->error);
}

$upserts = 0;
foreach ($agregado as $key => $count) {
    [$ip, $dominio, $tipo] = explode('|', $key, 3);

    $stmt->bind_param("isssi", $activeRedId, $ip, $dominio, $tipo, $count);

    if ($stmt->execute()) {
        $upserts++;
    }
}

$stmt->close();
$conn->commit();

// ===============================
// 5) Flash OK + vista final
// ===============================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['flash_ok'] = "✅ Importación Pi-hole OK: {$upserts} registros agregados/actualizados (Red activa ID {$activeRedId}).";

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Importación Pi-hole</title>
  <style>
    body{margin:0;padding:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at top,#1e293b 0,#020617 45%,#000 100%);color:#e5e7eb;min-height:100vh;display:flex;align-items:center;justify-content:center}
    .wrapper{width:100%;max-width:900px;padding:24px}
    .top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
    .top-bar h1{font-size:22px;font-weight:600;margin:0;color:#f9fafb}
    .badge{font-size:12px;padding:6px 10px;border-radius:9999px;background:rgba(148,163,184,.16);color:#cbd5f5}
    .card{background:rgba(15,23,42,.96);border-radius:18px;box-shadow:0 18px 40px rgba(15,23,42,.9);padding:32px 28px;border:1px solid rgba(148,163,184,.25)}
    .status-icon{width:54px;height:54px;border-radius:9999px;background:radial-gradient(circle at 30% 20%,#4ade80,#16a34a);display:flex;align-items:center;justify-content:center;margin-bottom:16px}
    .status-icon span{font-size:30px;color:white}
    .card h2{margin:0 0 8px;font-size:24px;font-weight:600;color:#e5e7eb}
    .subtitle{margin:0 0 24px;font-size:14px;color:#9ca3af;line-height:1.6}
    .metric{font-size:28px;font-weight:700;color:#f9fafb}
    .metric small{font-size:13px;font-weight:500;color:#9ca3af;margin-left:6px}
    .hint{font-size:13px;margin-top:12px;color:#6b7280;line-height:1.6}
    .actions{margin-top:28px;display:flex;gap:12px;flex-wrap:wrap}
    .btn-primary{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:9999px;border:none;cursor:pointer;text-decoration:none;font-size:14px;font-weight:600;color:#fff;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 10px 30px rgba(79,70,229,.7)}
    .btn-primary:hover{filter:brightness(1.05)}
    .btn-secondary{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9999px;border:1px solid rgba(148,163,184,.5);cursor:pointer;text-decoration:none;font-size:13px;font-weight:500;color:#e5e7eb;background:transparent}
    .btn-secondary:hover{background:rgba(15,23,42,.9)}
    code{background:rgba(148,163,184,.12);padding:2px 6px;border-radius:8px}
  </style>
</head>
<body>
<div class="wrapper">
  <div class="top-bar">
    <h1>Importación desde Pi-hole</h1>
    <div class="badge">Módulo DNS logs</div>
  </div>

  <div class="card">
    <div class="status-icon"><span>✓</span></div>

    <h2>Importación completada</h2>

    <p class="subtitle">
      Se analizaron <b><?= number_format($totLineasValidas) ?></b> líneas válidas y se aplicaron
      <span class="metric"><?= (int)$upserts ?><small>UPSERTs</small></span>.
    </p>

    <p class="hint">
      Red activa ID: <code><?= (int)$activeRedId ?></code><br>
      Método: <code><?= htmlspecialchars($metodo, ENT_QUOTES, 'UTF-8') ?></code><br>
      Docker cmd: <code><?= htmlspecialchars($cmd, ENT_QUOTES, 'UTF-8') ?></code><br>
      Fallback archivo: <code><?= htmlspecialchars($logfile, ENT_QUOTES, 'UTF-8') ?></code>
    </p>

    <div class="actions">
      <a href="/gestion_ipmac/codigo/php/dashboard.php" class="btn-primary">← Volver al Dashboard</a>
      <a href="/gestion_ipmac/codigo/php/dns_logs.php" class="btn-secondary">🔍 Ver consultas importadas</a>
    </div>
  </div>
</div>
</body>
</html>
