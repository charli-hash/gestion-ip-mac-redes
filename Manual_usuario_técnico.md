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
