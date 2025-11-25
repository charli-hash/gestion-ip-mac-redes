<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();
require_role_min(2); // Operador (2) o Admin (3)

// ===============================
// 1) Cargar ruta del log desde config
// ===============================
$cfg = [];
$res = $conn->query("SELECT clave, valor FROM config");
while ($r = $res->fetch_assoc()) {
    $cfg[$r['clave']] = $r['valor'];
}
$logfile = $cfg['pihole_log'] ?? null;

if (!$logfile || !file_exists($logfile)) {
    http_response_code(500);
    die("No se encontró el archivo de log de Pi-hole: ".htmlspecialchars($logfile ?? '(sin ruta)', ENT_QUOTES, 'UTF-8'));
}

// ===============================
// 2) Leer últimas N líneas (performance)
// ===============================
$ULTIMAS_LINEAS = 4000;   // puedes ajustar
$IGNORAR_PIHOLE = true;   // evita contaminar con pi.hole

$lineas = @file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lineas === false) {
    http_response_code(500);
    die("No se pudo leer el archivo de log.");
}
if (count($lineas) > $ULTIMAS_LINEAS) {
    $lineas = array_slice($lineas, -$ULTIMAS_LINEAS);
}

// ===============================
// 3) Parseo + agregación en memoria
//    Ejemplo: "Nov 18 00:28:49 dnsmasq[65]: query[A] example.com from 172.18.0.1"
// ===============================
$regex = '/\bquery\[(A|AAAA|HTTPS)\]\s+([^\s]+)\s+from\s+([0-9a-fA-F\.:]+)/';

$agregado = []; // clave: "ip|dominio|tipo" => contador
$totLineasValidas = 0;

foreach ($lineas as $l) {
    if (preg_match($regex, $l, $m)) {
        $tipo    = strtoupper($m[1]);
        $dominio = strtolower($m[2]);
        $ip      = $m[3];

        // Normalizar dominio: quitar punto final (a veces dnsmasq lo deja con ".")
        if (substr($dominio, -1) === '.') {
            $dominio = rtrim($dominio, '.');
        }

        if ($IGNORAR_PIHOLE && $dominio === 'pi.hole') {
            continue;
        }

        $key = $ip.'|'.$dominio.'|'.$tipo;
        if (!isset($agregado[$key])) {
            $agregado[$key] = 0;
        }
        $agregado[$key]++;
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
      </style>
    </head>
    <body>
      <div class="wrapper">
        <div class="card">
          <div class="status-icon"><span>!</span></div>
          <h2>No hay consultas nuevas para importar</h2>
          <p class="muted">Se analizaron las últimas líneas del log, pero no se encontraron entradas válidas (o solo <code>pi.hole</code>).</p>
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
//    Requiere el índice único (ip, dominio, tipo, dia)
//    con 'dia' como columna generada: dia = DATE(ts)
// ===============================
$conn->begin_transaction();

// ⚠️ INSERT con ON DUPLICATE KEY UPDATE para sumar contador
$stmt = $conn->prepare("
    INSERT INTO dns_logs (ip, dominio, tipo, contador, ts)
    VALUES (?, ?, ?, ?, NOW())
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
    $stmt->bind_param("sssi", $ip, $dominio, $tipo, $count);
    if ($stmt->execute()) {
        $upserts++;
    }
}

$stmt->close();
$conn->commit();
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
        .subtitle{margin:0 0 24px;font-size:14px;color:#9ca3af}
        .metric{font-size:28px;font-weight:700;color:#f9fafb}
        .metric small{font-size:13px;font-weight:500;color:#9ca3af;margin-left:6px}
        .hint{font-size:13px;margin-top:12px;color:#6b7280}
        .actions{margin-top:28px;display:flex;gap:12px;flex-wrap:wrap}
        .btn-primary{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:9999px;border:none;cursor:pointer;text-decoration:none;font-size:14px;font-weight:600;color:#fff;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 10px 30px rgba(79,70,229,.7)}
        .btn-primary:hover{filter:brightness(1.05)}
        .btn-secondary{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9999px;border:1px solid rgba(148,163,184,.5);cursor:pointer;text-decoration:none;font-size:13px;font-weight:500;color:#e5e7eb;background:transparent}
        .btn-secondary:hover{background:rgba(15,23,42,.9)}
        .btn-secondary span{font-size:15px}
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
            Se analizaron <b><?= number_format($totLineasValidas) ?></b> líneas válidas del log y se aplicaron
            <span class="metric"><?= (int)$upserts ?><small>UPSERTs (ip, dominio, tipo)</small></span>.
        </p>

        <p class="hint">
            Usando log: <code><?= htmlspecialchars($logfile, ENT_QUOTES, 'UTF-8') ?></code><br>
            Gracias al índice único <code>(ip, dominio, tipo, dia)</code> no se generan duplicados por día.
        </p>

        <div class="actions">
            <a href="/gestion_ipmac/codigo/php/dashboard.php" class="btn-primary">← Volver al Dashboard</a>
            <a href="/gestion_ipmac/codigo/php/dns_logs.php" class="btn-secondary"><span>🔍</span> Ver consultas importadas</a>
        </div>
    </div>
</div>
</body>
</html>