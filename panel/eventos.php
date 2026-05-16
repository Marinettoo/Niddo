<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$filtro = $_GET['tipo'] ?? '';
if ($filtro) {
    $stmt = $pdo->prepare("SELECT e.tipo, e.ip, e.fecha, u.nombre AS usuario FROM events e LEFT JOIN users u ON u.id=e.user_id WHERE e.tipo=? ORDER BY e.fecha DESC LIMIT 200");
    $stmt->execute([$filtro]);
} else {
    $stmt = $pdo->query("SELECT e.tipo, e.ip, e.fecha, u.nombre AS usuario FROM events e LEFT JOIN users u ON u.id=e.user_id ORDER BY e.fecha DESC LIMIT 200");
}
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$tipos   = $pdo->query("SELECT DISTINCT tipo FROM events ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Niddo — Eventos</title>
    <?php include '_head.php'; ?>
</head>
<body>
<div class="layout">
    <?php include '_nav.php'; ?>
    <main class="main">
        <div class="page-header">
            <div class="page-title">Eventos de seguridad</div>
            <div class="page-sub">Registro de actividad del sistema</div>
        </div>

        <div class="filtros">
            <a href="eventos.php" class="filtro <?= !$filtro?'activo':'' ?>">Todos</a>
            <?php foreach ($tipos as $t): ?>
                <a href="?tipo=<?= urlencode($t) ?>" class="filtro <?= $filtro===$t?'activo':'' ?>"><?= ucfirst(str_replace('_', ' ', $t)) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="seccion">
            <div class="table-wrap"><table>
                <thead><tr><th>Tipo</th><th>IP</th><th>Usuario</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach ($eventos as $e):
                    $c = str_contains($e['tipo'],'fallido') || str_contains($e['tipo'],'error') || str_contains($e['tipo'],'bloqueada') ? 'error' : 'ok';
                    $label = ucfirst(str_replace('_', ' ', $e['tipo'])); ?>
                <tr>
                    <td><span class="badge badge-<?= $c ?>"><?= htmlspecialchars($label) ?></span></td>
                    <td class="td-mono"><?= htmlspecialchars($e['ip']) ?></td>
                    <td class="td-name"><?= htmlspecialchars($e['usuario'] ?? '—') ?></td>
                    <td class="td-mono"><?= $e['fecha'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$eventos): ?><tr><td colspan="4" class="vacio">Sin eventos registrados</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </main>
</div>
</body>
</html>
