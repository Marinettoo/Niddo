<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); die('Acceso denegado'); }
require_once '../config/db.php';

$es_admin = in_array('Admin', $_SESSION['roles'] ?? []);
$uid = $_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('ID requerido'); }

if ($es_admin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("
        SELECT f.* FROM files f
        JOIN backups b ON b.id = f.backup_id
        JOIN devices d ON d.id = b.device_id
        WHERE f.id = ? AND d.user_id = ?
    ");
    $stmt->execute([$id, $uid]);
}
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file || !file_exists($file['punto_fisico'])) {
    http_response_code(404);
    die('Archivo no encontrado');
}

$ruta = $file['punto_fisico'];

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
