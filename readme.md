# Niddo Home Backup Server - Explicación del Proyecto

## ¿Qué es Niddo?

Niddo es un servidor de copias de seguridad autoalojado para usuarios domésticos. Corre en Linux (Debian/Ubuntu/Raspberry Pi OS) y se gestiona desde un panel web sencillo. Los dispositivos Windows suben sus archivos automáticamente mediante un agente en Python.

---

## Estructura de archivos

```
Niddo/
├── config/
│   └── db.php              # Conexión a la base de datos
├── api/
│   ├── .htaccess           # Aumenta límites de subida a 500 MB
│   ├── auth.php            # Autenticación de usuarios y dispositivos
│   ├── backup.php          # Recepción y registro de archivos de backup
│   ├── download.php        # Descarga segura de archivos (requiere sesión)
│   └── setup.php           # Creación del primer administrador
├── panel/
│   ├── _head.php           # Partial compartido: meta tags, fuentes, CSS
│   ├── _nav.php            # Partial compartido: sidebar con navegación
│   ├── style.css           # Sistema de diseño dark completo
│   ├── login.php           # Acceso (o setup inicial si no hay usuarios)
│   ├── logout.php          # Cierre de sesión
│   ├── dashboard.php       # Vista principal con estadísticas
│   ├── dispositivos.php    # Gestión de dispositivos
│   ├── usuarios.php        # Gestión de usuarios (solo Admin)
│   ├── eventos.php         # Registro de eventos de seguridad
│   ├── restaurar.php       # Descarga de archivos desde backups
│   └── generar_agente.php  # Genera y descarga el agente .py para cada dispositivo
├── niddo_schema.sql        # Esquema completo de la base de datos
├── install.sh              # Instalador automático para Debian/Ubuntu/Raspberry Pi
├── uninstall.sh            # Desinstalador: elimina todos los paquetes y datos
└── Explicacion.md          # Este archivo
```

---

## Instalador (`install.sh`)

Script de Bash para desplegar Niddo en cualquier sistema Debian/Ubuntu/Raspberry Pi OS con un solo comando:

```bash
sudo bash install.sh
```

El instalador realiza en orden:

1. Instala Apache2, PHP, MariaDB y extensiones necesarias (`php-mysql`, `php-mbstring`).
2. Arranca y habilita los servicios con `systemctl`.
3. Crea la base de datos `niddo` y todas las tablas importando `niddo_schema.sql`.
4. Crea el usuario MySQL `niddo` con permisos solo sobre esa base de datos.
5. Copia los archivos del proyecto a `/var/www/html/niddo/`.
6. Actualiza `config/db.php` con las credenciales reales mediante `sed`.
7. Crea el directorio `/var/niddo/backups/` con permisos de `www-data`.
8. Habilita `mod_rewrite` y configura Apache para que el `.htaccess` de la API funcione.
9. Muestra la URL del panel con la IP del servidor.

## Desinstalador (`uninstall.sh`)

Elimina completamente Niddo y deja el servidor limpio:

```bash
sudo bash uninstall.sh
```

Borra: archivos del panel, todos los backups, configuración de Apache, base de datos y usuario MySQL, y desinstala todos los paquetes instalados.

---

## Base de datos (`niddo_schema.sql`)

La base de datos MySQL se llama `niddo` y contiene las siguientes tablas:

| Tabla | Descripción |
|---|---|
| `users` | Usuarios del sistema (nombre, email, contraseña hasheada, estado) |
| `roles` | Roles disponibles (Admin, Gestor, Lectura) |
| `user_roles` | Relación M:N entre usuarios y roles |
| `devices` | Dispositivos registrados, cada uno con un token único |
| `device_folders` | Carpetas configuradas por dispositivo para hacer backup |
| `repositorios` | Repositorios donde se almacenan los backups |
| `backups` | Registro de cada operación de backup (tamaño, fecha, estado) |
| `files` | Archivos individuales dentro de cada backup (con hash SHA) |
| `events` | Eventos de seguridad: logins, fallos, IPs |
| `settings` | Configuración global del sistema (clave-valor) |

---

## Conexión a la base de datos (`config/db.php`)

Establece la conexión con MySQL usando **PDO**, más seguro que `mysqli`.

- Si la conexión falla, devuelve un error HTTP 500.
- Todos los archivos de la API incluyen este fichero con `require_once`.
- El instalador actualiza automáticamente las credenciales de `root/vacío` a `niddo/niddo`.

---

## Autenticación (`api/auth.php`)

Maneja dos tipos de autenticación mediante `$_POST`:

### Login de usuario (panel web)
- Recibe `email` y `password` por formulario HTML.
- Verifica la contraseña con `password_verify()`.
- Si es correcto: guarda el usuario en `$_SESSION` y redirige al dashboard.
- Si falla: registra el intento en la tabla `events` con la IP del cliente.
- Bloquea usuarios con estado distinto de `activo`.

### Validación de token (agente Python)
- Recibe un `token` por POST.
- Busca el dispositivo en la tabla `devices`.
- Si es válido, devuelve `device_id` en texto plano.

---

## Setup inicial (`api/setup.php`)

Se ejecuta solo cuando no existe ningún usuario en la base de datos.

- Crea el rol `Admin` si no existe.
- Inserta el primer usuario con los datos del formulario.
- Le asigna el rol Admin automáticamente.
- Inicia sesión directamente y redirige al dashboard.
- Si ya hay usuarios, redirige al login sin hacer nada.

---

## Recepción de backups (`api/backup.php`)

Recibe los archivos subidos por el agente Python.

**El agente envía por POST:**
- `token` — identifica el dispositivo
- `archivo` — el fichero a guardar (`$_FILES`)
- `hash` — hash SHA256 del archivo para verificar integridad

**El servidor:**
1. Valida el token del dispositivo.
2. Guarda el archivo en `/var/niddo/backups/{device_id}/`.
3. Crea un registro en la tabla `backups`.
4. Registra el archivo en la tabla `files` con su hash y ruta física.
5. Actualiza el tamaño del backup.
6. Responde `ok` si todo fue bien.

El `.htaccess` de la carpeta `api/` amplía los límites de PHP a 500 MB de subida y 300 segundos de tiempo de ejecución.

---

## Descarga de archivos (`api/download.php`)

Endpoint seguro para servir archivos del servidor al navegador.

- Comprueba que hay sesión activa antes de servir cualquier archivo.
- Recibe el ID del archivo (`?id=`) y busca su ruta física en la tabla `files`.
- Devuelve el archivo con cabeceras `Content-Disposition: attachment`.
- Si el archivo no existe en disco o el ID es inválido, responde 404.

---

## Panel web (`panel/`)

Interfaz de administración con diseño dark y sidebar fija. Usa dos partials compartidos:

- **`_head.php`** — meta tags, carga de Inter (Google Fonts) y `style.css` con cache-busting automático.
- **`_nav.php`** — sidebar con logo, navegación con estado activo por página, avatar con inicial del usuario y botón de logout.

Todas las páginas comprueban sesión activa; si no, redirigen al login.

### `login.php`
- Si no hay ningún usuario en la BD: muestra el formulario de creación del primer administrador.
- Si ya hay usuarios: muestra el formulario de login normal.

### `dashboard.php`
- 4 tarjetas de estadísticas: dispositivos, backups, usuarios y espacio total.
- Tabla con los 10 últimos backups (dispositivo, fecha, tamaño, estado).
- Tabla con los 10 últimos eventos de seguridad.

### `dispositivos.php`
- Formulario para registrar un nuevo dispositivo (nombre, SO, repositorio).
- El token se genera automáticamente con `bin2hex(random_bytes(32))`.
- Tabla con todos los dispositivos y un enlace **"descargar agente (.py)"** por cada uno.

### `generar_agente.php`
- Al hacer click en "descargar agente", genera al vuelo el script Python configurado y lo sirve como descarga directa.
- Usa **nowdoc** de PHP (`<<<'PYTHON'`) para que el código Python no sufra interpolación, e inyecta el token y la URL del servidor con `str_replace` sobre placeholders (`__TOKEN__`, `__SERVIDOR__`, `__NOMBRE__`).
- La URL del servidor se calcula dinámicamente con `$_SERVER['HTTP_HOST']` para que funcione en cualquier red.
- El agente descargado abre una ventana con **tkinter** (librería estándar de Python) donde el usuario elige los discos o carpetas a copiar, configura el intervalo y activa el backup automático mediante **Task Scheduler de Windows** (`schtasks`).
- Requiere Python 3 instalado en el equipo Windows.

### `usuarios.php` *(solo Admin)*
- Comprueba que el usuario logueado tiene rol `Admin`; si no, muestra "Acceso denegado".
- Formulario para añadir usuarios con nombre, email, contraseña y rol.
- Tabla con todos los usuarios, roles y estado.

### `eventos.php`
- Muestra los últimos 200 eventos de seguridad.
- Filtros por tipo de evento generados dinámicamente desde los tipos existentes en la BD.
- Los eventos de tipo `*_fallido` se muestran en rojo; el resto en verde.

### `restaurar.php`
- Lista todos los dispositivos con el número de backups disponibles.
- Al seleccionar un dispositivo, muestra todos sus archivos con fecha de backup y tamaño.
- Cada archivo tiene un enlace de descarga que pasa por `api/download.php`.

---

## Agente Python (Windows)

El agente se descarga desde el panel (una vez por dispositivo) y se ejecuta con Python 3 en Windows.

**Modos de ejecución:**
- **Normal** (`python agente.py`) — abre la ventana tkinter con la interfaz gráfica.
- **Automático** (`python agente.py --auto`) — ejecuta el backup en silencio, sin GUI. Es el modo que usa Task Scheduler.

**Flujo del backup:**
1. Recorre recursivamente las carpetas seleccionadas.
2. Para cada archivo: calcula el hash SHA256 y lo sube mediante una petición `multipart/form-data` a `api/backup.php`.
3. El servidor valida el token, guarda el archivo y registra el resultado en la BD.

**Configuración persistente:** se guarda en `%APPDATA%\Niddo\{nombre_dispositivo}.json` (carpetas y intervalo).

---

## Pendiente de implementar

Los siguientes puntos son necesarios para cubrir los criterios de evaluación del módulo PASIR:

- **Bloqueo de IP** tras varios intentos fallidos de login (RA.3.e — prevención de riesgos de seguridad).
- **Indicadores de calidad en el dashboard**: tasa de éxito de backups, dispositivos activos vs inactivos, alertas de incidencias (RA.4.a/b/c).
- **Eliminar Usuarios**: Usuarios antiguos que se quieran eliminar
- **Separar por carpetas**: restaurtar carpetas completas en .zip
- **+ eventos en el visor de eventos**: copia hecha o copia restaurada
- **Página web que venda el producto**: 