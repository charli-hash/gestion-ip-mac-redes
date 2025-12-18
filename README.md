🚦 Gestión de IP & MAC
🔍 Monitoreo de Redes con Nmap + Pi-hole

💡 Sistema web para inventario, detección automática y análisis de tráfico DNS en redes locales.

👨‍💻 Autor

🧑‍💻 Charlie Bailey Moya
🎓 Programación y Análisis de Sistemas — AIEP Concepción
📅 2025

🧠 ¿Qué hace este proyecto?

Este sistema permite visualizar, administrar y auditar dispositivos conectados a una red local de forma centralizada.

🔎 Detecta automáticamente

📡 IP

🆔 MAC

💻 Hostname

⚡ Estado del dispositivo (activo / inactivo)

🌐 Monitorea tráfico DNS

🌍 Dominios consultados por cada IP

🧾 Tipos de consultas (A, AAAA, HTTPS)

📊 Historial y estadísticas en tiempo real

Todo integrado en una interfaz web moderna y clara, pensada para laboratorios, institutos y redes pequeñas/medianas.

🎯 Objetivo del proyecto

🎯 Diseñar e implementar un sistema que automatice la detección de dispositivos y el monitoreo DNS, utilizando herramientas open source y una arquitectura simple pero escalable.

🧩 Funcionalidades principales

✅ Escaneo automático de red con Nmap
✅ Registro de dispositivos activos e inactivos
✅ Importación de consultas DNS desde Pi-hole
✅ 📊 Dashboard con métricas y estadísticas
✅ 🌐 Gestión de redes (CIDR)
✅ 📤 Exportación de datos (CSV)
✅ 🔐 Auditoría básica de actividad

⚙️ Tecnologías utilizadas
🖥️ Backend

🐘 PHP

🎨 Frontend

🎨 HTML

🎨 CSS

⚙️ JavaScript

🗄️ Base de datos

🐬 MySQL

🌐 Redes

🗺️ Nmap

🛡️ Pi-hole (Docker)

🔧 Entorno

🧰 XAMPP

🐳 Docker Desktop

📂 Estructura del proyecto
📁 codigo/php      → Aplicación web
📁 codigo/sql      → Modelo y scripts de base de datos
📁 diagramas       → Diagramas del sistema
📁 documentos      → Informes y documentación

🚀 Guía rápida de ejecución

1️⃣ 🧰 Instalar XAMPP, Nmap y Docker Desktop
2️⃣ 🗄️ Importar la base de datos desde codigo/sql/gestion_red.sql
3️⃣ 📂 Copiar el proyecto a htdocs/
4️⃣ ▶️ Iniciar Apache y MySQL
5️⃣ 🐳 Ejecutar Docker y Pi-hole
6️⃣ 🌐 Acceder desde el navegador:

http://localhost/gestion_ip

🪪 Licencia

📄 Este proyecto se distribuye bajo licencia MIT, permitiendo su uso, modificación y distribución con reconocimiento al autor.

🌟 Créditos

👨‍💻 Desarrollado por: Charlie Bailey Moya
👨‍🏫 Profesor guía: Víctor Valderrama
🎓 Institución: AIEP Concepción
📅 Año: 2025

Este proyecto forma parte de un trabajo académico y promueve el uso de software libre y herramientas open source para la gestión eficiente de redes locales.
