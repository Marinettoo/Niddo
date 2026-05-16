-- Creación de la base de datos
CREATE DATABASE niddo;
USE niddo;

-- 1. Tabla independiente de configuraciones
CREATE TABLE settings (
    clave VARCHAR(100) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
);

-- 2. Tabla de roles 
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL
);

-- 3. Tabla principal de usuarios
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    pwd_hash VARCHAR(255) NOT NULL,
    estado VARCHAR(20) DEFAULT 'activo'
);

-- 4. Tabla intermedia 
CREATE TABLE user_roles (
    user_id INT,
    role_id INT,
    fecha_alta DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- 5. Tabla de eventos de seguridad
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    ip VARCHAR(45),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Tabla de repositorios
CREATE TABLE repositorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

-- 7. Tabla de dispositivos
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    so VARCHAR(50),
    token VARCHAR(64) UNIQUE,
    user_id INT,
    repositorio_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (repositorio_id) REFERENCES repositorios(id)
);

-- 8. Tabla de carpetas por dispositivo 
CREATE TABLE device_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ruta VARCHAR(255) NOT NULL,
    device_id INT,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- 9. Tabla de backups 
CREATE TABLE backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tamaño BIGINT DEFAULT 0,
    fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(50),
    device_id INT,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- 10. Tabla de archivos 
CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    hash_sha VARCHAR(64),
    punto_fisico VARCHAR(500),
    backup_id INT,
    FOREIGN KEY (backup_id) REFERENCES backups(id) ON DELETE CASCADE
);