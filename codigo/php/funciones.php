<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function is_logged() { return isset($_SESSION['user']); }

function require_login() {
  if (!is_logged()) {
    header('Location: /gestion_ipmac/codigo/php/index.php');
    exit;
  }
}

function login($email, $password) {
  // DEMO sin BD:
  if ($email === 'admin@local' && $password === 'admin') {
    $_SESSION['user'] = ['email'=>$email, 'rol'=>'Administrador', 'nombre'=>'Admin'];
    return true;
  }
  return false;
}

function logout() { session_destroy(); }

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

