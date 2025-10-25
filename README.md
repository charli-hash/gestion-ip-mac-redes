# 🖥️ **Sistema de Gestión de IP y MAC con Nmap y Pi-hole**

---

### 👨‍💻 **Autor:**  
**Charlie Bailey Moya**  

### 🎓 **Carrera:**  
**Programación y Análisis de Sistemas — AIEP Concepción**  

### 👨‍🏫 **Profesor Guía:**  
**Víctor Valderrama**  

### 📅 **Año:**  
**2025**

---

## 📘 **Descripción**
Sistema general para el **inventario y control de dispositivos en redes locales**.  
Integra **Nmap** (detección de IP, MAC y hostname) y **Pi-hole** (registro de consultas DNS) en una **interfaz web desarrollada con PHP y MySQL**.  
El proyecto busca optimizar la administración de redes, automatizar la detección de equipos y mejorar la trazabilidad del tráfico en tiempo real.

---

## 🎯 **Objetivo General (SMART)**
> Diseñar e implementar un sistema de gestión de IP y MAC integrando **Nmap** y **Pi-hole**, alcanzando una detección automatizada del **90% de los dispositivos activos** antes de diciembre de 2025.

---

## 🧩 **Objetivos Específicos (SMART)**
1. Analizar distintos entornos de red (hogar, oficina, PyME, laboratorio).  
2. Diseñar una base de datos MySQL y una interfaz PHP con operaciones CRUD.  
3. Integrar **Nmap** para la detección automática de dispositivos activos.  
4. Incorporar **Pi-hole** para el registro de dominios consultados por IP.  
5. Evaluar el rendimiento, precisión y escalabilidad del sistema.

---

## ⚙️ **Tecnologías Utilizadas**
🧠 **Lenguajes y Frameworks:** PHP · HTML · CSS · JavaScript  
🗄️ **Base de Datos:** MySQL  
🔍 **Herramientas de Red:** Nmap · Pi-hole  
🖥️ **Entorno de Pruebas:** XAMPP (Apache + MySQL + PHP)

---

## 📂 **Estructura Sugerida del Proyecto**
 /documentos → Avances, informes y documentación
/diagramas → DFD, diagramas de actividades y clases
/codigo/php → Archivos PHP y scripts principales
/codigo/sql → Scripts SQL y modelo de base de datos



---

## 🚀 **Guía de Ejecución Rápida**
1️⃣ Instalar **XAMPP** y **Nmap** en el equipo local.  
2️⃣ Importar el archivo SQL: `codigo/sql/gestion_red.sql` en **phpMyAdmin**.  
3️⃣ Copiar la carpeta del proyecto a `htdocs` (si usas XAMPP).  
4️⃣ Iniciar los servicios Apache y MySQL.  
5️⃣ Abrir en navegador:  
👉 `http://localhost/gestion_ip`  

---

## 🪪 **Licencia**
📄 Este proyecto se distribuye bajo la licencia **MIT**, que permite el uso, copia y modificación del software con reconocimiento al autor.  
Consulta el archivo `LICENSE` para más información.

---

## 🌟 **Créditos**
Desarrollado por **Charlie Bailey Moya**, bajo la guía de **Profesor Víctor Valderrama**,  
como proyecto de título para **AIEP Concepción (2025)**.  
El sistema promueve el uso de **software libre y herramientas open source** para la gestión eficiente de redes locales.
