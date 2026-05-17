<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$es_admin = in_array('Admin', $_SESSION['roles'] ?? []);
$uid = $_SESSION['user_id'];
$device_id = (int)($_GET['device'] ?? 0);

if ($es_admin) {
    $dispositivos = $pdo->query("
        SELECT d.id, d.nombre, d.so, COUNT(DISTINCT b.id) AS total_backups
        FROM devices d LEFT JOIN backups b ON b.device_id = d.id
        GROUP BY d.id ORDER BY d.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $s = $pdo->prepare("
        SELECT d.id, d.nombre, d.so, COUNT(DISTINCT b.id) AS total_backups
        FROM devices d LEFT JOIN backups b ON b.device_id = d.id
        WHERE d.user_id = ?
        GROUP BY d.id ORDER BY d.nombre
    ");
    $s->execute([$uid]);
    $dispositivos = $s->fetchAll(PDO::FETCH_ASSOC);
}

$archivos = [];
$device   = null;
if ($device_id) {
    if ($es_admin) {
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt->execute([$device_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
        $stmt->execute([$device_id, $uid]);
    }
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($device) {
        $stmt = $pdo->prepare("
            SELECT f.id, f.nombre, f.carpeta, f.punto_fisico, b.fecha_inicio, b.id AS backup_id
            FROM files f
            JOIN backups b ON b.id = f.backup_id
            WHERE b.device_id = ?
            ORDER BY f.carpeta, b.fecha_inicio DESC, f.nombre
        ");
        $stmt->execute([$device_id]);
        $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function fmt($b) {
    if (!$b) return '—';
    if ($b >= 1073741824) return round($b/1073741824,1).' GB';
    if ($b >= 1048576)    return round($b/1048576,1).' MB';
    if ($b >= 1024)       return round($b/1024,1).' KB';
    return $b.' B';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Niddo — Restaurar</title>
    <?php include '_head.php'; ?>
</head>
<body>
<div class="layout">
    <?php include '_nav.php'; ?>
    <main class="main">
        <div class="page-header">
            <div class="page-title">Restaurar</div>
            <div class="page-sub">Descarga archivos desde tus copias de seguridad</div>
        </div>

        <div class="seccion">
            <div class="seccion-header"><div class="seccion-title">Selecciona un dispositivo</div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Dispositivo</th><th>SO</th><th>Backups</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($dispositivos as $d): ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($d['nombre']) ?></td>
                    <td><?= htmlspecialchars($d['so']) ?></td>
                    <td class="td-mono"><?= $d['total_backups'] ?></td>
                    <td><a href="?device=<?= $d['id'] ?>" class="action-link <?= $device_id===$d['id']?'action-link-active':'' ?>">ver archivos</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$dispositivos): ?><tr><td colspan="4" class="vacio">Sin dispositivos registrados</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>

        <?php if ($device): ?>
        <div class="seccion">
            <div class="seccion-header">
                <div class="seccion-title">Archivos de <?= htmlspecialchars($device['nombre']) ?></div>
            </div>
            <?php if (!$archivos): ?>
                <div class="table-wrap"><table><tbody>
                    <tr><td class="vacio">Sin archivos en este dispositivo</td></tr>
                </tbody></table></div>
            <?php else:
                $carpeta_actual = null;
                foreach ($archivos as $f):
                    $size = file_exists($f['punto_fisico']) ? filesize($f['punto_fisico']) : 0;
                    if ($f['carpeta'] !== $carpeta_actual):
                        if ($carpeta_actual !== null) echo '</tbody></table></div>';
                        $carpeta_actual = $f['carpeta'];
            ?>
                <div style="margin-top:20px; margin-bottom:6px; display:flex; align-items:center; gap:16px;">
                    <strong><?= htmlspecialchars($carpeta_actual) ?></strong>
                    <a href="../api/download_carpeta.php?device=<?= $device_id ?>&carpeta=<?= urlencode($carpeta_actual) ?>" class="action-link">descargar carpeta (zip)</a>
                </div>
                <div class="table-wrap"><table>
                    <thead><tr><th>Archivo</th><th>Backup</th><th>Tamaño</th><th></th></tr></thead>
                    <tbody>
            <?php endif; ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($f['nombre']) ?></td>
                    <td class="td-mono"><?= $f['fecha_inicio'] ?></td>
                    <td class="td-mono"><?= fmt($size) ?></td>
                    <td><a href="../api/download.php?id=<?= $f['id'] ?>" class="action-link">descargar</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
