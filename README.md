# 🚦 Gestión de IP & MAC  
## 🔍 Monitoreo de Redes con Nmap + Pi-hole

> 💡 Sistema web para el inventario, detección automática y análisis de tráfico DNS en redes locales.

---

## 👨‍💻 Autor

- **Charlie Bailey Moya**
- 🎓 Programación y Análisis de Sistemas — **AIEP Concepción**
- 📅 **2025**

---

## 🧠 Descripción

Sistema web para **gestionar y monitorear dispositivos conectados a una red local**, integrando herramientas de análisis de red y monitoreo DNS.

El sistema permite:

- Detectar dispositivos activos e inactivos  
- Registrar IP, MAC y hostname  
- Analizar consultas DNS en tiempo real  
- Visualizar métricas desde un dashboard web  

Proyecto orientado a **institutos, laboratorios, hogares y pequeñas redes**.

---

## 🎯 Objetivo

Diseñar e implementar un sistema que **automatice la detección de dispositivos y el monitoreo DNS**, utilizando herramientas *open source* y una arquitectura simple y escalable.

---

## 🧩 Funcionalidades

- 🔍 Escaneo automático de red con **Nmap**
- 🖥️ Registro de dispositivos (IP, MAC, hostname)
- 🌐 Importación de consultas DNS desde **Pi-hole**
- 📊 Dashboard con estadísticas y métricas
- 🧭 Gestión de redes mediante CIDR
- 📤 Exportación de datos (CSV)
- 🔐 Auditoría básica de actividad

---

## ⚙️ Tecnologías Utilizadas

### Backend
- `PHP`

### Frontend
- `HTML`
- `CSS`
- `JavaScript`

### Base de Datos
- `MySQL`

### Redes
- `Nmap`
- `Pi-hole` (Docker)

### Entorno
- `XAMPP`
- `Docker Desktop`

---

## 📂 Estructura del Proyecto

```text
codigo/
 ├─ php/                → Aplicación web
 └─ sql/                → Scripts y modelo de base de datos
    └─ gestion_red.sql  → Script principal de la base de datos

diagramas/              → Diagramas del sistema
documentos/             → Informes y documentación académica

## 🚀 Ejecución Rápida

### 1️⃣ Instalación de dependencias

Instalar los siguientes componentes en el equipo local:

- `XAMPP`
- `Nmap`
- `Docker Desktop`

---

### 2️⃣ Base de datos

Importar la base de datos desde:

```text
codigo/sql/gestion_red.sql
utilizando **phpMyAdmin**.

---

### 3️⃣ Despliegue del proyecto

Copiar la carpeta del proyecto dentro de:

```text
htdocs/
### 4️⃣ Servicios necesarios

Iniciar los siguientes servicios:

- `Apache`
- `MySQL`

Ejecutar:

- `Docker`
- Contenedor de `Pi-hole`

---

### 5️⃣ Acceso al sistema

Abrir el sistema desde el navegador web en:

```text
http://localhost/gestion_ip
## 🪪 Licencia

Proyecto distribuido bajo la **Licencia MIT**.

Se permite el uso, copia y modificación del software, siempre que se reconozca al autor original.

---

## 🌟 Créditos

- 👨‍💻 **Desarrollador:** Charlie Bailey Moya  
- 👨‍🏫 **Profesor guía:** Víctor Valderrama  
- 🎓 **Institución:** AIEP Concepción  
- 📅 **Año:** 2025  

Proyecto académico que promueve el uso de **software libre** y herramientas *open source* para la gestión eficiente de redes locales.




