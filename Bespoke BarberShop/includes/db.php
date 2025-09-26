<?php

// Define timezone
date_default_timezone_set('America/Sao_Paulo');

// Constantes de conexão
define('BD_SERVIDOR', 'localhost');
define('BD_USUARIO', 'root');
define('BD_SENHA', '');
define('BD_BANCO', 'mydb');

class Banco {
    protected $mysqli; // Armazena conexão com o bd

    public function __construct() { //Chamado quando o objeto de Banco é criado
        $this->conectar();
    }

    private function conectar() {
        $this->mysqli = new mysqli(BD_SERVIDOR, BD_USUARIO, BD_SENHA, BD_BANCO); // Abre conexão com o MySQL

        if ($this->mysqli->connect_error) {
            die("Erro na conexão: " . $this->mysqli->connect_error);
        }
    }

    public function getConexao() { // Permite o acesso da conexão
        return $this->mysqli;
    }
}
