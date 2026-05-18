# Niddo Home Backup Server

Sistema de copias de seguridad **autoalojado** para usuarios domésticos y pequeñas empresas. Corre sobre Linux (Debian/Ubuntu/Raspberry Pi OS) y se gestiona desde un panel web sencillo. Los equipos Windows suben sus archivos automáticamente mediante un agente Python que se descarga desde el panel.


Repositorio: <https://github.com/Marinettoo/Niddo>
Web del proyecto: `web/limelight-html/`

---

## Estructura del proyecto

```
Niddo/
├── config/
│   ├── db.php                # Conexión PDO a MariaDB
│   └── cuotas.php            # Cuotas de espacio por disco (gestionado desde el panel)
├── api/
│   ├── .htaccess             # Eleva límites de subida a 500 MB
│   ├── auth.php              # Login con bloqueo por IP + marca usuario activo
│   ├── backup.php            # Recepción de archivos del agente (multipart)
│   ├── download.php          # Descarga segura de archivos (con control de acceso)
│   ├── download_carpeta.php  # Descarga de carpetas enteras como ZIP
│   └── setup.php             # Creación del primer administrador
├── panel/
│   ├── _head.php             # Partial: meta, fuentes, CSS
│   ├── _nav.php              # Partial: sidebar con visibilidad por rol
│   ├── _session.php          # Partial: control de sesión + timeout de 5 min
│   ├── style.css             # Sistema de diseño del panel
│   ├── login.php             # Login (o setup si no hay usuarios)
│   ├── logout.php            # Cierre de sesión
│   ├── dashboard.php         # Estadísticas (filtradas por rol)
│   ├── dispositivos.php      # Gestión de dispositivos
│   ├── usuarios.php          # Gestión de usuarios y roles (solo Admin)
│   ├── eventos.php           # Visor de eventos de seguridad (solo Admin)
│   ├── restaurar.php         # Descarga de archivos / carpetas
│   ├── discos.php            # Montaje/desmontaje + cuotas (solo Admin)
│   └── generar_agente.php    # Genera el agente Python personalizado por dispositivo
├── web/
│   ├── limelight-html/       # Web pública del proyecto (HTML/CSS)
│   ├── niddo Logo completo.png
│   ├── niddo Logotipo.png
│   ├── niddo Isólogo.png
│   ├── banner.png
│   └── Dispositivos.png
├── niddo_schema.sql          # Esquema completo de BD
├── install.sh                # Instalador automático
├── uninstall.sh              # Desinstalador (deja el servidor limpio)
└── readme.md                 # Este archivo
```

---

## Instalación

### Desde el repositorio

```bash
git clone https://github.com/Marinettoo/Niddo
cd Niddo
sudo bash install.sh
```

### Qué hace el instalador (`install.sh`)

1. Instala Apache2, PHP, MariaDB y extensiones necesarias (`php-mysql`, `php-mbstring`, `php-zip`).
2. Arranca y habilita los servicios con `systemctl`.
3. Crea la base de datos `niddo` e importa `niddo_schema.sql` (con los roles `Admin` y `Usuario` ya sembrados).
4. Crea el usuario MySQL `niddo` con permisos solo sobre esa base.
5. Copia los archivos a `/var/www/html/niddo/`.
6. Actualiza `config/db.php` con las credenciales reales mediante `sed`.
7. Crea `/var/niddo/backups/` con permisos de `www-data`.
8. Habilita `mod_rewrite` y configura Apache para que los `.htaccess` funcionen.
9. **Configura sudoers**: añade `/etc/sudoers.d/niddo` para que `www-data` pueda ejecutar `mount` y `umount` sin contraseña (necesario para la gestión de discos desde el panel).
10. Muestra la URL del panel con la IP del servidor.

### Desinstalación

```bash
sudo bash uninstall.sh
```

Borra todo: panel, backups, configuración de Apache, base de datos y usuario MySQL, paquetes instalados y la regla de sudoers.

---

## Base de datos (`niddo_schema.sql`)

Base MariaDB `niddo` con las siguientes tablas:

| Tabla            | Descripción                                                                |
|------------------|----------------------------------------------------------------------------|
| `users`          | Usuarios (nombre, email, contraseña bcrypt, estado activo/inactivo)        |
| `roles`          | Roles del sistema. **Solo dos**: `Admin` y `Usuario` (sembrados al instalar)|
| `user_roles`     |                                   |
| `devices`        | Dispositivos registrados; cada uno con token único y `user_id` propietario |
| `device_folders` | Carpetas configuradas por dispositivo para hacer backup                    |
| `repositorios`   | Repositorios donde se almacenan los backups                                |
| `backups`        | Cada operación de backup (tamaño, fecha, estado)                           |
| `files`          | Archivos individuales del backup (con hash SHA-256)                        |
| `events`         | Eventos de seguridad: logins, fallos, IPs bloqueadas, etc.                 |
| `settings`       | Configuración global clave-valor                                           |

---

## Roles y permisos

Solo existen dos roles:

| Rol       | Qué ve y qué puede hacer                                                         |
|-----------|----------------------------------------------------------------------------------|
| **Admin** | Todo: dispositivos, usuarios, eventos, discos. Ve datos de todos los usuarios.   |
| **Usuario** | Solo sus propios dispositivos y sus propios archivos. Sin acceso a Usuarios/Eventos/Discos. |

**Administrador principal** ( El que su user_id = 1, el primero creado): es el único que puede degradar a otro Admin a Usuario. Cualquier Admin puede promocionar usuarios a Admin.

---

## Seguridad

- **Contraseñas con bcrypt** (`password_hash` / `password_verify`). Es la manera estándar de manejar contraseñas en PHP.
- **Tokens de dispositivo** de 64 caracteres hex con bin2hex. Permite generar una cadena aleatoria única y segura que actúa como una llave para recordar y autenticar un dispositivo autorizado, sin necesidad de exponer la contraseña real.
- **Bloqueo automático de IP** tras 5 intentos fallidos consecutivos (consultando la tabla `events`).
- **Sesión con timeout de 5 minutos** de inactividad — al expirar, el usuario queda marcado como `inactivo` en la BD y se redirige al login. Implementado en `panel/_session.php` (incluido por todas las páginas del panel).
- **Estado de usuario**: `activo` al iniciar sesión, `inactivo` al cerrarla o al expirar.
- **Verificación SHA-256** de cada archivo subido.
- **Mini-SOC**: la tabla `events` registra logins, fallos, bloqueos, copias completadas/erróneas, cambios de configuración…

---

## API (`api/`)

### `auth.php` — Login y validación de token

Maneja dos modos según el `$_POST`:

- **Login de panel**: recibe `email` + `password`. Verifica con `password_verify`, comprueba que la IP no esté bloqueada (5 fallos en `events`), carga los roles del usuario en `$_SESSION['roles']`, marca al usuario como `activo`, e inicializa `$_SESSION['last_activity']`.
- **Token del agente**: recibe `token`, busca en `devices` y devuelve `device_id` en texto plano.

### `setup.php` — Primer administrador

Solo se ejecuta cuando no hay ningún usuario. Crea al primer admin y le asigna automáticamente el rol `Admin`.

### `backup.php` — Recepción de archivos

Recibe del agente: `token`, `archivo` (`$_FILES`) y `hash` SHA-256. Valida el token, guarda el fichero en `/var/niddo/backups/{device_id}/{ruta_relativa}/`, registra en `backups` y `files`, y responde `ok`.

El `.htaccess` de `api/` amplía `upload_max_filesize` y `post_max_size` a 500 MB y `max_execution_time` a 300 s.

### `download.php` — Descarga de un archivo

- Comprueba sesión activa.
- Si el usuario es `Usuario` (no Admin), solo puede descargar archivos que pertenezcan a sus propios dispositivos (JOIN `files → backups → devices` filtrando por `user_id`).
- Devuelve el archivo con `Content-Disposition: attachment`.

### `download_carpeta.php` — Descarga de una carpeta como ZIP

Recibe el `device_id` y la ruta relativa de la carpeta. Misma comprobación de propiedad que `download.php`. Genera el ZIP al vuelo con ZipArchive de PHP. Que es una clase nativa de php y lo manda como descarga.

---

## Panel web (`panel/`)

Interfaz con nav en el lado. Todas las páginas empiezan con `require '_session.php'`. Para mantener la sesión siempre activa.

### `dashboard.php`

- **Admin**: 4 tarjetas (dispositivos, backups, usuarios, espacio total), tabla de últimos backups y de últimos eventos.
- **Usuario**: solo sus propios dispositivos y backups, sin tarjeta de usuarios ni tabla de eventos.

### `dispositivos.php`

Formulario para registrar un nuevo dispositivo (nombre, SO, repositorio). El token se genera automáticamente. Avisa de que se necesita Python 3 en el cliente con enlace a python.org. **Los usuarios no-Admin solo ven sus propios dispositivos**.

### `usuarios.php` *(solo Admin)*

- Crear usuarios nuevos con email, contraseña y rol.
- Cambiar el rol de cualquier usuario (`Usuario` ↔ `Admin`).
- Solo el **administrador principal** (`user_id = 1`) puede degradar a otro Admin. Cualquier Admin puede promocionar.
- El admin principal aparece marcado con `*` en la tabla.

### `eventos.php` *(solo Admin)*

Últimos 200 eventos con filtro por tipo. Los eventos `*_fallido` se muestran en rojo, el resto en verde.

### `restaurar.php`

Lista dispositivos (filtrados por usuario si no es Admin). Al seleccionar uno, muestra todos los archivos con opción de descarga individual o descarga de la carpeta completa como ZIP.

### `discos.php` *(solo Admin)*

Gestión de discos del servidor sin tocar la base de datos:

- Lista discos montados leyendo `df -B1` (filtra los archivos de sistema).
- Por cada disco: formulario para asignar cuota en GB y botón de desmontaje (`sudo umount`).
- Al final: formulario para montar un disco nuevo (`sudo mount`).
- Las cuotas se guardan en `config/cuotas.php` como un array PHP.
- Funciona gracias a la regla de sudoers que añade el instalador (`www-data ALL=(root) NOPASSWD: /bin/mount, /bin/umount`).

### `generar_agente.php`

Genera al vuelo el script Python configurado para un dispositivo concreto y lo sirve como descarga directa. Usa **nowdoc** (`<<<'PYTHON'`) para que el código Python no sufra interpolación, e inyecta `__TOKEN__`, `__SERVIDOR__` y `__NOMBRE__` con `str_replace`. La URL del servidor se calcula con `$_SERVER['HTTP_HOST']` para que funcione en cualquier red.

---

## Agente Python (Windows)

Se descarga desde el panel (una vez por dispositivo) y se ejecuta con **Python 3** en Windows.

**Modos:**
- `python agente.py` — abre la ventana **tkinter** donde el usuario elige carpetas, configura el intervalo y activa el backup automático mediante **Task Scheduler** (`schtasks`).
- `python agente.py --auto` — modo silencioso usado por Task Scheduler.

**Flujo del backup:**
1. Recorre recursivamente las carpetas seleccionadas.
2. Para cada archivo calcula el hash SHA-256 y lo sube por `multipart/form-data` a `api/backup.php`.
3. El servidor valida token, guarda el archivo y registra el resultado.

**Configuración persistente:** `%APPDATA%\Niddo\{nombre_dispositivo}.json`.

Solo usa librerías estándar de Python (`urllib`, `hashlib`, `tkinter`, `os`, `json`) — sin dependencias externas que instalar.

---

## Web pública (`web/limelight-html/`)

Sitio HTML/CSS. Es una plantilla de codigo abierto (<https://plantillashtmlgratis.com/todas-las-plantillas/plantilla/plantilla-web-gratis-limelight/>) rellenada. Para presentar el proyecto:

- `index.html` — landing con hero, features, precios.
- `about.html` — documentación PASIR completa (RA.1 a RA.4).
- `service.html` — funcionalidades del producto.
- `gallery.html` — guía de instalación y planes de precios.
- `testimonial.html` — capas de seguridad y RGPD.
- `contact.html` — créditos del equipo y stack técnico.

Paleta basada en el azul del logo (`#1a8fe8` / `#1f3f72`).

---

## Stack técnico

| Capa            | Tecnología                                 |
|-----------------|--------------------------------------------|
| SO servidor     | Debian 12 / Ubuntu 24.04 LTS               |
| Servidor web    | Apache 2.4                                 |
| Backend         | PHP                                        |
| Base de datos   | MariaDB 11.x                               |
| Frontend panel  | HTML5 + CSS3 (sin frameworks)              |
| Web pública     | HTML5 + CSS + JS                           |
| Agente cliente  | Python3                                    |
| Automatización  | Bash + `apt-get`                           |
| Control de ver. | Git + GitHub                               |

---

## Licencia

MIT — uso, modificación y distribución libres, incluso comercial, manteniendo el aviso de copyright.

---

## Equipo

- **Jesús Pérez Marinetto** — backend, frontend, base de datos, Página web, mantenimiento del repositorio, Documentación
- **Nicolás Baya-Casal Sansolini** — Documentación
- **Ismael Martín Ruiz** — Modelo Entidad-Relación de la base de datos
- **Iván López García** — 
