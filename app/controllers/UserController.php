<?php
require_once __DIR__ . '/../model/UserModel.php';

class UserController{
    private $userModel;

    public function __construct($pdo) {
        $this->userModel = new UserModel($pdo);
    }

    public function register($nome, $email, $senha){
        $this->userModel->cadastro($nome, $email, $senha);
    }
}
?>
