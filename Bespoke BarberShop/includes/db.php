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

        // Garantir que a tabela de soft-hide exista antes de qualquer SELECT que a use
        // Essa tabela é usada para ocultar agendamentos apenas para o usuário (sem deletar do sistema)
        $sqlCreateAOC = "
            CREATE TABLE IF NOT EXISTS `AgendamentoOcultoCliente` (
                `Cliente_id` INT NOT NULL,
                `Agendamento_id` INT NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`Cliente_id`, `Agendamento_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        // Executa a criação de forma segura; se falhar, não interrompe o site, apenas registra no log
        if (!$this->mysqli->query($sqlCreateAOC)) {
            error_log('Falha ao garantir tabela AgendamentoOcultoCliente: ' . $this->mysqli->error);
        }
    }

    public function getConexao() { // Permite o acesso da conexão
        return $this->mysqli;
    }
}
