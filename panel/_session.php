<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Cierre de sesion por inactividad (5 minutos)
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 300) {
    require_once __DIR__ . '/../config/db.php';
    $pdo->prepare("UPDATE users SET estado='inactivo' WHERE id=?")->execute([$_SESSION['user_id']]);
    session_destroy();
    header('Location: login.php?error=' . urlencode('Sesión cerrada por inactividad'));
    exit;
}

$_SESSION['last_activity'] = time();
