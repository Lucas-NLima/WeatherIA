<?php
require_once __DIR__ . '/../../config/Database2.php';
require_once __DIR__ . '/../../controllers/UserController.php';

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: Dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $userController = new UserController($pdo);
    $userController->register($nome, $email, $senha);

    header("Location: Login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrar</title>
    <link rel="stylesheet" href="../../../clima/style.css">
    <style>
        body{display:flex;justify-content:center;align-items:center;height:100vh}
        .weather-card{max-width:420px;width:100%;padding:20px}
        .weather-card form input[type="text"],
        .weather-card form input[type="email"],
        .weather-card form input[type="password"]{
            width:100%;padding:10px;border-radius:6px;border:1px solid #ddd;margin:8px 0;box-sizing:border-box
        }
        .weather-card form button{width:100%;padding:10px;border-radius:6px;border:none;background:var(--accent-dark,#2b6cb0);color:#fff;font-weight:600;cursor:pointer}
    </style>
</head>
<body class="bg-clear">
    <div class="weather-card">
    <form method="POST">
        <input type="text" name="nome" placeholder="Nome" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <button type="submit">Registrar</button>
    </form>
    </div>
</body>
</html>
