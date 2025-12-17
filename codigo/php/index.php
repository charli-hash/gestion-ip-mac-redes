<?php
require_once __DIR__.'/funciones.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? 'admin@local';
  $pass  = $_POST['password'] ?? 'admin';
  if (login($email, $pass)) {
    header('Location: /gestion_ipmac/codigo/php/dashboard.php');
    exit;
  } else {
    $error = 'Credenciales inválidas';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Login — Gestión IP/MAC</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=4">

  <style>
    .login-input {
        width: 100%;
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid rgba(148,163,184,0.35);
        background: rgba(15,23,42,0.7);
        color: #e5e7eb;
        font-size: 14px;
        margin-top: 4px;
    }
    .login-input::placeholder {
        color: #6b7280;
    }
    .login-label {
        font-size: 13px;
        color: #9ca3af;
        margin-top: 10px;
        display: block;
    }
    .login-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 14px;
        flex-wrap: wrap;
    }
    .alert {
        margin-top: 10px;
        padding: 8px 10px;
        border-radius: 10px;
        font-size: 13px;
        background: rgba(248, 113, 113, 0.14);
        border: 1px solid rgba(248, 113, 113, 0.6);
        color: #fecaca;
        text-align: left;
    }
  </style>
</head>
<body>

  <!-- OPCIONAL: navbar mínima solo con el título -->
  <div class="navbar">
    <div class="brand">Gestión IP/MAC — Acceso</div>
  </div>

  <div class="page-center-wrapper">
    <form class="page-card" method="post">
      <h2>Ingresar al sistema</h2>
      <p class="muted">Gestión IP/MAC </p>

      <hr style="border:none; border-bottom:1px solid rgba(55,65,81,0.8); margin:12px 0 14px;">

      <?php if ($error): ?>
        <div class="alert" data-flash><?= e($error) ?></div>
      <?php endif; ?>

      <label class="login-label">Usuario / Correo</label>
      <input
        class="login-input"
        type="text"
        name="email"
        placeholder="admin@local"
        required
      >

      <label class="login-label">Contraseña</label>
      <input
        class="login-input"
        type="password"
        name="password"
        placeholder="admin"
        required
      >

      <div class="login-row">
        <button class="btn primary" type="submit">Iniciar sesión</button>
        <span class="muted"><b></b></span>
      </div>
    </form>
  </div>

  <div class="footer">© 2025 — Sistema de Gestión IP/MAC</div>

  <script src="/gestion_ipmac/codigo/js/funciones.js?v=4"></script>
</body>
</html>