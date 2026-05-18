```markdown
# Niddo Home Backup Server

**Self-hosted** backup system for home users and small businesses. It runs on Linux (Debian/Ubuntu/Raspberry Pi OS) and is managed from a simple web panel. Windows machines upload their files automatically using a Python agent downloaded from the panel.

Repository: <https://github.com/Marinettoo/Niddo>  
Project Website: `docs/` (published via GitHub Pages)

---

## Project Structure


```

Niddo/
├── config/
│   ├── db.php                 # PDO connection to MariaDB
│   └── cuotas.php             # Disk space quotas (managed from the panel)
├── api/
│   ├── .htaccess              # Raises upload limits to 500 MB
│   ├── auth.php               # Login with IP blocking + marks active user
│   ├── backup.php             # File reception from the agent (multipart)
│   ├── download.php           # Secure file download (with access control)
│   ├── download_carpeta.php   # Download of entire folders as ZIP
│   └── setup.php              # Creation of the first administrator
├── panel/
│   ├── _head.php              # Partial: meta, fonts, CSS
│   ├── _nav.php               # Partial: sidebar with role-based visibility
│   ├── _session.php           # Partial: session control + 5 min timeout
│   ├── style.css              # Panel design system
│   ├── login.php              # Login (or setup if no users exist)
│   ├── logout.php             # Session logout
│   ├── dashboard.php          # Statistics (filtered by role)
│   ├── dispositivos.php       # Device management
│   ├── usuarios.php           # User and role management (Admin only)
│   ├── eventos.php            # Security event viewer (Admin only)
│   ├── restaurar.php          # File / folder download
│   ├── discos.php             # Mounting/unmounting + quotas (Admin only)
│   └── generar_agente.php     # Generates the custom Python agent per device
├── docs/                      # Public website (HTML/CSS) — served by GitHub Pages
│   ├── index.html
│   ├── about.html
│   ├── service.html
│   ├── gallery.html
│   ├── testimonial.html
│   ├── contact.html
│   ├── css/  js/  fonts/  images/
│   ├── MER.png                # Entity-relationship diagram referenced by this readme
│   └── .nojekyll              # Disables Jekyll processing on GitHub Pages
├── web/                       # Images used by the PHP panel
│   ├── niddo Logo completo.png
│   ├── niddo Logotipo.png
│   ├── niddo Isólogo.png
│   ├── banner.png
│   └── Dispositivos.png
├── niddo_schema.sql           # Complete DB schema
├── install.sh                 # Automated installer
├── uninstall.sh               # Uninstaller (leaves the server clean)
└── readme.md                  # This file

```

---

## Installation

### From the Repository

```bash
git clone [https://github.com/Marinettoo/Niddo](https://github.com/Marinettoo/Niddo)
cd Niddo
sudo bash install.sh

```

### What the Installer Does (`install.sh`)

1. Installs Apache2, PHP, MariaDB, and required extensions (`php-mysql`, `php-mbstring`, `php-zip`).
2. Starts and enables services using `systemctl`.
3. Creates the `niddo` database and imports `niddo_schema.sql` (with `Admin` and `Usuario` roles already seeded).
4. Creates the `niddo` MySQL user with privileges restricted only to that database.
5. Copies the files to `/var/www/html/niddo/`.
6. Updates `config/db.php` with the actual credentials using `sed`.
7. Creates `/var/niddo/backups/` owned by `www-data`.
8. Enables `mod_rewrite` and configures Apache so that `.htaccess` files work.
9. **Configures sudoers**: adds `/etc/sudoers.d/niddo` so that `www-data` can execute `mount` and `umount` without a password (required for disk management from the panel).
10. Displays the panel URL containing the server's IP address.

### Uninstallation

```bash
sudo bash uninstall.sh

```

Deletes everything: panel, backups, Apache configuration, MySQL database and user, installed packages, and the sudoers rule.

---

## Database (`niddo_schema.sql`)

MariaDB `niddo` database with the following tables:

| Table | Description |
| --- | --- |
| `users` | Users (name, email, bcrypt password, active/inactive status) |
| `roles` | System roles. **Only two**: `Admin` and `Usuario` (seeded during installation) |
| `user_roles` | User-role relationships |
| `devices` | Registered devices; each with a unique token and owner `user_id` |
| `device_folders` | Configured folders per device to be backed up |
| `repositorios` | Repositories where backups are stored |
| `backups` | Each backup operation (size, date, status) |
| `files` | Individual backup files (with SHA-256 hash) |
| `events` | Security events: logins, failures, blocked IPs, etc. |
| `settings` | Key-value global configuration |

**Database Entity-Relationship Model**:


---

## Roles and Permissions

There are only two roles:

| Role | What they see and can do |
| --- | --- |
| **Admin** | Everything: devices, users, events, disks. Can view data for all users. |
| **Usuario** | Only their own devices and files. No access to Users/Events/Disks. |

**Primary Administrator** (The one with `user_id = 1`, the first one created): is the only one who can demote another Admin to Usuario. Any Admin can promote users to Admin.

---

## Security

* **Passwords with bcrypt** (`password_hash` / `password_verify`). This is the standard way to handle passwords in PHP.
* **Device tokens** consisting of 64 hex characters generated with `bin2hex`. This allows generating a unique and secure random string that acts as a key to remember and authenticate an authorized device, without needing to expose the actual password.
* **Automatic IP blocking** after 5 consecutive failed attempts (by querying the `events` table).
* **Session with a 5-minute inactivity timeout** — upon expiration, the user is marked as `inactive` in the DB and redirected to the login page. Implemented in `panel/_session.php` (included by all panel pages).
* **User status**: `active` when logging in, `inactive` when logging out or upon session expiration.
* **SHA-256 verification** for every uploaded file.
* **Mini-SOC**: the `events` table logs successful logins, failures, lockouts, completed/failed backups, configuration changes…

---

## API (`api/`)

### `auth.php` — Login and Token Validation

Handles two modes depending on `$_POST`:

* **Panel Login**: receives `email` + `password`. Verifies it with `password_verify`, checks that the IP is not blocked (5 failures in `events`), loads the user's roles into `$_SESSION['roles']`, marks the user as `active`, and initializes `$_SESSION['last_activity']`.
* **Agent Token**: receives `token`, looks it up in `devices`, and returns the `device_id` in plain text.

### `setup.php` — First Administrator

Only executes when no users exist. Creates the first admin and automatically assigns them the `Admin` role.

### `backup.php` — File Reception

Receives from the agent: `token`, `archivo` (`$_FILES`), and SHA-256 `hash`. Validates the token, saves the file into `/var/niddo/backups/{device_id}/{ruta_relativa}/`, logs the entry in `backups` and `files`, and responds with `ok`.

The `.htaccess` file in `api/` increases `upload_max_filesize` and `post_max_size` to 500 MB and `max_execution_time` to 300 s.

### `download.php` — Downloading a File

* Checks for an active session.
* If the user is a `Usuario` (non-Admin), they can only download files belonging to their own devices (via a JOIN `files → backups → devices` filtering by `user_id`).
* Returns the file using `Content-Disposition: attachment`.

### `download_carpeta.php` — Downloading a Folder as a ZIP

Receives the `device_id` and the relative path of the folder. Performs the same ownership validation as `download.php`. Generates the ZIP on the fly using PHP's native `ZipArchive` class and serves it as a download.

---

## Web Panel (`panel/`)

Interface featuring a sidebar navigation layout. All pages start with `require '_session.php'` to keep the session consistently valid.

### `dashboard.php`

* **Admin**: 4 cards (devices, backups, users, total space), a table of the latest backups, and a table of the latest events.
* **Usuario**: only displays their own devices and backups, hiding the users card and the events table.

### `dispositivos.php`

Form to register a new device (name, OS, repository). The token is automatically generated. Warns the user that Python 3 is required on the client machine, providing a link to python.org. **Non-Admin users can only see their own devices**.

### `usuarios.php` *(Admin only)*

* Create new users with an email, password, and role.
* Change any user's role (`Usuario` ↔ `Admin`).
* Only the **primary administrator** (`user_id = 1`) can demote another Admin. Any Admin can promote.
* The primary admin is marked with an asterisk `*` in the table.

### `eventos.php` *(Admin only)*

Displays the last 200 events with filtering capabilities by type. Events matching `*_fallido` (failed) are highlighted in red, while the rest are shown in green.

### `restaurar.php`

Lists devices (filtered by user if they are not an Admin). Upon selecting one, it displays all files with options for individual downloads or downloading the entire folder structure as a ZIP.

### `discos.php` *(Admin only)*

Server disk management without modifying the database schema directly:

* Lists mounted disks by reading `df -B1` (filtering out system filesystems).
* For each disk: a form to assign a quota in GB and an unmount button (`sudo umount`).
* At the bottom: a form to mount a new disk (`sudo mount`).
* Quotas are saved in `config/cuotas.php` as a PHP array.
* This works thanks to the sudoers rule added by the installer (`www-data ALL=(root) NOPASSWD: /bin/mount, /bin/umount`).

### `generar_agente.php`

Generates the Python script pre-configured for a specific device on the fly and serves it as a direct download. It uses a **nowdoc** layout (`<<<'PYTHON'`) to ensure the Python code doesn't suffer string interpolation, then replaces placeholders like `__TOKEN__`, `__SERVIDOR__`, and `__NOMBRE__` using `str_replace`. The server URL is dynamically calculated via `$_SERVER['HTTP_HOST']` to ensure it works across any network setup.

---

## Python Agent (Windows)

Downloaded from the panel (once per device) and executed using **Python 3** on Windows.

**Modes:**

* `python agente.py` — opens a **tkinter** window where the user selects folders, configures the interval, and enables automatic backups via **Task Scheduler** (`schtasks`).
* `python agente.py --auto` — silent mode triggered automatically by Task Scheduler.

**Backup Flow:**

1. Recursively traverses the selected folders.
2. For each file, it calculates the SHA-256 hash and uploads it via `multipart/form-data` to `api/backup.php`.
3. The server validates the token, stores the file, and logs the operation output.

**Persistent Configuration:** `%APPDATA%\Niddo\{nombre_dispositivo}.json`.

It exclusively uses Python's standard libraries (`urllib`, `hashlib`, `tkinter`, `os`, `json`) — requiring zero external dependency installations.

---

## Public Website (`docs/`)

HTML/CSS static site published through **GitHub Pages** from the `/docs` folder of the main branch. It utilizes a filled-out open-source template ([https://plantillashtmlgratis.com/todas-las-plantillas/plantilla/plantilla-web-gratis-limelight/](https://plantillashtmlgratis.com/todas-las-plantillas/plantilla/plantilla-web-gratis-limelight/)) to introduce the project:

* `index.html` — landing page featuring a hero section, features, and pricing details.
* `about.html` — comprehensive PASIR documentation (RA.1 through RA.4).
* `service.html` — product functionalities.
* `gallery.html` — installation guide and pricing tiers.
* `testimonial.html` — security layers and GDPR compliance details.
* `contact.html` — team credits and technical stack breakdown.

Color palette centered around the signature blue from the logo (`#1a8fe8` / `#1f3f72`).

---

## Technical Stack

| Layer | Technology |
| --- | --- |
| Server OS | Debian 12 / Ubuntu 24.04 LTS |
| Web Server | Apache 2.4 |
| Backend | PHP |
| Database | MariaDB 11.x |
| Panel Frontend | HTML5 + CSS3 (Vanilla / Framework-less) |
| Public Website | HTML5 + CSS + JS |
| Client Agent | Python3 |
| Automation | Bash + `apt-get` |
| Version Control | Git + GitHub |

---

## License

MIT — free to use, modify, and distribute, including for commercial purposes, provided the copyright notice is retained.

---

## Team

* **Jesús Pérez Marinetto** — Backend, frontend, database, website, repository maintenance, documentation
* **Nicolás Baya-Casal Sansolini** — Documentation
* **Ismael Martín Ruiz** — Database Entity-Relationship Model
* **Iván López García** —

```

```