<?php
// scan.php — ejecuta Nmap (XML) y guarda IP/MAC/hostname en MySQL
// Mantiene ediciones manuales (nombre_pc y MAC) si Nmap no trae datos

require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();
require_role_min(2); // ✅ solo OPERADOR (2) y ADMIN (3) pueden escanear

/* ===============================
   1) Configuración desde BD
   =============================== */
$cfg = [];
if ($res = $conn->query("SELECT clave, valor FROM config")) {
    while ($r = $res->fetch_assoc()) {
        $cfg[$r['clave']] = $r['valor'];
    }
}

// Rango de escaneo
$nmapRange = trim($cfg['nmap_range'] ?? '192.168.0.0/24');

// Ruta de Nmap (prioriza config; si no, usa ruta típica Windows)
$nmapPath = $cfg['nmap_path'] ?? 'C:\\Program Files (x86)\\Nmap\\nmap.exe';

/* ===============================
   2) Validaciones previas
   =============================== */
if (!is_string($nmapPath) || $nmapPath === '' || !file_exists($nmapPath)) {
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Error: Nmap no encontrado</title>
      <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4">
      <style>.status-icon.error{background:radial-gradient(circle at 30% 20%,#f97373,#b91c1c);}</style>
    </head>
    <body>
      <div class="navbar">
        <div class="brand">Gestión IP/MAC — Escaneo Nmap</div>
        <div class="nav-actions">
          <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a>
        </div>
      </div>
      <div class="page-center-wrapper">
        <div class="page-card">
          <div class="status-icon error"><span>!</span></div>
          <h2>No se encontró Nmap</h2>
          <p>Ruta configurada:</p>
          <code><?= e($nmapPath) ?></code>
          <p class="hint">Corrige la clave <b>nmap_path</b> en la tabla <code>config</code> o instala Nmap en la ruta por defecto.</p>
        </div>
      </div>
      <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>
    </body>
    </html>
    <?php
    exit;
}

/* ===============================
   3) Carpeta para XML
   =============================== */
$xmlDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'scans' . DIRECTORY_SEPARATOR;
if (!is_dir($xmlDir)) {
    @mkdir($xmlDir, 0777, true);
}
$xmlFile = $xmlDir . 'scan_' . date('Ymd_His') . '.xml';

/* ===============================
   4) Ejecutar Nmap (ping scan rápido)
   =============================== */
/*
  -sn           : solo descubrimiento de hosts (sin puertos)
  -PR           : ARP ping (rápido en LAN)
  --host-timeout 5s : timeout por host
  -oX           : salida XML
*/
$cmd = sprintf(
    '"%s" -sn -PR --host-timeout 5s -oX "%s" %s',
    $nmapPath,
    $xmlFile,
    escapeshellarg($nmapRange) // protege el rango
);

exec($cmd . ' 2>&1', $out, $code);

/* ===============================
   4.1 Manejo de error
   =============================== */
if ($code !== 0 || !file_exists($xmlFile)) {
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Error en escaneo Nmap</title>
      <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4">
      <style>.status-icon.error{background:radial-gradient(circle at 30% 20%,#f97373,#b91c1c);}</style>
    </head>
    <body>
      <div class="navbar">
        <div class="brand">Gestión IP/MAC — Escaneo Nmap</div>
        <div class="nav-actions">
          <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a>
        </div>
      </div>

      <div class="page-center-wrapper">
        <div class="page-card">
            <div class="status-icon error"><span>!</span></div>
            <h2>Fallo al ejecutar Nmap</h2>
            <p>Ocurrió un error al intentar escanear el rango configurado.</p>
            <p class="hint">Verifica permisos, ruta de Nmap y que el rango sea válido.</p>

            <p style="text-align:left; font-size:12px; margin-top:18px; max-height:240px; overflow:auto;">
              <strong>Comando ejecutado:</strong><br>
              <code><?= e($cmd) ?></code><br><br>
              <strong>Código de salida:</strong> <?= (int)$code ?><br><br>
              <strong>Salida:</strong><br>
              <pre style="white-space:pre-wrap; font-family:monospace; background:rgba(15,23,42,0.9); padding:8px; border-radius:8px; border:1px solid rgba(148,163,184,0.4);">
<?= e(implode("\n", $out)) ?>
              </pre>
            </p>

            <div style="margin-top:12px;">
              <a class="btn primary" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a>
            </div>
        </div>
      </div>

      <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>
    </body>
    </html>
    <?php
    exit;
}

/* ===============================
   5) Cargar XML
   =============================== */
$xml = @simplexml_load_file($xmlFile);
if (!$xml) {
    die("No se pudo leer el XML generado por Nmap.");
}

/* ===============================
   6) Preparar INSERT/UPDATE
   =============================== */
$insert = $conn->prepare("
  INSERT INTO dispositivo (
      nombre_pc,
      ip,
      mac,
      mac_clean,
      mac_prefix,
      usuario_id,
      red_id,
      ultima_actualizacion
  )
  VALUES (?,?,?,?,?,?,?, NOW())
  ON DUPLICATE KEY UPDATE
    nombre_pc            = VALUES(nombre_pc),
    mac                  = VALUES(mac),
    mac_clean            = VALUES(mac_clean),
    mac_prefix           = VALUES(mac_prefix),
    ultima_actualizacion = NOW()
");

$red_id     = 1;      // por ahora todo a la red 1
$usuario_id = null;   // sin usuario asignado aún
$contHosts  = 0;

/* ===============================
   7) Recorrer hosts del XML
   =============================== */
foreach ($xml->host as $h) {
    if ((string)$h->status['state'] !== 'up') {
        continue;
    }

    $ip       = null;
    $mac      = null;
    $hostname = null;

    // Direcciones
    foreach ($h->address as $addr) {
        $type = (string)$addr['addrtype'];
        if ($type === 'ipv4') {
            $ip = (string)$addr['addr'];
        } elseif ($type === 'mac') {
            $mac = strtoupper((string)$addr['addr']);
        }
    }

    // Hostname (si hay múltiples, toma el primero)
    if (isset($h->hostnames->hostname)) {
        $hn = $h->hostnames->hostname;
        if (is_array($hn) || $hn instanceof Traversable) {
            $hostname = (string)$hn[0]['name'];
        } else {
            $hostname = (string)$hn['name'];
        }
    }

    if (!$ip) {
        continue;
    }

    // Leer existente para preservar manuales si Nmap no trae
    $exNombre = $exMac = $exMacClean = $exMacPrefix = null;

    $stmtSel = $conn->prepare("
        SELECT nombre_pc, mac, mac_clean, mac_prefix
        FROM dispositivo
        WHERE ip = ?
        LIMIT 1
    ");
    $stmtSel->bind_param("s", $ip);
    $stmtSel->execute();
    $stmtSel->bind_result($exNombre, $exMac, $exMacClean, $exMacPrefix);
    $tieneExistente = $stmtSel->fetch();
    $stmtSel->close();

    // Si Nmap NO trae hostname, conservar el existente
    if (!$hostname && $tieneExistente && $exNombre) {
        $hostname = $exNombre;
    }

    // Normalización de MAC
    $mac_clean  = null;
    $mac_prefix = null;

    if ($mac) {
        $mac_clean  = strtoupper(str_replace(':', '', $mac));
        $mac_prefix = substr($mac_clean, 0, 6);

        if (preg_match('/^[0-9A-F]{6}$/', $mac_prefix)) {
            $stmtVendor = $conn->prepare("
                INSERT IGNORE INTO vendor_oui (prefix, fabricante)
                VALUES (?, 'Desconocido')
            ");
            $stmtVendor->bind_param("s", $mac_prefix);
            $stmtVendor->execute();
            $stmtVendor->close();
        }
    } elseif ($tieneExistente) {
        // Nmap no trajo MAC → conservar lo que ya existía
        $mac        = $exMac;
        $mac_clean  = $exMacClean;
        $mac_prefix = $exMacPrefix;
    }

    // Insertar / actualizar
    $insert->bind_param(
        "ssssisi",
        $hostname,   // nombre_pc
        $ip,
        $mac,
        $mac_clean,
        $mac_prefix,
        $usuario_id,
        $red_id
    );
    $insert->execute();
    $contHosts++;
}

/* ===============================
   8) Cierre y salida
   =============================== */
$insert->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo completado</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4">
</head>
<body>

  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Escaneo Nmap</div>
    <div class="nav-actions">
      <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a>
    </div>
  </div>

  <div class="page-center-wrapper">
    <div class="page-card">
        <div class="status-icon"><span>✓</span></div>
        <h2>Escaneo completado</h2>
        <p><b>Rango escaneado:</b> <?= e($nmapRange) ?></p>
        <p class="metric">
          <?= (int)$contHosts ?> <span style="font-size:14px; font-weight:500;">dispositivos actualizados</span>
        </p>
        <p class="hint">
          Archivo XML generado:<br>
          <code><?= e($xmlFile) ?></code>
        </p>

        <div style="margin-top:20px;">
          <a class="btn primary" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a>
        </div>
    </div>
  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>

</body>
</html>