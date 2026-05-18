<?php
require '_session.php';
require_once '../config/db.php';

$es_admin = in_array('Admin', $_SESSION['roles'] ?? []);
$uid = $_SESSION['user_id'];
$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$msg = '';
$msg_tipo = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO devices (nombre, so, token, user_id, repositorio_id) VALUES (?, ?, ?, ?, ?)")
            ->execute([trim($_POST['nombre']), $_POST['so'], $token, $uid, $_POST['repositorio_id'] ?: null]);
        $msg = "Dispositivo creado · token: $token";

    } elseif ($accion === 'borrar') {
        $target = (int)($_POST['id'] ?? 0);
        if ($es_admin) {
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ?");
            $stmt->execute([$target]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$target, $uid]);
        }
        if (!$stmt->fetchColumn()) {
            $pdo->prepare("INSERT INTO events (tipo, ip, user_id) VALUES ('dispositivo_denegado', ?, ?)")->execute([$ip, $uid]);
            $msg = "No tienes permisos para borrar ese dispositivo.";
            $msg_tipo = 'error';
        } else {
            // Borrar archivos fisicos. ON DELETE CASCADE limpia backups, files y device_folders.
            $dir = "/var/niddo/backups/" . $target;
            if (is_dir($dir)) shell_exec('rm -rf ' . escapeshellarg($dir));
            $pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([$target]);
            $pdo->prepare("INSERT INTO events (tipo, ip, user_id) VALUES ('dispositivo_borrado', ?, ?)")->execute([$ip, $uid]);
            $msg = "Dispositivo borrado correctamente.";
        }
    }
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

        <?php if ($msg): ?><div class="msg-<?= $msg_tipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

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
                <thead><tr><th>Nombre</th><th>SO</th><th>Usuario</th><th>Token</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($dispositivos as $d): ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($d['nombre']) ?></td>
                    <td><?= htmlspecialchars($d['so']) ?></td>
                    <td><?= htmlspecialchars($d['usuario'] ?? '—') ?></td>
                    <td class="td-token"><?= substr($d['token'],0,20) ?>…</td>
                    <td style="display:flex; gap:12px;">
                        <a href="generar_agente.php?id=<?= $d['id'] ?>" class="action-link">descargar agente (.py)</a>
                        <form method="POST" class="action-form" onsubmit="return confirm('¿Borrar el dispositivo «<?= htmlspecialchars($d['nombre'], ENT_QUOTES) ?>» y TODAS sus copias de seguridad? Esta accion no se puede deshacer.');">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button type="submit" class="action-link-danger">borrar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$dispositivos): ?><tr><td colspan="5" class="vacio">Sin dispositivos registrados</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </main>
</div>
</body>
</html>
