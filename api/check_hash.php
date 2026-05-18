<?php
require_once '../config/db.php';

if (!isset($_POST['token'], $_POST['hash'], $_POST['nombre'], $_POST['carpeta'])) {
    http_response_code(400);
    die('Parametros incorrectos');
}

$stmt = $pdo->prepare("SELECT id FROM devices WHERE token = ?");
$stmt->execute([$_POST['token']]);
$device_id = $stmt->fetchColumn();

if (!$device_id) {
    http_response_code(401);
    die('Token invalido');
}

$carpeta = preg_replace('/[^a-zA-Z0-9_\-. ]/', '_', $_POST['carpeta']);
$nombre  = basename($_POST['nombre']);
$hash    = $_POST['hash'];

$stmt = $pdo->prepare("
    SELECT f.id FROM files f
    JOIN backups b ON b.id = f.backup_id
    WHERE b.device_id = ? AND f.carpeta = ? AND f.nombre = ? AND f.hash_sha = ?
    LIMIT 1
");
$stmt->execute([$device_id, $carpeta, $nombre, $hash]);

echo $stmt->fetchColumn() ? 'existe' : 'nuevo';
