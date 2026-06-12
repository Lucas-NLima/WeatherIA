<?php
require_once __DIR__ . '/../../config/Database2.php';

session_start();

// Limpa variáveis de sessão
$_SESSION = [];

// Remove cookie de sessão
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destrói sessão e redireciona ao login
session_destroy();
header('Location: Login.php');
exit;

?>
