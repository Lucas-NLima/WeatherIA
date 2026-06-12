<?php
require_once __DIR__ . '/../../config/Database2.php';

session_start();

// If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: Dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $sql = "SELECT * FROM user WHERE email = :email AND senha = :senha";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':senha', $senha);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($senha == $user['senha']) {
            $_SESSION['user_id'] = $user['id'] ?? null;
            $_SESSION['user_name'] = $user['nome'] ?? '';
            header("Location: index.php");
            exit;
        } else {
            $error = "Senha errada.";
        }
    } else {
        $error = "Usuário não existe. Deseja se <a href='Register.php'>cadastrar</a>?";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body{display:flex;justify-content:center;align-items:center;height:100vh}
        .weather-card{max-width:420px;width:100%;padding:20px}
        .weather-card form input[type="email"],
        .weather-card form input[type="password"]{
            width:100%;padding:10px;border-radius:6px;border:1px solid #ddd;margin:8px 0;box-sizing:border-box
        }
        .weather-card form button{width:100%;padding:10px;border-radius:6px;border:none;background:var(--accent-dark,#2b6cb0);color:#fff;font-weight:600;cursor:pointer}
    </style>
</head>
<body class="bg-clear">
    <div class="weather-card">
    <?php if (!empty($error)): ?>
        <p style="color:red;padding:12px"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <button type="submit">Login</button>
    </form>
    <p style="padding-bottom:12px"><a href="Register.php">Não tem uma conta? Cadastre-se</a></p>
    </div>
</body>
</html>
