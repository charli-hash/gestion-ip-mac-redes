🧾 Manual de Usuario y Técnico
Sistema de Gestión IP/MAC Integrado con Nmap, Pi-hole y Docker
Autor: Charlie Bailey Moya
Carrera: Programación y Análisis de Sistemas – AIEP Concepción
Docente: Víctor Valderrama Mora
Año: 2025

⚙️ Instalación y Configuración
Requisitos Previos
Herramienta	Descripción	Uso principal
XAMPP	Incluye Apache, PHP y MySQL.	Entorno local para ejecutar la aplicación web y la base de datos.
Docker Desktop	Plataforma de contenedores.	Permite correr Pi-hole y servicios adicionales.
Nmap	Herramienta de escaneo de red.	Detecta IP, MAC y fabricantes de dispositivos conectados.
Pi-hole	Servidor DNS local open source.	Registra y filtra las consultas DNS generadas en la red.
🧱 1️⃣ Instalar XAMPP
Descargar desde https://www.apachefriends.org

Instalar en la ruta por defecto: C:\xampp\

Iniciar Apache y MySQL desde el XAMPP Control Panel

Verificar en el navegador: http://localhost

🐳 2️⃣ Instalar Docker Desktop
Descargar desde https://www.docker.com

Activar Use WSL 2 based engine

Iniciar Docker Desktop y confirmar que esté activo.

🧩 3️⃣ Configurar Pi-hole con Docker
En la carpeta del proyecto C:\xampp\htdocs\gestion_ipmac\ crear docker-compose.yml:

yaml
version: '3.8'
services:
  pihole:
    image: pihole/pihole:latest
    container_name: pihole
    environment:
      WEBPASSWORD: admin
    ports:
      - "8053:80"
      - "53:53/tcp"
      - "53:53/udp"
    restart: unless-stopped
Levantar el servicio:

bash
cd C:\xampp\htdocs\gestion_ipmac\
docker-compose up -d
Acceso a la consola de administración: http://localhost:8053/admin
(Contraseña: admin)

🔍 4️⃣ Instalar Nmap
Descargar desde https://nmap.org/download.html

Instalar en: C:\Program Files (x86)\Nmap\

Probar en CMD:

cmd
nmap -v
💻 5️⃣ Instalar el Sistema de Gestión IP/MAC
Clonar o descargar y mover a C:\xampp\htdocs\:

bash
git clone https://github.com/charli-hash/gestion-ipmac.git
Crear base de datos e importar script:

Abrir phpMyAdmin: http://localhost/phpmyadmin

Crear BD: gestion_ip_mac_v2

Importar: sql/gestion_ip_mac_v2.sql

Configurar conexión en codigo/php/conexion.php:

php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "gestion_ip_mac_v2";
🌐 6️⃣ Acceder al Sistema
URL: http://localhost:8080/gestion_ipmac/codigo/php/index.php

Credenciales iniciales:

Administrador: admin@demo / admin123

Operador: operador@demo / operador123

🧠 7️⃣ Verificar Integración Completa

| Servicio      | Acción                             | Resultado esperado                        |
| ------------- | ---------------------------------- | ----------------------------------------- |
| **Nmap**      | Ejecutar escaneo desde el sistema. | Detección de IP, MAC y fabricantes.       |
| **Pi-hole**   | Monitorear registros DNS.          | Consultas activas en tiempo real.         |
| **Dashboard** | Visualizar métricas.               | KPIs actualizados sin recargar la página. |


👤 Manual del Usuario


| Módulo                  | Descripción                                         | Rol            |
| ----------------------- | --------------------------------------------------- | -------------- |
| **Inicio de sesión**    | Acceso seguro por rol (Administrador / Operador).   | Sistema        |
| **Escaneo Nmap**        | Detecta y registra IP, MAC y fabricantes.           | Sistema        |
| **Integración Pi-hole** | Importa registros DNS asociados a dispositivos.     | Sistema        |
| **Dashboard**           | KPIs globales, alertas y actividad DNS.             | Administrador  |
| **Gestión CRUD**        | Alta, baja y modificación de usuarios/dispositivos. | Administrador  |
| **Reportes CSV/PDF**    | Exporta registros del sistema.                      | Operador/Admin |
| **Backup**              | Permite generar copias de seguridad.                | Administrador  |


Iniciar sesión.

Configurar rango de red.

Ejecutar escaneo con Nmap.

Importar DNS desde Pi-hole.

Revisar KPIs/alertas en el dashboard.

Exportar reportes o realizar backup.

🧩 Manual Técnico
🧱 Arquitectura del Sistema

graph TD
    A[Cliente Web] -->|HTTP| B[Servidor PHP/Apache (XAMPP)]
    B -->|Consultas SQL| C[(Base de Datos MySQL)]
    B -->|Escaneo| D[Nmap\nEscaneo de red (IP, MAC, hostnames)]
    B -->|Integración DNS| E[Pi-hole (Docker)\nRegistros DNS]
    E --> C
    C -->|Datos procesados| F[Dashboard y Reportes]
    F -->|Exportación| G[CSV / PDF]

📁 Estructura Real del Proyecto (XAMPP)
Ruta principal: C:\xampp\htdocs\gestion_ipmac\

text
gestion_ipmac/
├── codigo/
│   ├── php/
│   │   ├── activos.php
│   │   ├── auditoria.php
│   │   ├── dashboard.php
│   │   ├── dispositivo_editar.php
│   │   ├── dispositivo_eliminar.php
│   │   ├── dns_log.php
│   │   ├── export_csv.php
│   │   ├── funciones.php
│   │   ├── import_pihole.php
│   │   ├── inactive.php
│   │   ├── index.php
│   │   ├── logout.php
│   │   ├── mer.php
│   │   ├── scan.php
│   │   ├── usuario_red_list.php
│   │   ├── usuarios.php
│   │   └── conexion.php
│   ├── css/
│   │   ├── estilos.css
│   │   └── dashboard.css
│   └── js/
│       ├── dashboard.js
│       └── funciones.js
├── sql/
│   └── gestion_ip_mac_v2.sql
├── docker-compose.yml
└── README.md
🔐 Seguridad del Sistema
Control de acceso por roles (Administrador / Operador).

Validación para evitar duplicados de MAC.

Registro de auditoría (usuario, acción, fecha/hora).

Sesiones PHP con expiración controlada.

Acceso restringido a funciones críticas.

💾 Copias de Seguridad
Desde el módulo Backup.

Formatos: .sql o .zip.

Sugerencia: copia semanal o antes de actualizar.

⚙️ Mantenimiento y Actualización
Actualizar herramientas (Nmap, Pi-hole) desde sus sitios oficiales.

Si falla el contenedor:

bash
docker-compose down
docker-compose up -d
🧠 Autor y Licencia
Desarrollador: Charlie Bailey Moya
Licencia: MIT — uso, modificación y redistribución con atribución.

Repositorio: https://github.com/charli-hash/gestion-ipmac
