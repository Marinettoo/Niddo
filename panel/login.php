<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
require_once '../config/db.php';
$sin_usuarios = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0;
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Niddo</title>
    <?php include '_head.php'; ?>
</head>
<body class="login-body">
<div class="login-box">
    <div class="login-brand">
        <img src="../web/niddo%20Logo%20completo.png" alt="Niddo">
    </div>

    <?php if ($sin_usuarios): ?>
        <div class="login-heading">Bienvenido</div>
        <div class="login-sub">Crea tu cuenta de administrador para empezar.</div>
        <div class="login-setup-badge">primera configuración</div>
        <?php if ($error): ?><div class="login-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" action="../api/setup.php">
            <div class="field"><label>Nombre</label><input type="text" name="nombre" required autofocus placeholder="Tu nombre"></div>
            <div class="field"><label>Email</label><input type="email" name="email" required placeholder="admin@niddo.local"></div>
            <div class="field"><label>Contraseña</label><input type="password" name="password" required></div>
            <button class="login-btn" type="submit">Crear administrador</button>
        </form>
    <?php else: ?>
        <div class="login-heading">Accede al panel</div>
        <div class="login-sub">Introduce tus credenciales para continuar.</div>
        <?php if ($error): ?><div class="login-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" action="../api/auth.php">
            <div class="field"><label>Email</label><input type="email" name="email" required autofocus></div>
            <div class="field"><label>Contraseña</label><input type="password" name="password" required></div>
            <button class="login-btn" type="submit">Entrar</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
