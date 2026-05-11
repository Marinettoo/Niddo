# Niddo Home Backup Server - Project Overview (EN/ES)

**Niddo Home Backup Server** es un sistema de copias de seguridad autoalojado para usuarios domésticos. Corre en **Linux** con una gestión web muy sencilla, tipo "plug and play".

## Especificaciones Técnicas por Asignatura (Technical Specs)

### 1. Optativa ASIR (Python)
* Uso / Use: **Agente de backup** para Windows con versionado simple.
* Tecnología / Tech: Scripts de **Python muy básicos** para conectividad y subida.

### 2. Lenguaje de Marcas (HTML, CSS, PHP)
* Uso / Use: **Panel web de administración** intuitivo y dashboard.
* Tecnología / Tech: **HTML, CSS y PHP básicos** para la interfaz web.

### 3. Bases de Datos (SQL & Transact-SQL)
* Uso / Use: **Gestión de usuarios, roles** (Admin, Gestor, Lectura) y registros.
* Tecnología / Tech: **SQL y Transact-SQL muy básicos** para la base de datos central.

### 4. Administración de Sistemas Operativos (Bash & Permisos)
* Uso / Use: **Instalación nativa en Debian/Ubuntu** mediante scripts de instalación automática.
* Tecnología / Tech: **Scripts de Bash sencillos** y gestión de permisos en Linux.

### 5. Servicios de Red e Internet (Apache, Docker, APIs, DNS)
* Uso / Use: Comunicación cifrada vía **HTTPS** y despliegue del servidor central.
* Tecnología / Tech: **Apache, APIs, DNS** y preparación para futura contenerización con **Docker Compose**.

### 6. Ciberseguridad (Buenas Prácticas)
* Uso / Use: **Mini SOC** (Security Operations Center) para registro detallado de eventos.
* Tecnología / Tech: **Autenticación por Token único**, bloqueo temporal por intentos fallidos y registro de IPs.

## Ubicaciones del Sistema (System Locations)
* **Código backend:** Directorio principal de la aplicación.
* **Configuración global:** Archivo de configuración del sistema.
* **Backups principales:** Repositorio de datos por defecto.
* **Discos de backup adicionales:** Puntos de montaje para almacenamiento extra.
* **Logs:** Directorios de registros para eventos de aplicación y seguridad.

## Planificación de 2 Semanas (2-Week Schedule)
1. **Core:** Base de datos, API, Roles y Logs.
2. **Panel:** Interfaz web (Dashboard/CRUD).
3. **Backup:** Módulo de recepción de archivos y versionado.
4. **Agente:** Cliente Windows (conectividad y subida).
5. **Cierre:** SOC, Instalador Bash y refinamiento visual.