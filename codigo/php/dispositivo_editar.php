<?php
require_once __DIR__.'/funciones.php';
require_once __DIR__.'/conexion.php';
require_login();
require_role_min(2); // ✅ Solo OPERADOR o ADMIN

/* ===============================
   1) Obtener ID y cargar dispositivo
   =============================== */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('ID de dispositivo inválido.');
}

$mensaje = '';
$errores = [];

// Cargar datos actuales del dispositivo
$stmt = $conn->prepare("
    SELECT 
        id,
        ip,
        mac,
        mac_clean,
        mac_prefix,
        nombre_pc,
        usuario_manual,
        fabricante_manual
    FROM dispositivo
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$dispositivo = $res->fetch_assoc();
$stmt->close();

if (!$dispositivo) {
    die('Dispositivo no encontrado.');
}

/* ===============================
   Helpers MAC (validar/normalizar)
   =============================== */
$is_valid_mac = function(string $s): bool {
    $s = trim($s);
    if ($s === '') return true; // permitir vacío (se guarda NULL)
    // Acepta: AA:BB:CC:DD:EE:FF o AA-BB-CC-DD-EE-FF o 12 hex seguidos
    if (preg_match('/^([0-9A-Fa-f]{2}([:\-])){5}([0-9A-Fa-f]{2})$/', $s)) return true;
    if (preg_match('/^[0-9A-Fa-f]{12}$/', str_replace([':', '-'], '', $s))) return true;
    return false;
};

$normalize_mac = function(?string $s): ?string {
    $s = trim($s ?? '');
    if ($s === '') return null;
    $hex = strtoupper(str_replace([':', '-'], '', $s));
    // Formato canónico AA:BB:CC:DD:EE:FF
    return implode(':', str_split($hex, 2));
};

/* ===============================
   2) Procesar envío del formulario (POST)
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre_pc         = trim($_POST['nombre_pc'] ?? '');
    $mac_input_raw     = trim($_POST['mac'] ?? '');
    $usuario_manual    = trim($_POST['usuario_manual'] ?? '');
    $fabricante_manual = trim($_POST['fabricante_manual'] ?? '');

    // Validaciones
    if ($nombre_pc === '') {
        $errores[] = 'El nombre del equipo no puede estar vacío.';
    }
    if (!$is_valid_mac($mac_input_raw)) {
        $errores[] = 'La dirección MAC no es válida. Usa AA:BB:CC:DD:EE:FF, AA-BB-CC-DD-EE-FF o 12 dígitos hex.';
    }

    // Preparar columnas derivadas solo si no hay errores de formato
    $mac        = null;
    $mac_clean  = null;
    $mac_prefix = null;

    if (empty($errores)) {
        $mac = $normalize_mac($mac_input_raw); // null si vacío
        if ($mac !== null) {
            $mac_clean  = strtoupper(str_replace(':', '', $mac)); // 12 hex
            $mac_prefix = substr($mac_clean, 0, 6);

            // ✅ BLOQUEAR MAC DUPLICADA EN OTRO DISPOSITIVO
            $stmtChk = $conn->prepare("
                SELECT id 
                FROM dispositivo 
                WHERE mac_clean = ? AND id <> ?
                LIMIT 1
            ");
            $stmtChk->bind_param("si", $mac_clean, $id);
            $stmtChk->execute();
            $dup = $stmtChk->get_result()->fetch_assoc();
            $stmtChk->close();

            if ($dup) {
                $errores[] = 'La MAC ya existe en otro dispositivo. Debe ser única.';
            }

            // Asegurar vendor_oui si el prefijo es válido
            if (empty($errores) && preg_match('/^[0-9A-F]{6}$/', $mac_prefix)) {
                $stmtV = $conn->prepare("
                    INSERT IGNORE INTO vendor_oui (prefix, fabricante)
                    VALUES (?, 'Desconocido')
                ");
                $stmtV->bind_param("s", $mac_prefix);
                $stmtV->execute();
                $stmtV->close();
            }
        }

        if (empty($errores)) {
            // UPDATE dispositivo (sin tocar tablas de usuario_red)
            $sqlUpdate = "
                UPDATE dispositivo
                SET 
                    nombre_pc         = ?,
                    mac               = ?,
                    mac_clean         = ?,
                    mac_prefix        = ?,
                    usuario_manual    = ?,
                    fabricante_manual = ?
                WHERE id = ?
            ";

            $stmtU = $conn->prepare($sqlUpdate);
            $stmtU->bind_param(
                "ssssssi",
                $nombre_pc,
                $mac,
                $mac_clean,
                $mac_prefix,
                $usuario_manual,
                $fabricante_manual,
                $id
            );

            if ($stmtU->execute()) {
                $mensaje = '✅ Dispositivo actualizado correctamente.';
            } else {
                $errores[] = 'Error al actualizar el dispositivo: '.$conn->error;
            }
            $stmtU->close();

            // Recargar datos actualizados del dispositivo
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    ip,
                    mac,
                    mac_clean,
                    mac_prefix,
                    nombre_pc,
                    usuario_manual,
                    fabricante_manual
                FROM dispositivo
                WHERE id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $dispositivo = $res->fetch_assoc();
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Editar dispositivo — Gestión IP/MAC</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=3"/>
  <style>
    body { background: radial-gradient(circle at top, #1e293b 0, #020617 45%, #000 100%); color:#e5e7eb; }
    .alert.ok { background:#0f172a; border-left:4px solid #22c55e; color:#22c55e; padding:10px; border-radius:6px; margin-bottom:12px; }
    .alert.error { background:#0f172a; border-left:4px solid #f87171; color:#f87171; padding:10px; border-radius:6px; margin-bottom:12px; }
    .input { width:100%; padding:10px; border-radius:10px; border:1px solid rgba(148,163,184,0.35); background:#0b1220; color:#e5e7eb; }
    .input:disabled { background:rgba(30,41,59,0.7); color:#94a3b8; }
    .help { font-size:12px; color:#9ca3af; margin-top:4px; }
    label { display:block; font-size:12px; color:#9ca3af; margin-bottom:4px; }
    .navbar {
      position: sticky; top: 0; z-index: 40; display:flex; justify-content:space-between; align-items:center;
      padding:12px 32px; background:rgba(15, 23, 42, 0.96); border-bottom:1px solid rgba(148, 163, 184, 0.3);
      backdrop-filter: blur(12px);
    }
    .brand { font-weight:600; font-size:15px; color:#e5e7eb; }
    .nav-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .btn { border-radius:9999px; border:1px solid rgba(148,163,184,0.5); background:transparent; padding:6px 14px; font-size:13px; color:#e5e7eb; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .btn:hover { background: rgba(30, 64, 175, 0.4); border-color: rgba(129, 140, 248, 0.9); }
    .btn.primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); border-color:transparent; color:#fff; box-shadow: 0 10px 30px rgba(79, 70, 229, 0.7);}
    .container { max-width:820px; margin:32px auto 40px; padding:0 20px 40px; }
    .section-title { font-size:15px; font-weight:600; color:#e5e7eb; margin:10px 0 12px; }
    .card { background: rgba(15, 23, 42, 0.96); border-radius:18px; padding:18px; border:1px solid rgba(148,163,184,0.35); box-shadow: 0 16px 35px rgba(15, 23, 42, 0.85); }
    .row { display:flex; gap:16px; }
    .sep { border:0; border-top:1px solid rgba(148,163,184,0.25); }
    .footer { text-align:center; font-size:11px; padding:12px 0 16px; color:#6b7280; }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Editar dispositivo</div>
    <div class="nav-actions">
      <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">⬅ Volver al Dashboard</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/activos.php">Ver activos</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/inactivos.php">Ver inactivos</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/logout.php">Salir</a>
    </div>
  </div>

  <div class="container">
    <h2 class="section-title">Editar dispositivo</h2>

    <?php if (!empty($mensaje)): ?>
      <div class="alert ok"><?= e($mensaje) ?></div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
      <div class="alert error">
        <?php foreach ($errores as $err): ?>
          <div>⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" class="card">
      <div class="row" style="flex-wrap:wrap;">
        <div style="flex:1 1 240px;">
          <label>IP (no editable)</label>
          <input class="input" type="text" value="<?= e($dispositivo['ip']) ?>" disabled>
        </div>
        <div style="flex:1 1 240px;">
          <label>Nombre del equipo</label>
          <input class="input" type="text" name="nombre_pc"
                 value="<?= e($dispositivo['nombre_pc']) ?>" required>
        </div>
      </div>

      <div class="row" style="flex-wrap:wrap; margin-top:8px;">
        <div style="flex:1 1 240px;">
          <label>Dirección MAC</label>
          <input class="input" type="text" name="mac"
                 placeholder="AA:BB:CC:DD:EE:FF"
                 value="<?= e($dispositivo['mac']) ?>"
                 pattern="^([0-9A-Fa-f]{2}([:\-])){5}([0-9A-Fa-f]{2})$|^[0-9A-Fa-f]{12}$"
                 title="Usa AA:BB:CC:DD:EE:FF, AA-BB-CC-DD-EE-FF o 12 dígitos hexadecimales">
          <div class="help">Déjala vacía si no tienes la MAC. Si la ingresas, no puede repetirse en otro equipo.</div>
        </div>

        <div style="flex:1 1 240px;">
          <label>Usuario de Red </label>
          <input class="input" type="text" name="usuario_manual"
                 placeholder="Ej: Notebook Encargado, Juan Pérez…"
                 value="<?= e($dispositivo['usuario_manual']) ?>">
          <div class="help"></div>
        </div>
      </div>

      <hr class="sep" style="margin:16px 0;">

      <div class="row" style="flex-wrap:wrap;">
        <div style="flex:1 1 240px;">
          <label>Fabricante (manual)</label>
          <input class="input" type="text" name="fabricante_manual"
                 placeholder="Ej: HP, Dell, Lenovo…"
                 value="<?= e($dispositivo['fabricante_manual']) ?>">
        </div>
      </div>

      <div class="row" style="margin-top:16px; justify-content:flex-end; gap:8px;">
        <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">Cancelar</a>
        <button class="btn primary" type="submit">💾 Guardar cambios</button>
      </div>
    </form>
  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>
  <script src="/gestion_ipmac/codigo/js/funciones.js?v=3"></script>
</body>
</html>