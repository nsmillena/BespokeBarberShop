-- Migração incremental (segura) para recursos: reset de senha (barbeiro), bloqueios, folga semanal, férias e metas/comissões
-- Execute no schema atual (ex.: `mydb`). Ajuste o nome do schema se necessário.

-- 1) Flags de senha no Barbeiro
-- Se já existirem, o MySQL pode acusar erro de coluna duplicada; ignore com segurança
ALTER TABLE `Barbeiro`
  ADD COLUMN `deveTrocarSenha` TINYINT(1) NOT NULL DEFAULT 0 AFTER `dataRetorno`,
  ADD COLUMN `senhaTempExpiraEm` DATE NULL AFTER `deveTrocarSenha`;

-- 2) Índices úteis no Agendamento (ignore erro se já existirem)
CREATE INDEX `idx_agendamento_unidade_data` ON `Agendamento` (`Unidade_idUnidade`, `data`);
CREATE INDEX `idx_agendamento_barbeiro_data` ON `Agendamento` (`Barbeiro_idBarbeiro`, `data`);
CREATE INDEX `idx_agendamento_status` ON `Agendamento` (`statusAgendamento`);

-- 3) Tabelas novas (criadas se não existirem)
CREATE TABLE IF NOT EXISTS `BloqueioHorario` (
  `idBloqueio` INT NOT NULL AUTO_INCREMENT,
  `Barbeiro_idBarbeiro` INT NOT NULL,
  `data` DATE NOT NULL,
  `horaInicio` TIME NOT NULL,
  `horaFim` TIME NOT NULL,
  `motivo` VARCHAR(255) NULL,
  `criadoEm` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idBloqueio`),
  KEY `idx_bloqueio_barbeiro_data` (`Barbeiro_idBarbeiro`,`data`,`horaInicio`,`horaFim`),
  CONSTRAINT `fk_Bloqueio_Barbeiro` FOREIGN KEY (`Barbeiro_idBarbeiro`) REFERENCES `Barbeiro` (`idBarbeiro`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `FolgaSemanal` (
  `idFolga` INT NOT NULL AUTO_INCREMENT,
  `Barbeiro_idBarbeiro` INT NOT NULL,
  `weekday` TINYINT NOT NULL,
  `inicio` DATE NOT NULL,
  `fim` DATE NULL,
  `criadoEm` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idFolga`),
  KEY `idx_folga_barbeiro_periodo` (`Barbeiro_idBarbeiro`,`inicio`,`fim`,`weekday`),
  CONSTRAINT `fk_Folga_Barbeiro` FOREIGN KEY (`Barbeiro_idBarbeiro`) REFERENCES `Barbeiro` (`idBarbeiro`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `FeriasBarbeiro` (
  `idFerias` INT NOT NULL AUTO_INCREMENT,
  `Barbeiro_idBarbeiro` INT NOT NULL,
  `inicio` DATE NOT NULL,
  `fim` DATE NOT NULL,
  `motivo` VARCHAR(255) NULL,
  `criadoEm` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idFerias`),
  KEY `idx_ferias_barbeiro_periodo` (`Barbeiro_idBarbeiro`,`inicio`,`fim`),
  CONSTRAINT `fk_Ferias_Barbeiro` FOREIGN KEY (`Barbeiro_idBarbeiro`) REFERENCES `Barbeiro` (`idBarbeiro`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `Meta` (
  `idMeta` INT NOT NULL AUTO_INCREMENT,
  `Unidade_idUnidade` INT NOT NULL,
  `periodicidade` ENUM('Semanal','Mensal') NOT NULL,
  `inicio` DATE NOT NULL,
  `fim` DATE NOT NULL,
  `base` ENUM('Receita','Atendimentos') NOT NULL,
  `objetivoValor` DECIMAL(10,2) NOT NULL,
  `percentualFlat` DECIMAL(5,2) NOT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idMeta`),
  CONSTRAINT `fk_Meta_Unidade` FOREIGN KEY (`Unidade_idUnidade`) REFERENCES `Unidade` (`idUnidade`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `MetaBarbeiro` (
  `idMetaBarbeiro` INT NOT NULL AUTO_INCREMENT,
  `Meta_idMeta` INT NOT NULL,
  `Barbeiro_idBarbeiro` INT NOT NULL,
  `objetivoOverride` DECIMAL(10,2) NULL,
  PRIMARY KEY (`idMetaBarbeiro`),
  UNIQUE KEY `uk_meta_barbeiro` (`Meta_idMeta`,`Barbeiro_idBarbeiro`),
  CONSTRAINT `fk_MetaBarbeiro_Meta` FOREIGN KEY (`Meta_idMeta`) REFERENCES `Meta` (`idMeta`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_MetaBarbeiro_Barbeiro` FOREIGN KEY (`Barbeiro_idBarbeiro`) REFERENCES `Barbeiro` (`idBarbeiro`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ComissaoLancamento` (
  `idLancamento` INT NOT NULL AUTO_INCREMENT,
  `Meta_idMeta` INT NOT NULL,
  `Barbeiro_idBarbeiro` INT NOT NULL,
  `periodoInicio` DATE NOT NULL,
  `periodoFim` DATE NOT NULL,
  `baseRealizado` DECIMAL(10,2) NOT NULL,
  `objetivoUsado` DECIMAL(10,2) NOT NULL,
  `atingiu` TINYINT(1) NOT NULL,
  `percentualAplicado` DECIMAL(5,2) NOT NULL,
  `receitaRealizada` DECIMAL(10,2) NOT NULL,
  `valorComissao` DECIMAL(10,2) NOT NULL,
  `status` ENUM('Pendente','Pago') NOT NULL DEFAULT 'Pendente',
  `criadoEm` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idLancamento`),
  KEY `idx_lanc_meta_barb` (`Meta_idMeta`,`Barbeiro_idBarbeiro`),
  CONSTRAINT `fk_Lanc_Meta` FOREIGN KEY (`Meta_idMeta`) REFERENCES `Meta` (`idMeta`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_Lanc_Barbeiro` FOREIGN KEY (`Barbeiro_idBarbeiro`) REFERENCES `Barbeiro` (`idBarbeiro`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `AuditLog` (
  `idLog` INT NOT NULL AUTO_INCREMENT,
  `quando` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atorId` INT NULL,
  `papel` ENUM('admin','barbeiro','cliente') NULL,
  `evento` VARCHAR(64) NOT NULL,
  `alvoTipo` VARCHAR(32) NULL,
  `alvoId` INT NULL,
  `ip` VARCHAR(45) NULL,
  `detalhes` VARCHAR(255) NULL,
  PRIMARY KEY (`idLog`)
) ENGINE=InnoDB;

-- 4) Tabela de reset de senha (utilizada pelos fluxos de "Esqueci a senha")
CREATE TABLE IF NOT EXISTS `PasswordReset` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `papel` ENUM('admin','barbeiro','cliente') NOT NULL,
  `token` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_email_papel` (`email`,`papel`)
) ENGINE=InnoDB;

-- Observações:
-- - Se algum comando de índice/coluna acusar duplicidade, pode ignorar.
-- - Após a migração, recarregue as telas de Admin (Bloqueios/Escolas/Metas) e o login do Barbeiro.
