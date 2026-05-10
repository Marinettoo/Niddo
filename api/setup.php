<?php
session_start();
require_once '../config/db.php';

// Solo funciona si no hay usuarios
if ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) {
    header('Location: ../panel/login.php');
    exit;
}

if (!isset($_POST['nombre'], $_POST['email'], $_POST['password'])) {
    header('Location: ../panel/login.php');
    exit;
}

// Crear rol Admin si no existe
$pdo->query("INSERT IGNORE INTO roles (id, nombre) VALUES (1, 'Admin')");

// Crear el usuario administrador
$hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (nombre, email, pwd_hash, estado) VALUES (?, ?, ?, 'activo')");
$stmt->execute([trim($_POST['nombre']), trim($_POST['email']), $hash]);
$user_id = $pdo->lastInsertId();

// Asignar rol Admin
$pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, 1)")->execute([$user_id]);

// Iniciar sesión directamente
$_SESSION['user_id'] = $user_id;
$_SESSION['nombre']  = trim($_POST['nombre']);
$_SESSION['roles']   = ['Admin'];

header('Location: ../panel/dashboard.php');
exit;
