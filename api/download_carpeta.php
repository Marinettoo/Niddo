<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); die('Acceso denegado'); }
require_once '../config/db.php';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('El servidor no tiene php-zip instalado. Ejecuta: sudo apt-get install php-zip && sudo systemctl restart apache2');
}

$device_id = (int)($_GET['device'] ?? 0);
$carpeta   = $_GET['carpeta'] ?? '';

if (!$device_id || $carpeta === '') { http_response_code(400); die('Parametros incorrectos'); }

$stmt = $pdo->prepare("
    SELECT f.nombre, f.punto_fisico
    FROM files f
    JOIN backups b ON b.id = f.backup_id
    WHERE b.device_id = ? AND f.carpeta = ?
    ORDER BY f.nombre
");
$stmt->execute([$device_id, $carpeta]);
$archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$archivos) { http_response_code(404); die('Sin archivos en esta carpeta'); }

$zip_path = sys_get_temp_dir() . '/niddo_' . uniqid() . '.zip';
$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE) !== true) { http_response_code(500); die('Error creando zip'); }

foreach ($archivos as $f) {
    if (file_exists($f['punto_fisico'])) {
        $zip->addFile($f['punto_fisico'], $f['nombre']);
    }
}
$zip->close();

$nombre_zip = $carpeta . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nombre_zip . '"');
header('Content-Length: ' . filesize($zip_path));
readfile($zip_path);
unlink($zip_path);
