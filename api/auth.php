<?php
session_start();
require_once '../config/db.php';

//guarda la ip del usuario en la variable $ip
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; //$_SERVER es una varianle superglobal como $_POST

// --- Login de usuario (panel web) ---
if (isset($_POST['email'], $_POST['password'])) {


//si fallamos mas de 5 veces, se bloqueará esa IP por 5 minutos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM events
        WHERE tipo = 'login_fallido' AND ip = ?
        AND fecha >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmt->execute([$ip]);
    if ($stmt->fetchColumn() >= 5) {
        $pdo->prepare("INSERT INTO events (tipo, ip) VALUES ('ip_bloqueada', ?)")->execute([$ip]);
        header('Location: ../panel/login.php?error=' . urlencode('IP bloqueada por exceso de intentos. Espera 10 minutos.'));
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($_POST['password'], $user['pwd_hash'])) {
        $pdo->prepare("INSERT INTO events (tipo, ip) VALUES ('login_fallido', ?)")->execute([$ip]);
        header('Location: ../panel/login.php?error=' . urlencode('Credenciales incorrectas'));
        exit;
    }

    if ($user['estado'] !== 'activo') {
        header('Location: ../panel/login.php?error=' . urlencode('Usuario inactivo'));
        exit;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nombre']  = $user['nombre'];

    $pdo->prepare("INSERT INTO events (tipo, ip, user_id) VALUES ('login_ok', ?, ?)")->execute([$ip, $user['id']]);
    header('Location: ../panel/dashboard.php');
    exit;
}

// valida el token de python
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
