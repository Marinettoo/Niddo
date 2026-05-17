<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$es_admin = in_array('Admin', $_SESSION['roles'] ?? []);
$uid = $_SESSION['user_id'];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO devices (nombre, so, token, user_id, repositorio_id) VALUES (?, ?, ?, ?, ?)")
        ->execute([trim($_POST['nombre']), $_POST['so'], $token, $uid, $_POST['repositorio_id'] ?: null]);
    $msg = "Dispositivo creado · token: $token";
}

if ($es_admin) {
    $dispositivos = $pdo->query("
        SELECT d.id, d.nombre, d.so, d.token, u.nombre AS usuario, r.nombre AS repo
        FROM devices d
        LEFT JOIN users u ON u.id = d.user_id
        LEFT JOIN repositorios r ON r.id = d.repositorio_id
        ORDER BY d.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $s = $pdo->prepare("
        SELECT d.id, d.nombre, d.so, d.token, u.nombre AS usuario, r.nombre AS repo
        FROM devices d
        LEFT JOIN users u ON u.id = d.user_id
        LEFT JOIN repositorios r ON r.id = d.repositorio_id
        WHERE d.user_id = ?
        ORDER BY d.id DESC
    ");
    $s->execute([$uid]);
    $dispositivos = $s->fetchAll(PDO::FETCH_ASSOC);
}

$repositorios = $pdo->query("SELECT * FROM repositorios")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Niddo — Dispositivos</title>
    <?php include '_head.php'; ?>
</head>
<body>
<div class="layout">
    <?php include '_nav.php'; ?>
    <main class="main">
        <div class="page-header">
            <div class="page-title">Dispositivos</div>
            <div class="page-sub">Gestiona los equipos conectados</div>
        </div>

        <?php if ($msg): ?><div class="msg-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <form method="POST" class="form-card">
            <div class="field"><label>Nombre</label><input type="text" name="nombre" required placeholder="Nombre del equipo"></div>
            <div class="field"><label>Sistema operativo</label>
                <select name="so"><option>Windows</option><option>Linux</option><option>macOS</option></select>
            </div>
            <div class="field"><label>Repositorio</label>
                <select name="repositorio_id">
                    <option value="">— ninguno —</option>
                    <?php foreach ($repositorios as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Crear dispositivo</button>
        </form>

        <div class="aviso-info">
            <strong>Aviso:</strong> El agente necesita tener python 3 instalado en el equipo cliente.
            Si no lo tienes, descargalo desde <a href="https://www.python.org/downloads/" target="_blank">aquí</a>. Y ejecuta el agente con él.
        </div>

        <div class="seccion">
            <div class="seccion-header"><div class="seccion-title">Dispositivos registrados</div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>#</th><th>Nombre</th><th>SO</th><th>Usuario</th><th>Token</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($dispositivos as $d): ?>
                <tr>
                    <td class="td-mono"><?= $d['id'] ?></td>
                    <td class="td-name"><?= htmlspecialchars($d['nombre']) ?></td>
                    <td><?= htmlspecialchars($d['so']) ?></td>
                    <td><?= htmlspecialchars($d['usuario'] ?? '—') ?></td>
                    <td class="td-token"><?= substr($d['token'],0,20) ?>…</td>
                    <td><a href="generar_agente.php?id=<?= $d['id'] ?>" class="action-link">descargar agente (.py)</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$dispositivos): ?><tr><td colspan="6" class="vacio">Sin dispositivos registrados</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </main>
</div>
</body>
</html>
