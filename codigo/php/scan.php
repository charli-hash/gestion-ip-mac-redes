<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();
require_role_min(2); // OPERADOR (2) o ADMIN (3)

/* ===============================
   0) Funciones auxiliares
   =============================== */
function maskToCidr(string $mask): ?int {
    $long = ip2long($mask);
    if ($long === false) return null;
    $bin = str_pad(decbin($long), 32, '0', STR_PAD_LEFT);
    return substr_count($bin, '1');
}

function cidrContains(string $cidr, string $ip): bool {
    if (!str_contains($cidr, '/')) return false;
    [$net, $prefix] = explode('/', $cidr, 2);
    $ipl = ip2long($ip);
    $netl = ip2long($net);
    $prefix = (int)$prefix;
    $mask = -1 << (32 - $prefix);
    $mask = $mask & 0xFFFFFFFF;
    return ($ipl & $mask) === ($netl & $mask);
}

function detectWinInterface(): ?array {
    $out = @shell_exec('ipconfig');
    if (!$out) return null;

    $blocks = preg_split('/\r?\n\r?\n/', $out);
    foreach ($blocks as $b) {
        if (stripos($b, 'vEthernet') !== false) continue;
        if (stripos($b, 'VirtualBox') !== false) continue;
        if (stripos($b, 'Loopback') !== false) continue;
        if (stripos($b, 'Tunne') !== false) continue;
        if (stripos($b, 'Bluetooth') !== false) continue;

        $ip = $mask = $gw = null;

        if (preg_match('/Direcci[oó]n IPv4[^:]*:\s*([\d\.]+)/i', $b, $m) ||
            preg_match('/IPv4 Address[^:]*:\s*([\d\.]+)/i', $b, $m)) {
            $ip = $m[1];
        }

        if (preg_match('/M[aá]scara de subred[^:]*:\s*([\d\.]+)/i', $b, $m) ||
            preg_match('/Subnet Mask[^:]*:\s*([\d\.]+)/i', $b, $m)) {
            $mask = $m[1];
        }

        if (preg_match('/Puerta de enlace predeterminada[^:]*:\s*([\d\.]+)/i', $b, $m) ||
            preg_match('/Default Gateway[^:]*:\s*([\d\.]+)/i', $b, $m)) {
            if (preg_match('/\b(\d{1,3}(?:\.\d{1,3}){3})\b/', $m[0], $g)) $gw = $g[1];
        }

        if ($ip && $mask && preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $ip)) {
            return ['ip'=>$ip,'mask'=>$mask,'gw'=>$gw];
        }
    }
    return null;
}

function deriveCidr(string $ip, string $mask): ?string {
    $ipl = ip2long($ip); $ml = ip2long($mask);
    if ($ipl === false || $ml === false) return null;
    $netl = $ipl & $ml;
    $cidr = maskToCidr($mask);
    return $cidr ? long2ip($netl)."/".$cidr : null;
}

/* ===============================
   1) Configuración y autodetección
   =============================== */
$cfg = [];
if ($res = $conn->query("SELECT clave, valor FROM config")) {
    while ($r = $res->fetch_assoc()) $cfg[$r['clave']] = $r['valor'];
}

$activeRedPrev  = isset($cfg['active_red_id']) ? (int)$cfg['active_red_id'] : null;
$activeCidrPrev = trim($cfg['active_cidr'] ?? '');

$nmapRange = trim($cfg['nmap_range'] ?? '192.168.0.0/24');
$nmapPath  = $cfg['nmap_path'] ?? 'C:\\Program Files (x86)\\Nmap\\nmap.exe';

$iface   = detectWinInterface();
$autoMsg = null;
$red_id  = 1; // por defecto

if ($iface && isset($iface['ip'], $iface['mask'])) {
    $autoCidr = deriveCidr($iface['ip'], $iface['mask']);
    if ($autoCidr) {
        // Buscar red en tabla red
        $stmtRed = $conn->prepare("SELECT id, cidr FROM red");
        $stmtRed->execute();
        $resRed = $stmtRed->get_result();

        while ($r = $resRed->fetch_assoc()) {
            if (cidrContains($r['cidr'], $iface['ip'])) {
                $red_id    = (int)$r['id'];
                $nmapRange = $r['cidr'];
                break;
            }
        }
        $stmtRed->close();

        // Si no coincide con config, actualizar
        if (!cidrContains($cfg['nmap_range'] ?? '', $iface['ip'])) {
            $stmt = $conn->prepare("UPDATE config SET valor = ? WHERE clave = 'nmap_range'");
            $stmt->bind_param("s", $nmapRange);
            $stmt->execute();
            $stmt->close();
            $autoMsg = "Red detectada: {$nmapRange} (IP local {$iface['ip']} / máscara {$iface['mask']})";
        }
    }
}

/* ===============================
   1.5) Guardar red activa + detectar cambio
   =============================== */
$activeRedNow  = (int)$red_id;
$activeCidrNow = trim($nmapRange);

$networkChanged = false;
if ($activeRedPrev !== null && $activeRedPrev !== $activeRedNow) $networkChanged = true;
if ($activeCidrPrev !== '' && $activeCidrPrev !== $activeCidrNow) $networkChanged = true;

// Guardar active_red_id
$stmt = $conn->prepare("
    INSERT INTO config (clave, valor) VALUES ('active_red_id', ?)
    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
");
$v1 = (string)$activeRedNow;
$stmt->bind_param("s", $v1);
$stmt->execute();
$stmt->close();

// Guardar active_cidr
$stmt = $conn->prepare("
    INSERT INTO config (clave, valor) VALUES ('active_cidr', ?)
    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
");
$v2 = $activeCidrNow;
$stmt->bind_param("s", $v2);
$stmt->execute();
$stmt->close();

if ($networkChanged) {
    // Solo aviso (NO borra nada automático)
    $_SESSION['flash_warn'] = "⚠ Se detectó un posible cambio de red ({$activeCidrPrev} → {$activeCidrNow}). Si es otra red, usa Reset (limpiar red) para no mezclar datos.";
}

/* ===============================
   2) Validar Nmap
   =============================== */
if (!file_exists($nmapPath)) {
    http_response_code(500);
    echo "<h3>Error: Nmap no encontrado en ".htmlspecialchars($nmapPath, ENT_QUOTES, 'UTF-8')."</h3>";
    exit;
}

/* ===============================
   3) Directorio XML
   =============================== */
$xmlDir = __DIR__ . '/../scans/';
if (!is_dir($xmlDir)) @mkdir($xmlDir, 0777, true);
$xmlFile = $xmlDir . 'scan_' . date('Ymd_His') . '.xml';

/* ===============================
   4) Ejecutar Nmap
   =============================== */
$cmd = sprintf('"%s" -sn -PR --host-timeout 5s -oX "%s" %s',
    $nmapPath, $xmlFile, escapeshellarg($nmapRange)
);

exec($cmd . ' 2>&1', $out, $code);

if ($code !== 0 || !file_exists($xmlFile)) {
    http_response_code(500);
    echo "<h3>Error al ejecutar Nmap</h3><pre>".htmlspecialchars(implode("\n", $out), ENT_QUOTES, 'UTF-8')."</pre>";
    exit;
}

/* ===============================
   5) Procesar XML y actualizar DB
   ✅ FIX: reactivar eliminado = 0 al detectar nuevamente
   =============================== */
$xml = simplexml_load_file($xmlFile);
if (!$xml) die("No se pudo leer XML.");

$insert = $conn->prepare("
  INSERT INTO dispositivo (
    nombre_pc, ip, mac, mac_clean, mac_prefix, usuario_id, red_id, eliminado, ultima_actualizacion
  )
  VALUES (?,?,?,?,?,?,?, 0, NOW())
  ON DUPLICATE KEY UPDATE
    nombre_pc = VALUES(nombre_pc),
    mac = VALUES(mac),
    mac_clean = VALUES(mac_clean),
    mac_prefix = VALUES(mac_prefix),
    red_id = VALUES(red_id),
    eliminado = 0,
    ultima_actualizacion = NOW()
");

$usuario_id = null;
$contHosts  = 0;

foreach ($xml->host as $h) {
    if ((string)$h->status['state'] !== 'up') continue;

    $ip = $mac = $hostname = null;
    foreach ($h->address as $addr) {
        $type = (string)$addr['addrtype'];
        if ($type === 'ipv4') $ip = (string)$addr['addr'];
        elseif ($type === 'mac') $mac = strtoupper((string)$addr['addr']);
    }

    if (isset($h->hostnames->hostname)) {
        $hostname = (string)$h->hostnames->hostname['name'];
    }
    if (!$ip) continue;

    // Normalizar MAC
    $mac_clean = $mac_prefix = null;
    if ($mac) {
        $mac_clean  = strtoupper(str_replace(':', '', $mac));
        $mac_prefix = substr($mac_clean, 0, 6);
        if (preg_match('/^[0-9A-F]{6}$/', $mac_prefix)) {
            $stmtVendor = $conn->prepare("INSERT IGNORE INTO vendor_oui (prefix, fabricante) VALUES (?, 'Desconocido')");
            $stmtVendor->bind_param("s", $mac_prefix);
            $stmtVendor->execute();
            $stmtVendor->close();
        }
    }

    $insert->bind_param("ssssisi", $hostname, $ip, $mac, $mac_clean, $mac_prefix, $usuario_id, $red_id);
    $insert->execute();
    $contHosts++;
}

$insert->close();

// Flash OK para el dashboard
$_SESSION['flash_ok'] = "✅ Escaneo Nmap OK: {$contHosts} dispositivos agregados/actualizados (red activa ID {$red_id}).";

/* ===============================
   6) Salida HTML
   =============================== */
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
    <div class="nav-actions"><a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a></div>
  </div>

  <div class="page-center-wrapper">
    <div class="page-card">
        <div class="status-icon"><span>✓</span></div>
        <h2>Escaneo completado</h2>

        <?php if (!empty($autoMsg)): ?>
          <p class="hint"><b>Auto-configuración:</b> <?= htmlspecialchars($autoMsg, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <p><b>Red activa:</b> <?= htmlspecialchars($activeCidrNow, ENT_QUOTES, 'UTF-8') ?> (ID <?= (int)$red_id ?>)</p>
        <p><b>Rango escaneado:</b> <?= htmlspecialchars($nmapRange, ENT_QUOTES, 'UTF-8') ?></p>

        <p class="metric"><?= (int)$contHosts ?> <span>dispositivos actualizados</span></p>
        <p class="hint">Archivo XML generado:<br><code><?= htmlspecialchars($xmlFile, ENT_QUOTES, 'UTF-8') ?></code></p>

        <div><a class="btn primary" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a></div>
    </div>
  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>
</body>
</html>
