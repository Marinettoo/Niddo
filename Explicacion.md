# Niddo Home Backup Server - Explicación del Proyecto

## ¿Qué es Niddo?

Niddo es un servidor de copias de seguridad autoalojado para usuarios domésticos. Corre en Linux (Debian/Ubuntu) y se gestiona desde un panel web sencillo. Los dispositivos Windows suben sus archivos automáticamente mediante un agente en Python.

---

## Estructura de archivos

```
Niddo/
├── config/
│   └── db.php              # Conexión a la base de datos
├── api/
│   ├── auth.php            # Autenticación de usuarios y dispositivos
│   ├── backup.php          # Recepción y registro de archivos de backup
│   └── setup.php           # Creación del primer administrador
├── panel/
│   ├── style.css           # Estilos del panel web
│   ├── login.php           # Acceso (o setup inicial si no hay usuarios)
│   ├── logout.php          # Cierre de sesión
│   ├── dashboard.php       # Vista principal con estadísticas
│   ├── dispositivos.php    # Gestión de dispositivos
│   ├── usuarios.php        # Gestión de usuarios (solo Admin)
│   ├── eventos.php         # Registro de eventos de seguridad
│   └── generar_agente.php  # Genera y descarga el .exe del agente para cada dispositivo
├── niddo_schema.sql        # Esquema completo de la base de datos
└── Explicacion.md          # Este archivo
```

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

Establece la conexión con MySQL usando **PDO**, que es más seguro que `mysqli`.

- Si la conexión falla, devuelve un error HTTP 500.
- Todos los archivos de la API incluyen este fichero con `require_once`.

---

## Autenticación (`api/auth.php`)

Maneja dos tipos de autenticación mediante `$_POST`:

### Login de usuario (panel web)
- Recibe `email` y `password` por formulario HTML.
- Busca el usuario en la base de datos y verifica la contraseña con `password_verify()`.
- Si es correcto: guarda el usuario en la sesión (`$_SESSION`) y redirige al dashboard.
- Si falla: registra el intento en la tabla `events` con la IP del cliente.
- Bloquea usuarios con estado distinto de `activo`.

### Validación de token (agente Python)
- Recibe un `token` por POST.
- Busca el dispositivo en la tabla `devices`.
- Si es válido, devuelve `device_id` y `repositorio_id` en texto plano.

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
- `hash` — hash SHA del archivo para verificar integridad

**El servidor:**
1. Valida el token del dispositivo.
2. Guarda el archivo en `/var/niddo/backups/{device_id}/`.
3. Crea un registro en la tabla `backups`.
4. Registra el archivo en la tabla `files` con su hash y ruta física.
5. Actualiza el tamaño del backup.
6. Responde `ok` si todo fue bien.

---

## Panel web (`panel/`)

Interfaz de administración con estética dark/monospace. Todas las páginas comprueban que hay sesión activa; si no, redirigen al login.

### `login.php`
- Si no hay ningún usuario en la BD: muestra el formulario de creación del primer administrador.
- Si ya hay usuarios: muestra el formulario de login normal.

### `dashboard.php`
- Muestra 4 estadísticas: dispositivos, backups, usuarios y espacio total usado.
- Tabla con los 10 últimos backups (dispositivo, fecha, tamaño, estado).
- Tabla con los 10 últimos eventos de seguridad.

### `dispositivos.php`
- Formulario para registrar un nuevo dispositivo (nombre, SO, repositorio).
- El token se genera automáticamente y se muestra al crearlo para copiarlo al agente.
- Tabla con todos los dispositivos, sus tokens y un botón **"generar agente"** por cada uno.

### `generar_agente.php`
- Al hacer click en "generar agente", muestra una página de espera y ejecuta PyInstaller en el servidor.
- Genera un `.exe` de Windows con el token y la URL del servidor ya incluidos dentro.
- El ejecutable se descarga directamente — no requiere Python en el equipo destino.
- Usa **nowdoc** de PHP para que el código Python pase sin ninguna interpolación, y luego inyecta los valores con `str_replace` sobre placeholders (`__TOKEN__`, `__SERVIDOR__`, `__NOMBRE__`).
- El agente generado abre una ventana con **tkinter** (librería estándar de Python) donde el usuario selecciona las carpetas o discos a copiar y pulsa "Iniciar Backup".

### `usuarios.php` *(solo Admin)*
- Comprueba que el usuario logueado tiene rol `Admin`; si no, muestra "Acceso denegado".
- Formulario para añadir usuarios con nombre, email, contraseña y rol.
- Tabla con todos los usuarios y sus roles.

### `eventos.php`
- Muestra los últimos 200 eventos de seguridad.
- Filtros por tipo de evento generados dinámicamente desde los tipos existentes en la BD.

---

## Pendiente

- [ ] Script de instalación Bash para Debian/Ubuntu
