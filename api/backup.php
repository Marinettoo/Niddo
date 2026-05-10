<?php
require_once '../config/db.php';

// Verificar token del dispositivo
if (!isset($_POST['token'])) die('Token requerido');

$stmt = $pdo->prepare("SELECT * FROM devices WHERE token = ?");
$stmt->execute([$_POST['token']]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) die('Token invalido');

// Verificar que se subio un archivo
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== 0) die('Archivo requerido');

$device_id = $device['id'];
$nombre    = basename($_FILES['archivo']['name']);
$hash      = $_POST['hash'] ?? '';
$destino   = "/var/niddo/backups/$device_id/$nombre";

// Crear carpeta si no existe
if (!is_dir("/var/niddo/backups/$device_id")) {
    mkdir("/var/niddo/backups/$device_id", 0750, true);
}

// Mover el archivo al destino
if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) die('Error al guardar archivo');

// Crear registro de backup
$stmt = $pdo->prepare("INSERT INTO backups (estado, device_id) VALUES ('completado', ?)");
$stmt->execute([$device_id]);
$backup_id = $pdo->lastInsertId();

// Registrar el archivo
$stmt = $pdo->prepare("INSERT INTO files (nombre, hash_sha, punto_fisico, backup_id) VALUES (?, ?, ?, ?)");
$stmt->execute([$nombre, $hash, $destino, $backup_id]);

// Actualizar tamaño del backup
$pdo->prepare("UPDATE backups SET tamaño = ? WHERE id = ?")->execute([filesize($destino), $backup_id]);

echo 'ok';
