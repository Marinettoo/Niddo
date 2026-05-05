-- =============================================================================
-- NIDDO HOME BACKUP SERVER — Esquema de base de datos v1.1
-- Compatible con: MariaDB 10.6+ / MySQL 8.0+
-- Crear la base de datos primero:
--   CREATE DATABASE niddo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE niddo;
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. ROLES
--    Los tres roles del sistema: admin, gestor, lectura
-- -----------------------------------------------------------------------------
CREATE TABLE roles (
    id          TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(50)         NOT NULL,
    descripcion VARCHAR(255)        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales de roles
INSERT INTO roles (nombre, descripcion) VALUES
    ('admin',   'Acceso total: usuarios, repositorios, dispositivos, políticas y logs'),
    ('gestor',  'Gestión de backups en repositorios y dispositivos asignados'),
    ('lectura', 'Solo lectura: ver estado y descargar archivos dentro de sus permisos');


-- -----------------------------------------------------------------------------
-- 2. USERS
--    Usuarios del sistema. Un usuario puede tener uno o más roles.
-- -----------------------------------------------------------------------------
CREATE TABLE users (
    id                  INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    nombre              VARCHAR(100)        NOT NULL,
    email               VARCHAR(150)        NOT NULL,
    password_hash       VARCHAR(255)        NOT NULL,           -- bcrypt
    estado              ENUM('activo','bloqueado') NOT NULL DEFAULT 'activo',
    intentos_fallidos   TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    bloqueado_hasta     DATETIME            NULL,               -- NULL = no bloqueado
    fecha_alta          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_ultimo_login  DATETIME            NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 3. USER_ROLES
--    Tabla pivote: qué roles tiene cada usuario (relación N:M)
-- -----------------------------------------------------------------------------
CREATE TABLE user_roles (
    user_id     INT UNSIGNED        NOT NULL,
    role_id     TINYINT UNSIGNED    NOT NULL,
    fecha_alta  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id)  ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id)  ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 4. SESSIONS
--    Tokens de sesión activos. Permite invalidar JWTs en logout o bloqueo.
--    El backend verifica aquí antes de aceptar cualquier token.
-- -----------------------------------------------------------------------------
CREATE TABLE sessions (
    id              BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED        NOT NULL,
    token_hash      VARCHAR(64)         NOT NULL,               -- SHA-256 del JWT
    ip              VARCHAR(45)         NULL,
    user_agent      VARCHAR(500)        NULL,
    fecha_creacion  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_expira    DATETIME            NOT NULL,               -- = exp del JWT
    revocada        TINYINT(1)          NOT NULL DEFAULT 0,     -- 1 = logout/bloqueo
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessions_token (token_hash),
    INDEX idx_sessions_user   (user_id),
    INDEX idx_sessions_expira (fecha_expira),
    CONSTRAINT fk_sess_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 5. REPOSITORIES
--    Espacios de almacenamiento donde se guardan los backups.
--    Cada repositorio apunta a una ruta real del servidor Linux.
-- -----------------------------------------------------------------------------
CREATE TABLE repositories (
    id              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(100)        NOT NULL,
    ruta            VARCHAR(512)        NOT NULL,               -- ej: /srv/niddo/data
    descripcion     VARCHAR(255)        NULL,
    capacidad_gb    SMALLINT UNSIGNED   NULL,                   -- NULL = sin límite definido
    estado          ENUM('activo','inactivo','error') NOT NULL DEFAULT 'activo',
    es_default      TINYINT(1)          NOT NULL DEFAULT 0,     -- solo uno puede ser 1
    fecha_alta      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_repositories_ruta (ruta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 6. USER_REPOSITORIES
--    Qué repositorios puede ver/usar cada usuario (no aplica a admin).
--    El nivel de permiso lo determina el rol del usuario, no esta tabla.
-- -----------------------------------------------------------------------------
CREATE TABLE user_repositories (
    user_id         INT UNSIGNED    NOT NULL,
    repository_id   INT UNSIGNED    NOT NULL,
    fecha_alta      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, repository_id),
    CONSTRAINT fk_urep_user FOREIGN KEY (user_id)       REFERENCES users        (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_urep_repo FOREIGN KEY (repository_id) REFERENCES repositories (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 7. DEVICES
--    Equipos registrados que envían backups al servidor.
--    Cada dispositivo tiene un token único para autenticarse en la API.
-- -----------------------------------------------------------------------------
CREATE TABLE devices (
    id                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    nombre            VARCHAR(100)        NOT NULL,               -- ej: "PC Jesús escritorio"
    sistema_operativo VARCHAR(50)         NULL,                   -- ej: "Windows 11"
    user_id           INT UNSIGNED        NOT NULL,               -- propietario
    repository_id     INT UNSIGNED        NULL,                   -- repositorio asignado
    token             VARCHAR(64)         NOT NULL,               -- token único del agente (SHA-256)
    estado            ENUM('activo','inactivo','pendiente') NOT NULL DEFAULT 'pendiente',
    ultima_conexion   DATETIME            NULL,
    ultimo_backup     DATETIME            NULL,
    fecha_alta        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_devices_token (token),
    CONSTRAINT fk_dev_user       FOREIGN KEY (user_id)       REFERENCES users        (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_dev_repository FOREIGN KEY (repository_id) REFERENCES repositories (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 8. DEVICE_FOLDERS
--    Carpetas configuradas en cada dispositivo para hacer backup.
--    El agente Windows lee esta tabla para saber qué monitorizar.
-- -----------------------------------------------------------------------------
CREATE TABLE device_folders (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    device_id   INT UNSIGNED    NOT NULL,
    ruta        VARCHAR(1000)   NOT NULL,                       -- ruta en el cliente, ej: C:\Users\jesus\Documents
    activa      TINYINT(1)      NOT NULL DEFAULT 1,
    fecha_alta  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_df_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 9. BACKUPS
--    Registro de cada ejecución de backup (sesión de sincronización).
--    Un backup agrupa todos los archivos enviados en una pasada del agente.
-- -----------------------------------------------------------------------------
CREATE TABLE backups (
    id                   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    device_id            INT UNSIGNED    NOT NULL,
    repository_id        INT UNSIGNED    NOT NULL,
    fecha_inicio         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin            DATETIME        NULL,
    estado               ENUM('en_progreso','completado','error') NOT NULL DEFAULT 'en_progreso',
    archivos_nuevos      INT UNSIGNED    NOT NULL DEFAULT 0,
    archivos_modificados INT UNSIGNED    NOT NULL DEFAULT 0,
    archivos_borrados    INT UNSIGNED    NOT NULL DEFAULT 0,
    tamaño_bytes         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    mensaje_error        TEXT            NULL,                   -- detalle si estado = error
    PRIMARY KEY (id),
    CONSTRAINT fk_bk_device     FOREIGN KEY (device_id)     REFERENCES devices      (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_bk_repository FOREIGN KEY (repository_id) REFERENCES repositories (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 10. FILES
--     Metadatos de cada archivo y sus versiones.
--     El contenido físico se guarda en disco; aquí solo los metadatos.
--     Cada fila = una versión de un archivo en un dispositivo.
-- -----------------------------------------------------------------------------
CREATE TABLE files (
    id                  BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    device_id           INT UNSIGNED        NOT NULL,
    backup_id           INT UNSIGNED        NULL,               -- backup en el que se registró
    ruta_relativa       VARCHAR(1000)       NOT NULL,           -- ruta dentro del backup, ej: Documents/foto.jpg
    nombre              VARCHAR(255)        NOT NULL,
    extension           VARCHAR(20)         NULL,
    hash_sha256         VARCHAR(64)         NULL,               -- para detectar cambios reales
    tamaño_bytes        BIGINT UNSIGNED     NOT NULL DEFAULT 0,
    version             SMALLINT UNSIGNED   NOT NULL DEFAULT 1, -- versión 1, 2, 3…
    estado              ENUM('activo','borrado') NOT NULL DEFAULT 'activo',
    ruta_fisica         VARCHAR(1000)       NOT NULL,           -- ruta real en el servidor
    fecha_modificacion  DATETIME            NULL,               -- mtime del archivo en el cliente
    fecha_backup        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_files_device_ruta (device_id, ruta_relativa(255)),
    INDEX idx_files_backup      (backup_id),
    CONSTRAINT fk_fi_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_fi_backup FOREIGN KEY (backup_id) REFERENCES backups (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 11. EVENTS  (Mini SOC)
--     Registro centralizado de todos los eventos de seguridad y actividad.
--     Se escribe también en /var/log/niddo/security.log desde el backend.
-- -----------------------------------------------------------------------------
CREATE TABLE events (
    id          BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    tipo        ENUM(
                    'login_ok',
                    'login_fail',
                    'logout',
                    'bloqueo_usuario',
                    'crear_usuario',
                    'editar_usuario',
                    'borrar_usuario',
                    'cambio_rol',
                    'crear_repo',
                    'editar_repo',
                    'borrar_repo',
                    'crear_dispositivo',
                    'borrar_dispositivo',
                    'backup_inicio',
                    'backup_ok',
                    'backup_error',
                    'restore',
                    'cambio_config'
                ) NOT NULL,
    user_id     INT UNSIGNED        NULL,                       -- NULL si el usuario fue borrado
    ip          VARCHAR(45)         NULL,                       -- IPv4 o IPv6
    user_agent  VARCHAR(500)        NULL,
    descripcion TEXT                NULL,                       -- detalle libre del evento
    fecha       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_events_tipo  (tipo),
    INDEX idx_events_fecha (fecha),
    INDEX idx_events_user  (user_id),
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 12. SETTINGS
--     Configuración global del sistema (política de backup, retención, etc.)
--     Clave-valor para no necesitar migraciones al añadir parámetros nuevos.
-- -----------------------------------------------------------------------------
CREATE TABLE settings (
    clave       VARCHAR(100)    NOT NULL,
    valor       VARCHAR(500)    NOT NULL,
    descripcion VARCHAR(255)    NULL,
    PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores por defecto del sistema
INSERT INTO settings (clave, valor, descripcion) VALUES
    ('backup_frecuencia_min',   '30',   'Cada cuántos minutos ejecuta el agente una comprobación'),
    ('retencion_versiones_max', '10',   'Número máximo de versiones por archivo'),
    ('retencion_dias_max',      '30',   'Días máximos que se conserva una versión antigua'),
    ('login_max_intentos',      '5',    'Intentos fallidos antes de bloquear el usuario'),
    ('login_bloqueo_min',       '15',   'Minutos que dura el bloqueo por fuerza bruta'),
    ('app_nombre',              'Niddo','Nombre visible del sistema en la interfaz web');


-- =============================================================================
-- FIN DEL ESQUEMA
-- Para aplicarlo en XAMPP (MariaDB):
--   1. Abre phpMyAdmin → Nueva base de datos → niddo → utf8mb4_unicode_ci
--   2. Pestaña SQL → pega este archivo → Ejecutar
-- Para aplicarlo en el VPS:
--   mysql -u root -p niddo < niddo_schema.sql
-- =============================================================================
