<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$stmt = $pdo->prepare("SELECT r.nombre FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?");
$stmt->execute([$_SESSION['user_id']]);
if (!in_array('Admin', array_column($stmt->fetchAll(PDO::FETCH_ASSOC),'nombre'))) die('Acceso denegado');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (nombre, email, pwd_hash, estado) VALUES (?, ?, ?, 'activo')")
        ->execute([trim($_POST['nombre']), trim($_POST['email']), $hash]);
    $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")
        ->execute([$pdo->lastInsertId(), (int)$_POST['role_id']]);
    $msg = 'Usuario creado correctamente.';
}

$usuarios = $pdo->query("
    SELECT u.id, u.nombre, u.email, u.estado, GROUP_CONCAT(r.nombre) AS roles
    FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id
    GROUP BY u.id ORDER BY u.id
")->fetchAll(PDO::FETCH_ASSOC);

$todos_roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Niddo — Usuarios</title>
    <?php include '_head.php'; ?>
</head>
<body>
<div class="layout">
    <?php include '_nav.php'; ?>
    <main class="main">
        <div class="page-header">
            <div class="page-title">Usuarios</div>
            <div class="page-sub">Control de acceso al panel</div>
        </div>

        <?php if ($msg): ?><div class="msg-ok"><?= $msg ?></div><?php endif; ?>

        <form method="POST" class="form-card">
            <div class="field"><label>Nombre</label><input type="text" name="nombre" required placeholder="Nombre completo"></div>
            <div class="field"><label>Email</label><input type="email" name="email" required placeholder="usuario@dominio.com"></div>
            <div class="field"><label>Contraseña</label><input type="password" name="password" required></div>
            <div class="field"><label>Rol</label>
                <select name="role_id">
                    <?php foreach ($todos_roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Añadir usuario</button>
        </form>

        <div class="seccion">
            <div class="seccion-header"><div class="seccion-title">Usuarios del sistema</div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>#</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td class="td-mono"><?= $u['id'] ?></td>
                    <td class="td-name"><?= htmlspecialchars($u['nombre']) ?></td>
                    <td class="td-mono"><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['roles'] ?? '—') ?></td>
                    <td><?php $c=$u['estado']==='activo'?'ok':'error'; ?>
                        <span class="badge badge-<?= $c ?>"><span class="badge-dot"></span><?= $u['estado'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </main>
</div>
</body>
</html>
