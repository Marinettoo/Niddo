<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); die('Acceso denegado'); }
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die('Metodo no permitido'); }

$es_admin = in_array('Admin', $_SESSION['roles'] ?? []);
$uid      = (int)$_SESSION['user_id'];
$ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$file_id   = (int)($_POST['id'] ?? 0);
$device_id = (int)($_POST['device'] ?? 0);
$carpeta   = $_POST['carpeta'] ?? '';

// Selecciona archivos a borrar segun el modo (archivo individual o carpeta entera)
// y aplica el filtro por propietario si el usuario no es admin.
if ($file_id > 0) {
    if ($es_admin) {
        $stmt = $pdo->prepare("SELECT f.id, f.punto_fisico, f.backup_id FROM files f WHERE f.id = ?");
        $stmt->execute([$file_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT f.id, f.punto_fisico, f.backup_id
            FROM files f
            JOIN backups b ON b.id = f.backup_id
            JOIN devices d ON d.id = b.device_id
            WHERE f.id = ? AND d.user_id = ?
        ");
        $stmt->execute([$file_id, $uid]);
    }
} elseif ($device_id > 0 && $carpeta !== '') {
    if ($es_admin) {
        $stmt = $pdo->prepare("
            SELECT f.id, f.punto_fisico, f.backup_id
            FROM files f
            JOIN backups b ON b.id = f.backup_id
            WHERE b.device_id = ? AND f.carpeta = ?
        ");
        $stmt->execute([$device_id, $carpeta]);
    } else {
        $stmt = $pdo->prepare("
            SELECT f.id, f.punto_fisico, f.backup_id
            FROM files f
            JOIN backups b ON b.id = f.backup_id
            JOIN devices d ON d.id = b.device_id
            WHERE b.device_id = ? AND f.carpeta = ? AND d.user_id = ?
        ");
        $stmt->execute([$device_id, $carpeta, $uid]);
    }
} else {
    http_response_code(400);
    die('Parametros incorrectos');
}

$archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$archivos) {
    $pdo->prepare("INSERT INTO events (tipo, ip, user_id) VALUES ('borrado_denegado', ?, ?)")->execute([$ip, $uid]);
    http_response_code(404);
    die('Sin permisos o archivo no encontrado');
}

$pdo->beginTransaction();
try {
    foreach ($archivos as $a) {
        if (file_exists($a['punto_fisico'])) @unlink($a['punto_fisico']);
        // ON DELETE CASCADE de backups → files elimina tambien la fila de files
        $pdo->prepare("DELETE FROM backups WHERE id = ?")->execute([$a['backup_id']]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    $pdo->prepare("INSERT INTO events (tipo, ip, user_id) VALUES ('borrado_error', ?, ?)")->execute([$ip, $uid]);
    http_response_code(500);
    die('Error al borrar');
}

// Si era borrado de carpeta entera, intentar eliminar el directorio fisico vacio
if ($device_id > 0 && $carpeta !== '') {
    $dir = "/var/niddo/backups/$device_id/$carpeta";
    if (is_dir($dir)) @rmdir($dir);
}

$pdo->prepare("INSERT INTO events (tipo, ip, user_id) VALUES ('archivo_borrado', ?, ?)")->execute([$ip, $uid]);

$volver = $device_id ? "../panel/restaurar.php?device=$device_id" : '../panel/restaurar.php';
header("Location: $volver");
exit;
