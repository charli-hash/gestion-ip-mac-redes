# 🧾 Manual de Usuario y Técnico  
### Sistema de Gestión IP/MAC Integrado con Nmap, Pi-hole y Docker  

**Autor:** Charlie Bailey Moya  
**Carrera:** Programación y Análisis de Sistemas – AIEP Concepción  
**Docente:** Víctor Valderrama Mora  
**Año:** 2025  

---

## ⚙️ Instalación y Configuración  

### Requisitos Previos  

| Herramienta | Descripción | Uso principal |
|-------------|--------------|----------------|
| **XAMPP** | Incluye Apache, PHP y MySQL. | Entorno local para ejecutar la aplicación web y la base de datos. |
| **Docker Desktop** | Plataforma de contenedores. | Permite correr Pi-hole y servicios adicionales. |
| **Nmap** | Herramienta de escaneo de red. | Detecta IP, MAC y fabricantes de dispositivos conectados. |
| **Pi-hole** | Servidor DNS local open source. | Registra y filtra las consultas DNS generadas en la red. |

---

### Instalación Paso a Paso  

#### 🧱 1️⃣ Instalar XAMPP  

1. Descargar desde [https://www.apachefriends.org](https://www.apachefriends.org)  
2. Instalar en la ruta por defecto `C:\xampp\`  
3. Iniciar **Apache** y **MySQL** desde el *XAMPP Control Panel*  
4. Verificar funcionamiento en el navegador: [http://localhost](http://localhost)  

---

#### 🐳 2️⃣ Instalar Docker Desktop  

1. Descargar Docker Desktop desde [https://www.docker.com](https://www.docker.com)  
2. Instalar y activar la opción **“Use WSL 2 based engine”**  
3. Iniciar Docker Desktop y confirmar que el servicio esté activo.  

---

#### 🧩 3️⃣ Configurar Pi-hole con Docker  

1. En la carpeta raíz del proyecto (`C:\xampp\htdocs\gestion_ipmac\`), crear el archivo **`docker-compose.yml`**:

```yaml
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


En la terminal PowerShell o CMD, ejecutar:

cd C:\xampp\htdocs\gestion_ipmac\
docker-compose up -d


Acceder a la interfaz web:
👉 http://localhost:8053/admin

Iniciar sesión (contraseña: admin) y verificar que el servicio DNS esté activo.


🔍 4️⃣ Instalar Nmap

Descargar desde 👉 https://nmap.org/download.html

Instalar en la ruta:
C:\Program Files (x86)\Nmap\

Comprobar instalación en CMD:

nmap -v


💻 5️⃣ Instalar el Sistema de Gestión IP/MAC

Clonar o descargar el repositorio:

git clone https://github.com/charli-hash/gestion-ipmac.git

Mover la carpeta descargada a:
C:\xampp\htdocs\

Abrir phpMyAdmin 👉 http://localhost/phpmyadmin

Crear la base de datos:
gestion_ip_mac_v2

Importar el archivo SQL:
sql/gestion_ip_mac_v2.sql

Revisar el archivo de conexión (conexion.php):

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestion_ip_mac_v2";


🌐 6️⃣ Acceder al Sistema

Abrir el navegador y acceder a:
👉 http://localhost:8080/gestion_ipmac/codigo/php/index.php

Credenciales iniciales:

Administrador: admin@demo / admin123

Operador: operador@demo / operador123

🧠 7️⃣ Verificar Integración Completa

| Servicio      | Acción                            | Resultado esperado                        |
| ------------- | --------------------------------- | ----------------------------------------- |
| **Nmap**      | Ejecutar escaneo desde el sistema | Detección de IP, MAC y fabricantes.       |
| **Pi-hole**   | Monitorear registros DNS          | Consultas activas en tiempo real.         |
| **Dashboard** | Visualizar métricas               | KPIs actualizados sin recargar la página. |


👤 Manual del Usuario

| Módulo                  | Descripción                                         | Rol            |
| ----------------------- | --------------------------------------------------- | -------------- |
| **Inicio de sesión**    | Acceso seguro por rol (Administrador / Operador).   | Sistema        |
| **Escaneo Nmap**        | Detecta y registra IP, MAC y fabricantes.           | Sistema        |
| **Integración Pi-hole** | Importa registros DNS asociados a dispositivos.     | Sistema        |
| **Dashboard**           | Visualiza KPIs globales, alertas y actividad DNS.   | Administrador  |
| **Gestión CRUD**        | Alta, baja y modificación de usuarios/dispositivos. | Administrador  |
| **Reportes CSV/PDF**    | Exporta registros del sistema.                      | Operador/Admin |
| **Backup**              | Permite generar copias de seguridad.                | Administrador  |



Flujo de uso:

Iniciar sesión con credenciales.

Configurar el rango de red.

Ejecutar escaneo con Nmap.

Importar registros DNS desde Pi-hole.

Visualizar actividad y KPIs en el dashboard.

Exportar reportes o ejecutar copia de respaldo.


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


📁 Estructura Real del Proyecto (Verificada en XAMPP)

Ruta principal:
C:\xampp\htdocs\gestion_ipmac\

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

Validación automática para evitar duplicados de MAC.

Registro de auditoría con usuario, acción y fecha/hora.

Sesiones PHP seguras con expiración controlada.

Acceso restringido a funciones críticas del sistema.

💾 Copias de Seguridad

Generadas desde el módulo Backup.

Formatos disponibles: .sql o .zip.

Recomendado: realizar una copia semanal o antes de actualizar el sistema.

⚙️ Mantenimiento y Actualización

Actualizar dependencias mediante descarga directa de nuevas versiones de Nmap y Pi-hole.

En caso de error en el contenedor Docker, ejecutar:


docker-compose down
docker-compose up -d


🧠 Autor y Licencia

Desarrollador: Charlie Bailey Moya
Licencia: MIT

Permite uso, modificación y redistribución libre, siempre con atribución al autor.

Repositorio oficial:
https://github.com/charli-hash/gestion-ipmac

