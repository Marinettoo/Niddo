<?php
session_start();
require_once '../config/db.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// --- Login de usuario (panel web) ---
if (isset($_POST['email'], $_POST['password'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($_POST['password'], $user['pwd_hash'])) {
        $pdo->prepare("INSERT INTO events (tipo, ip) VALUES ('login_fallido', ?)")->execute([$ip]);
        die('Credenciales incorrectas');
    }

    if ($user['estado'] !== 'activo') {
        die('Usuario inactivo');
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nombre']  = $user['nombre'];

    $pdo->prepare("INSERT INTO events (tipo, ip, user_id) VALUES ('login_ok', ?, ?)")->execute([$ip, $user['id']]);
    header('Location: ../panel/dashboard.php');
    exit;
}

// --- Validación de token de dispositivo (agente Python) ---
if (isset($_POST['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE token = ?");
    $stmt->execute([$_POST['token']]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        http_response_code(401);
        die('Token invalido');
    }

    echo $device['id'] . ',' . $device['repositorio_id'];
    exit;
}

die('Peticion incorrecta');
