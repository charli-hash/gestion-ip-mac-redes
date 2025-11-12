<?php
require_once __DIR__.'/funciones.php';
require_login();

/* ===============================
   Datos DEMO (sin base de datos)
   =============================== */
$dispositivos = [
  ['ip'=>'192.168.0.10','mac'=>'F0:D1:A9:AA:BB:01','nombre_pc'=>'PC-LAB-01','usuario'=>'Admin','fabricante'=>'Samsung','estado'=>'ACTIVO','etiqueta'=>'Laboratorio','ultima'=>'2025-11-01 09:10'],
  ['ip'=>'192.168.0.11','mac'=>'3C:5A:37:AA:BB:02','nombre_pc'=>'PC-LAB-02','usuario'=>'—','fabricante'=>'Apple','estado'=>'ACTIVO','etiqueta'=>'Laboratorio','ultima'=>'2025-11-01 09:12'],
  ['ip'=>'192.168.0.20','mac'=>'B8:27:EB:AA:BB:03','nombre_pc'=>'RPI-LAB','usuario'=>'IoT','fabricante'=>'Raspberry Pi','estado'=>'INACTIVO','etiqueta'=>'Sensores','ultima'=>'2025-10-31 18:05'],
];

$logs = [
  ['ip'=>'192.168.0.10','dominio'=>'example.com','ts'=>'2025-11-01 09:12','fuente'=>'pihole'],
  ['ip'=>'192.168.0.11','dominio'=>'aiep.cl','ts'=>'2025-11-01 09:15','fuente'=>'pihole'],
  ['ip'=>'192.168.0.10','dominio'=>'docs.google.com','ts'=>'2025-11-01 09:18','fuente'=>'pihole'],
];

$activos    = array_sum(array_map(fn($d)=> $d['estado']==='ACTIVO'?1:0, $dispositivos));
$inactivos  = count($dispositivos) - $activos;
$duplicadas = 0; // demo
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Dashboard — Gestión IP/MAC (Demo)</title>
  <!-- CSS ABSOLUTO -->
  <link rel="stylesheet" href="/gestion_ipmac/codigo/css/estilos.css?v=3"/>
</head>
<body>
  <!-- Barra superior -->
  <div class="navbar">
    <div class="brand"><span class="logo"></span> Gestión IP/MAC — Dashboard</div>
    <div class="nav-actions">
      <a class="btn" href="/gestion_ipmac/codigo/php/dashboard.php">Inicio</a>
      <a class="btn" href="/gestion_ipmac/codigo/php/logout.php">Salir</a>
    </div>
  </div>

  <!-- Contenido -->
  <div class="container">
    <h2 class="section-title">Resumen</h2>
    <div class="grid cards">
      <div class="card">
        <h3>Dispositivos activos</h3>
        <div class="metric"><?= $activos ?></div>
        <div class="muted">en línea</div>
      </div>
      <div class="card">
        <h3>Dispositivos inactivos</h3>
        <div class="metric"><?= $inactivos ?></div>
        <div class="muted">última actualización</div>
      </div>
      <div class="card">
        <h3>Total dispositivos</h3>
        <div class="metric"><?= count($dispositivos) ?></div>
        <div class="muted">inventario</div>
      </div>
      <div class="card">
        <h3>IP duplicadas</h3>
        <div class="metric"><?= $duplicadas ?></div>
        <div class="muted">posibles conflictos</div>
      </div>
    </div>

    <h3 class="section-title" style="margin-top:16px">Inventario reciente</h3>
    <table class="table">
      <thead>
        <tr>
          <th>IP</th><th>MAC</th><th>Equipo</th><th>Usuario</th>
          <th>Fabricante</th><th>Estado</th><th>Etiqueta</th><th>Actualizado</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dispositivos as $d): ?>
          <tr>
            <td><?= e($d['ip']) ?></td>
            <td><?= e($d['mac']) ?></td>
            <td><?= e($d['nombre_pc']) ?></td>
            <td><?= e($d['usuario']) ?></td>
            <td><?= e($d['fabricante']) ?></td>
            <td class="<?= $d['estado']==='ACTIVO' ? 'status ok' : 'status bad' ?>">
              <?= e($d['estado']) ?>
            </td>
            <td><span class="tag"><?= e($d['etiqueta']) ?></span></td>
            <td><?= e($d['ultima']) ?></td> <!-- ✅ línea correcta -->
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h3 class="section-title">Logs DNS (demo)</h3>
    <table class="table">
      <thead>
        <tr><th>IP</th><th>Dominio</th><th>Fecha</th><th>Fuente</th></tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $r): ?>
          <tr>
            <td><?= e($r['ip']) ?></td>
            <td><?= e($r['dominio']) ?></td>
            <td><?= e($r['ts']) ?></td>
            <td><span class="tag"><?= e($r['fuente']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="footer">© 2025 — Demo (Login + Dashboard)</div>

  <!-- JS ABSOLUTO (asegúrate de que el archivo esté en /codigo/js/funciones.js y NO dentro de una carpeta llamada igual) -->
  <script src="/gestion_ipmac/codigo/js/funciones.js?v=3"></script>
</body>
</html>
