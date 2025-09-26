USE `mydb`;

-- Inserindo Unidades
INSERT INTO Unidade (endereco, cidade, estado, cep, nomeUnidade) VALUES
('Rua das Flores, 123', 'São Paulo', 'SP', '01001-000', 'Barbearia Central'),
('Av. Brasil, 456', 'Rio de Janeiro', 'RJ', '20040-002', 'Barbearia Zona Sul');

-- Inserindo Serviços
INSERT INTO Servico (nomeServico, descricaoServico, duracaoPadrao, precoServico) VALUES
('Corte Masculino', 'Corte de cabelo tradicional masculino', 30, 50.00),
('Barba', 'Design e aparo da barba', 20, 30.00),
('Corte + Barba', 'Pacote completo com corte e barba', 50, 75.00);

-- Inserindo Clientes
INSERT INTO Cliente (nomeCliente, emailCliente, telefoneCliente, senhaCliente) VALUES
('João Silva', 'joao@email.com', '(11) 99999-0001', 'senha123'),
('Carlos Mendes', 'carlos@email.com', '(21) 98888-0002', 'senha123');

-- Inserindo Administradores
INSERT INTO Administrador (nomeAdmin, emailAdmin, telefoneAdmin, senhaAdmin, Unidade_idUnidade) VALUES
('Lucas Admin', 'lucas@admin.com', '(11) 97777-0003', 'admin123', 1),
('Marcos Admin', 'marcos@admin.com', '(21) 96666-0004', 'admin123', 2);

-- Inserindo Barbeiros
INSERT INTO Barbeiro (nomeBarbeiro, emailBarbeiro, telefoneBarbeiro, senhaBarbeiro, statusBarbeiro, dataRetorno, Unidade_idUnidade) VALUES
('Pedro Barbeiro', 'pedro@barbearia.com', '(11) 95555-0005', 'barbeiro123', 'Ativo', NULL, 1),
('Thiago Barbeiro', 'thiago@barbearia.com', '(21) 94444-0006', 'barbeiro123', 'Ativo', NULL, 2);

-- Associando Serviços às Unidades
INSERT INTO Unidade_has_Servico (Unidade_idUnidade, Servico_idServico) VALUES
(1, 1), (1, 2), (1, 3),
(2, 1), (2, 3);

-- Associando Serviços aos Barbeiros
INSERT INTO Barbeiro_has_Servico (Barbeiro_idBarbeiro, Servico_idServico) VALUES
(1, 1), (1, 2), (1, 3),
(2, 1), (2, 3);

-- Inserindo Agendamentos
INSERT INTO Agendamento (data, hora, statusAgendamento, Cliente_idCliente, Barbeiro_idBarbeiro, Unidade_idUnidade) VALUES
('2025-09-26', '10:00:00', 'Agendado', 1, 1, 1),
('2025-09-26', '11:00:00', 'Agendado', 2, 2, 2);

-- Associando Serviços aos Agendamentos
INSERT INTO Agendamento_has_Servico (Agendamento_idAgendamento, Servico_idServico, precoFinal, tempoEstimado) VALUES
(1, 1, 50.00, 30),
(2, 3, 75.00, 50);
