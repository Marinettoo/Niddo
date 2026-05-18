<?php
require '_session.php';
require_once '../config/db.php';

$uid = (int)$_SESSION['user_id'];
$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$msg      = '';
$msg_tipo = 'ok';

$stmt = $pdo->prepare("SELECT nombre, email, pwd_hash FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = $_POST['actual'] ?? '';
    $nueva  = $_POST['nueva'] ?? '';
    $rep    = $_POST['repetir'] ?? '';

    if (!password_verify($actual, $user['pwd_hash'])) {
        $pdo->prepare("INSERT INTO events (tipo, ip, user_id) VALUES ('password_error', ?, ?)")->execute([$ip, $uid]);
        $msg = 'La contraseña actual no es correcta.';
        $msg_tipo = 'error';
    } elseif (strlen($nueva) < 6) {
        $msg = 'La contraseña nueva debe tener al menos 6 caracteres.';
        $msg_tipo = 'error';
    } elseif ($nueva !== $rep) {
        $msg = 'Las contraseñas nuevas no coinciden.';
        $msg_tipo = 'error';
    } else {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET pwd_hash = ? WHERE id = ?")->execute([$hash, $uid]);
        $pdo->prepare("INSERT INTO events (tipo, ip, user_id) VALUES ('password_cambiada', ?, ?)")->execute([$ip, $uid]);
        $msg = 'Contraseña actualizada correctamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Niddo — Mi perfil</title>
    <?php include '_head.php'; ?>
</head>
<body>
<div class="layout">
    <?php include '_nav.php'; ?>
    <main class="main">
        <div class="page-header">
            <div class="page-title">Mi perfil</div>
            <div class="page-sub">Datos de tu cuenta</div>
        </div>

        <?php if ($msg): ?><div class="msg-<?= $msg_tipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <div class="seccion">
            <div class="seccion-header"><div class="seccion-title">Datos</div></div>
            <div class="table-wrap"><table>
                <tbody>
                    <tr><th>Nombre</th><td class="td-name"><?= htmlspecialchars($user['nombre']) ?></td></tr>
                    <tr><th>Email</th><td class="td-mono"><?= htmlspecialchars($user['email']) ?></td></tr>
                    <tr><th>Roles</th><td><?= htmlspecialchars(implode(', ', $_SESSION['roles'] ?? [])) ?></td></tr>
                </tbody>
            </table></div>
        </div>

        <div class="seccion">
            <div class="seccion-header"><div class="seccion-title">Cambiar contraseña</div></div>
            <form method="POST" class="form-card">
                <div class="field"><label>Contraseña actual</label><input type="password" name="actual" required></div>
                <div class="field"><label>Nueva contraseña</label><input type="password" name="nueva" required minlength="6"></div>
                <div class="field"><label>Repetir nueva</label><input type="password" name="repetir" required minlength="6"></div>
                <button class="btn btn-primary" type="submit">Actualizar contraseña</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
