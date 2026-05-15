<?php

class Database {

    private $host = "localhost";
    private $dbname = "weather_ai";
    private $user = "root";
    private $pass = "";

    public function conectar() {

        try {

            $conexao = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8",
                $this->user,
                $this->pass
            );

            $conexao->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            return $conexao;

        } catch(PDOException $erro) {

            die("Erro na conexão: " . $erro->getMessage());

        }

    }

}