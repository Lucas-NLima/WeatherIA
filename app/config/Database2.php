<?php
// Conexão separada para Cloudling (Database2) — evita conflitos com Database principal
$dsn = 'mysql:host=localhost;dbname=cloudling;charset=utf8';
$usuario = 'root';
$senha = '';
try {
    $pdo = new PDO($dsn, $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Falha na conexão — mostre mensagem mínima
    echo "Erro na conexão (Database2): " . $e->getMessage();
}

?>
