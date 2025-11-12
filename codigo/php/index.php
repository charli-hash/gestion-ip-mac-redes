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
  <title>Login — Gestión IP/MAC (Demo)</title>
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=3">
</head>
<body>
  <div class="center">
    <form class="login" method="post">
      <h1>Ingresar</h1>
      <div class="muted">Gestión IP/MAC — Wireframe Demo</div>
      <hr class="sep"/>
      <?php if ($error): ?>
        <div class="alert" data-flash><?= e($error) ?></div>
      <?php endif; ?>
      <label>Usuario / Correo</label>
      <input class="input" type="text" name="email" placeholder="admin@local" required/>
      <label style="margin-top:8px">Contraseña</label>
      <input class="input" type="password" name="password" placeholder="admin" required/>
      <div class="row">
        <button class="btn primary" type="submit">Iniciar sesión</button>
        <span class="muted">Demo: <b>admin@local / admin</b></span>
      </div>
    </form>
  </div>
  <div class="footer">© 2025 — Demo (Login + Dashboard)</div>
  <script src="/gestion_ipmac/codigo/js/funciones.js?v=3"></script>


</body>
</html>

