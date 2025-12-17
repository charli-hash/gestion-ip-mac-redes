<?php
// ===========================================================
// FUNCIONES PRINCIPALES DEL SISTEMA DE GESTIÓN IP/MAC
// ===========================================================

// Iniciar sesión si no existe
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar conexión global
require_once __DIR__.'/conexion.php';
global $conn;

/* ===========================================================
   FUNCIONES BÁSICAS
   =========================================================== */

/** Escapar HTML para evitar XSS */
function e($v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/** ¿Usuario logueado? */
function is_logged(): bool {
    return isset($_SESSION['user_id']) || isset($_SESSION['user']);
}

/** Obligar a iniciar sesión */
function require_login(): void {
    if (!is_logged()) {
        header('Location: /gestion_ipmac/codigo/php/index.php');
        exit;
    }
}

/** Usuario actual (array con datos y rol) */
function current_user(): array {
    return [
        'id'         => $_SESSION['user_id']     ?? ($_SESSION['user']['id'] ?? null),
        'nombre'     => $_SESSION['user_nombre'] ?? ($_SESSION['user']['nombre'] ?? null),
        'email'      => $_SESSION['user_email']  ?? ($_SESSION['user']['email'] ?? null),
        'rol_nombre' => $_SESSION['rol_nombre']  ?? ($_SESSION['user']['rol'] ?? 'lector'),
        'rol_nivel'  => $_SESSION['rol_nivel']   ?? ($_SESSION['user']['nivel'] ?? 1),
    ];
}

/* ===========================================================
   CONTROL DE ROLES Y PERMISOS
   =========================================================== */

/**
 * Verificar si el usuario tiene un rol mínimo requerido
 * @param int $nivelMinimo  Nivel mínimo (1 lector, 2 operador, 3 admin)
 * @return bool
 */
function has_role_min(int $nivelMinimo): bool {
    $nivel = $_SESSION['rol_nivel'] ?? ($_SESSION['user']['nivel'] ?? 1);
    return (int)$nivel >= (int)$nivelMinimo;
}

/**
 * Exigir nivel mínimo (redirige si no cumple)
 */
function require_role_min(int $nivelMinimo): void {
    if (!has_role_min($nivelMinimo)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
          <meta charset="UTF-8">
          <title>Acceso denegado</title>
          <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4">
          <style>
            body {
              margin: 0;
              padding: 0;
              font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
              background: radial-gradient(circle at top, #1e293b 0, #020617 45%, #000 100%);
              color: #e5e7eb;
              min-height: 100vh;
              display: flex;
              flex-direction: column;
            }
            .navbar {
              display: flex;
              justify-content: space-between;
              align-items: center;
              padding: 12px 24px;
              background: rgba(15, 23, 42, 0.96);
              border-bottom: 1px solid rgba(148, 163, 184, 0.3);
            }
            .brand { font-weight: 600; font-size: 15px; color: #e5e7eb; }
            .center {
              flex: 1;
              display: flex;
              align-items: center;
              justify-content: center;
              padding: 24px;
            }
            .card {
              background: rgba(15, 23, 42, 0.96);
              border-radius: 18px;
              padding: 34px 28px;
              border: 1px solid rgba(148, 163, 184, 0.35);
              box-shadow: 0 18px 40px rgba(15, 23, 42, 0.9);
              max-width: 520px;
              text-align: center;
            }
            h1 {
              margin: 0 0 8px;
              font-size: 22px;
              font-weight: 700;
              color: #f87171;
            }
            p {
              margin: 0 0 18px;
              font-size: 14px;
              color: #9ca3af;
            }
            .btn {
              display: inline-flex;
              align-items: center;
              justify-content: center;
              gap: 8px;
              padding: 10px 18px;
              border-radius: 9999px;
              border: none;
              cursor: pointer;
              text-decoration: none;
              font-size: 14px;
              font-weight: 600;
              color: #fff;
              background: linear-gradient(135deg, #6366f1, #8b5cf6);
              box-shadow: 0 10px 30px rgba(79, 70, 229, 0.7);
            }
            .btn:hover { filter: brightness(1.06); }
            .footer {
              text-align: center;
              font-size: 11px;
              padding: 12px 0 16px;
              color: #6b7280;
            }
          </style>
        </head>
        <body>
          <div class="navbar">
            <div class="brand">Gestión IP/MAC — Acceso</div>
          </div>

          <div class="center">
            <div class="card">
              <h1>Acceso denegado</h1>
              <p>No tienes permisos suficientes para ver esta sección.</p>
              <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">← Volver al Dashboard</a>
            </div>
          </div>

          <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>
        </body>
        </html>
        <?php
        exit;
    }
}

/* Atajos */
function is_admin(): bool { return has_role_min(3); }
function is_operador(): bool { return has_role_min(2); }
function require_operador(): void { require_role_min(2); }
function require_admin(): void { require_role_min(3); }

/* ===========================================================
   LOGIN / LOGOUT
   =========================================================== */

/** Autenticación con base de datos y verificación de rol */
function login(string $email, string $password): bool {
    global $conn;

    if (!$conn) return false;

    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.nombre,
            u.email,
            u.pass_hash,
            u.rol_id,
            r.nombre AS rol_nombre,
            r.nivel  AS rol_nivel
        FROM usuario u
        LEFT JOIN rol r ON u.rol_id = r.id
        WHERE u.email = ?
        LIMIT 1
    ");

    if (!$stmt) return false;

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $u   = $res->fetch_assoc();
    $stmt->close();

    if ($u && password_verify($password, $u['pass_hash'])) {
        $_SESSION['user_id']      = (int)$u['id'];
        $_SESSION['user_nombre']  = $u['nombre'];
        $_SESSION['user_email']   = $u['email'];
        $_SESSION['rol_nombre']   = $u['rol_nombre'] ?? 'lector';
        $_SESSION['rol_nivel']    = (int)($u['rol_nivel'] ?? 1);

        $_SESSION['user'] = [
            'id'     => (int)$u['id'],
            'email'  => $u['email'],
            'nombre' => $u['nombre'],
            'rol'    => $_SESSION['rol_nombre'],
            'nivel'  => $_SESSION['rol_nivel'],
        ];
        return true;
    }

    return false;
}

/** Cerrar sesión */
function logout(): void {
    $_SESSION = [];
    if (session_id() !== '' || isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}
