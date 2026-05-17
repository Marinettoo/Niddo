<?php
require '_session.php';
require_once '../config/db.php';

$es_admin = in_array('Admin', $_SESSION['roles'] ?? []);
$uid = $_SESSION['user_id'];

if ($es_admin) {
    $total_devices  = $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
    $total_backups  = $pdo->query("SELECT COUNT(*) FROM backups")->fetchColumn();
    $total_usuarios = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $espacio        = $pdo->query("SELECT COALESCE(SUM(tamaño),0) FROM backups")->fetchColumn();
    $backups = $pdo->query("
        SELECT b.fecha_inicio, b.estado, b.tamaño, d.nombre AS dispositivo
        FROM backups b JOIN devices d ON d.id = b.device_id
        ORDER BY b.fecha_inicio DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    $eventos = $pdo->query("
        SELECT e.tipo, e.ip, e.fecha, u.nombre AS usuario
        FROM events e LEFT JOIN users u ON u.id = e.user_id
        ORDER BY e.fecha DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $s = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id=?"); $s->execute([$uid]);
    $total_devices = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM backups b JOIN devices d ON d.id=b.device_id WHERE d.user_id=?"); $s->execute([$uid]);
    $total_backups = $s->fetchColumn();
    $total_usuarios = null;
    $s = $pdo->prepare("SELECT COALESCE(SUM(b.tamaño),0) FROM backups b JOIN devices d ON d.id=b.device_id WHERE d.user_id=?"); $s->execute([$uid]);
    $espacio = $s->fetchColumn();
    $s = $pdo->prepare("
        SELECT b.fecha_inicio, b.estado, b.tamaño, d.nombre AS dispositivo
        FROM backups b JOIN devices d ON d.id = b.device_id
        WHERE d.user_id=? ORDER BY b.fecha_inicio DESC LIMIT 10
    "); $s->execute([$uid]);
    $backups = $s->fetchAll(PDO::FETCH_ASSOC);
    $eventos = [];
}

function fmt($b) {
    if ($b >= 1073741824) return round($b/1073741824,1).' GB';
    if ($b >= 1048576)    return round($b/1048576,1).' MB';
    if ($b >= 1024)       return round($b/1024,1).' KB';
    return $b ? $b.' B' : '—';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Niddo — Dashboard</title>
    <?php include '_head.php'; ?>
</head>
<body>
<div class="layout">
    <?php include '_nav.php'; ?>
    <main class="main">
        <div class="page-header">
            <div class="page-title">Dashboard</div>
            <div class="page-sub">Resumen del sistema</div>
        </div>

        <div class="stats">
            <div class="stat"><div class="stat-num"><?= $total_devices ?></div><div class="stat-label">Dispositivos</div></div>
            <div class="stat"><div class="stat-num"><?= $total_backups ?></div><div class="stat-label">Backups</div></div>
            <?php if ($es_admin): ?>
            <div class="stat"><div class="stat-num"><?= $total_usuarios ?></div><div class="stat-label">Usuarios</div></div>
            <?php endif; ?>
            <div class="stat"><div class="stat-num"><?= fmt($espacio) ?></div><div class="stat-label">Almacenado</div></div>
        </div>

        <div class="seccion">
            <div class="seccion-header"><div class="seccion-title">Últimos backups</div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Dispositivo</th><th>Fecha</th><th>Tamaño</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($backups as $b):
                    $c = match($b['estado']) { 'completado'=>'ok','error'=>'error',default=>'warn' }; ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($b['dispositivo']) ?></td>
                    <td class="td-mono"><?= $b['fecha_inicio'] ?></td>
                    <td class="td-mono"><?= fmt($b['tamaño']) ?></td>
                    <td><span class="badge badge-<?= $c ?>"><span class="badge-dot"></span><?= $b['estado'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$backups): ?><tr><td colspan="4" class="vacio">Sin backups registrados</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>

        <?php if ($es_admin): ?>
        <div class="seccion">
            <div class="seccion-header"><div class="seccion-title">Últimos eventos</div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Tipo</th><th>IP</th><th>Usuario</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach ($eventos as $e):
                    $c = str_contains($e['tipo'],'fallido') ? 'error' : 'ok'; ?>
                <tr>
                    <td><span class="badge badge-<?= $c ?>"><span class="badge-dot"></span><?= htmlspecialchars($e['tipo']) ?></span></td>
                    <td class="td-mono"><?= htmlspecialchars($e['ip']) ?></td>
                    <td class="td-name"><?= htmlspecialchars($e['usuario'] ?? '—') ?></td>
                    <td class="td-mono"><?= $e['fecha'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$eventos): ?><tr><td colspan="4" class="vacio">Sin eventos</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
