<?php
require_once __DIR__ . '/../../config/Database2.php';
require_once __DIR__ . '/../../controllers/UserController.php';

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: Login.php');
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
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-clear">
    <div style="position:fixed;top:12px;right:12px;z-index:9999">
        <a href="index.php" style="display:inline-block;padding:8px 12px;background:rgba(0,0,0,0.12);color:#fff;border-radius:6px;text-decoration:none;font-weight:600">Voltar</a>
    </div>
    <div class="app-shell">
        <div class="weather-card form-blue">
            <h1>Registrar</h1>
            <form method="POST" class="register-form">
                <input type="text" name="nome" placeholder="Nome" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <button type="submit">Registrar</button>
            </form>
        </div>
    </div>
</body>
</html>
