<?php
require_once '../config/db.php';

if (!isset($_POST['token'])) die('Token requerido');

$stmt = $pdo->prepare("SELECT * FROM devices WHERE token = ?");
$stmt->execute([$_POST['token']]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!$device) {
    $pdo->prepare("INSERT INTO events (tipo, ip) VALUES ('copia_token_invalido', ?)")->execute([$ip]);
    die('Token invalido');
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== 0) {
    $pdo->prepare("INSERT INTO events (tipo, ip) VALUES ('copia_error', ?)")->execute([$ip]);
    die('Archivo requerido');
}

$device_id = $device['id'];
$nombre    = basename($_FILES['archivo']['name']);
$hash      = $_POST['hash'] ?? '';
$carpeta   = preg_replace('/[^a-zA-Z0-9_\-. ]/', '_', $_POST['carpeta'] ?? 'sin_carpeta');
$destino   = "/var/niddo/backups/$device_id/$carpeta/$nombre";

if (!is_dir("/var/niddo/backups/$device_id/$carpeta")) {
    mkdir("/var/niddo/backups/$device_id/$carpeta", 0750, true);
}

if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
    $pdo->prepare("INSERT INTO events (tipo, ip) VALUES ('copia_error', ?)")->execute([$ip]);
    die('Error al guardar archivo');
}

$stmt = $pdo->prepare("INSERT INTO backups (estado, device_id) VALUES ('completado', ?)");
$stmt->execute([$device_id]);
$backup_id = $pdo->lastInsertId();

$stmt = $pdo->prepare("INSERT INTO files (nombre, carpeta, hash_sha, punto_fisico, backup_id) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$nombre, $carpeta, $hash, $destino, $backup_id]);

$pdo->prepare("UPDATE backups SET tamaño = ? WHERE id = ?")->execute([filesize($destino), $backup_id]);

$pdo->prepare("INSERT INTO events (tipo, ip) VALUES ('copia_correcta', ?)")->execute([$ip]);

echo 'ok';
