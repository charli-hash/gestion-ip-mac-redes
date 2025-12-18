📡 Sistema de Gestión IP / MAC con Nmap y Pi-hole
👨‍💻 Autor

Charlie Bailey Moya

🎓 Carrera

Programación y Análisis de Sistemas — AIEP Concepción

👨‍🏫 Profesor Guía

Víctor Valderrama

📅 Año

2025

📘 Descripción del Proyecto

Sistema web para el inventario, monitoreo y control de dispositivos en redes locales, orientado a entornos educativos, domésticos y pequeñas organizaciones.

El sistema integra:

Nmap para la detección automática de dispositivos activos (IP, MAC, hostname).

Pi-hole para el registro y análisis de consultas DNS en tiempo real.

Interfaz web desarrollada en PHP y MySQL, con dashboard visual y módulos administrativos.

El objetivo principal es mejorar la trazabilidad de los dispositivos conectados, optimizar la administración de red y facilitar la auditoría del tráfico DNS.

🎯 Objetivo General (SMART)

Diseñar e implementar un sistema de gestión de IP y MAC integrando Nmap y Pi-hole, logrando una detección automatizada de al menos el 90 % de los dispositivos activos, antes de diciembre de 2025.

🧩 Objetivos Específicos (SMART)

Analizar distintos entornos de red (hogar, instituto, laboratorio).

Diseñar una base de datos MySQL orientada a la trazabilidad de dispositivos y consultas DNS.

Implementar una interfaz web en PHP con operaciones CRUD.

Integrar Nmap para la detección automática de dispositivos activos.

Incorporar Pi-hole para el registro de dominios consultados por IP.

Evaluar rendimiento, precisión y escalabilidad del sistema.

⚙️ Tecnologías Utilizadas
🧠 Lenguajes y Desarrollo Web

PHP

HTML

CSS

JavaScript

🗄️ Base de Datos

MySQL

🔍 Herramientas de Red

Nmap

Pi-hole (Docker)

🖥️ Entorno de Pruebas

XAMPP (Apache + MySQL + PHP)

Docker Desktop (para Pi-hole)

📂 Estructura del Proyecto
/documentos
 └─ Avances, informes y documentación académica

/diagramas
 └─ DFD, diagramas de clases, procesos y actividades

/codigo
 ├─ /php
 │   └─ Archivos PHP, dashboard y scripts principales
 └─ /sql
     └─ Scripts SQL y modelo de base de datos

🚀 Guía de Ejecución Rápida

1️⃣ Instalar XAMPP y Nmap en el equipo local.
2️⃣ Importar el archivo SQL ubicado en:

codigo/sql/gestion_red.sql


usando phpMyAdmin.

3️⃣ Copiar la carpeta del proyecto dentro de:

htdocs/


(si utilizas XAMPP).

4️⃣ Iniciar los servicios Apache y MySQL.

5️⃣ (Opcional) Iniciar Docker Desktop si se usará Pi-hole.

6️⃣ Acceder desde el navegador:

http://localhost/gestion_ip

🪪 Licencia

📄 Este proyecto se distribuye bajo la Licencia MIT, que permite el uso, copia y modificación del software, siempre que se reconozca al autor original.

Consulta el archivo LICENSE para más información.

🌟 Créditos

Desarrollado por Charlie Bailey Moya,
bajo la guía del Profesor Víctor Valderrama,
como proyecto académico para AIEP Concepción (2025).

El sistema promueve el uso de software libre y herramientas open source para la gestión eficiente y responsable de redes locales.*software libre y herramientas open source** para la gestión eficiente de redes locales.
