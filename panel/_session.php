<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// si estamos inactivos x 5 min, expulsado.
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 300) {
    require_once __DIR__ . '/../config/db.php';
    $pdo->prepare("UPDATE users SET estado='inactivo' WHERE id=?")->execute([$_SESSION['user_id']]);
    session_destroy();
    header('Location: login.php?error=' . urlencode('Sesión cerrada por inactividad')); //urlencode pone texto dentro de una url sin romperla, quita las tildes y to eso.
    exit;
}

$_SESSION['last_activity'] = time();
