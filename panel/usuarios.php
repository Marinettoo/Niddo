<?php
require '_session.php';
require_once '../config/db.php';

if (!in_array('Admin', $_SESSION['roles'] ?? [])) { header('Location: dashboard.php'); exit; }

$yo        = (int)$_SESSION['user_id'];
$es_primer_admin = ($yo === 1);

$msg      = '';
$msg_tipo = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (nombre, email, pwd_hash, estado) VALUES (?, ?, ?, 'activo')")
            ->execute([trim($_POST['nombre']), trim($_POST['email']), $hash]);
        $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")
            ->execute([$pdo->lastInsertId(), (int)$_POST['role_id']]);
        $msg = 'Usuario creado correctamente.';

    } elseif ($accion === 'cambiar_rol') {
        $target_id   = (int)$_POST['target_id'];
        $nuevo_rol   = (int)$_POST['role_id'];

        // No se puede editar a uno mismo
        if ($target_id === $yo) {
            $msg = 'No puedes cambiar tu propio rol.';
            $msg_tipo = 'error';
        } else {
            // Comprobar si es una degradacion (el usuario objetivo es Admin y el nuevo rol no lo es)
            $rol_actual_stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
            $rol_actual_stmt->execute([$target_id]);
            $rol_actual = (int)$rol_actual_stmt->fetchColumn();

            $admin_role_id = $pdo->query("SELECT id FROM roles WHERE nombre='Admin'")->fetchColumn();
            $es_degradacion = ($rol_actual == $admin_role_id && $nuevo_rol != $admin_role_id);

            if ($es_degradacion && !$es_primer_admin) {
                $msg = 'Solo el administrador principal puede degradar a otros administradores.';
                $msg_tipo = 'error';
            } else {
                $pdo->prepare("UPDATE user_roles SET role_id = ? WHERE user_id = ?")
                    ->execute([$nuevo_rol, $target_id]);
                $msg = 'Rol actualizado correctamente.';
            }
        }
    }
}

$usuarios = $pdo->query("
    SELECT u.id, u.nombre, u.email, u.estado, ur.role_id, r.nombre AS rol
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    ORDER BY u.id
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

        <?php if ($msg): ?><div class="msg-<?= $msg_tipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <form method="POST" class="form-card">
            <input type="hidden" name="accion" value="crear">
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
                <thead><tr><th>#</th><th>Nombre</th><th>Email</th><th>Rol actual</th><th>Estado</th><th>Cambiar rol</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td class="td-mono"><?= $u['id'] ?><?= $u['id']==1 ? ' *' : '' ?></td>
                    <td class="td-name"><?= htmlspecialchars($u['nombre']) ?></td>
                    <td class="td-mono"><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['rol'] ?? '—') ?></td>
                    <td><?php $c = $u['estado']==='activo' ? 'ok' : 'error'; ?>
                        <span class="badge badge-<?= $c ?>"><span class="badge-dot"></span><?= $u['estado'] ?></span></td>
                    <td>
                        <?php if ($u['id'] === $yo): ?>
                            <span style="color:#aaa; font-size:12px;">eres tú</span>
                        <?php else: ?>
                            <?php
                            $admin_role_id = 1;
                            $es_admin_objetivo = ($u['role_id'] == $admin_role_id);
                            $puede_cambiar = !$es_admin_objetivo || $es_primer_admin;
                            ?>
                            <?php if ($puede_cambiar): ?>
                            <form method="POST" style="display:flex; gap:6px; align-items:center;">
                                <input type="hidden" name="accion" value="cambiar_rol">
                                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                <select name="role_id">
                                    <?php foreach ($todos_roles as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= $r['id']==$u['role_id']?'selected':'' ?>>
                                            <?= htmlspecialchars($r['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary" type="submit">Guardar</button>
                            </form>
                            <?php else: ?>
                                <span style="color:#aaa; font-size:12px;">solo el admin principal puede degradar</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
        <p style="font-size:12px; color:#888; margin-top:6px;">* administrador principal</p>
    </main>
</div>
</body>
</html>
