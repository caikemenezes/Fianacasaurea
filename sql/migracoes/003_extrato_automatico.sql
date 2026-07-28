-- Migração 003: importação automática de extrato bancário (Gmail + CSV do Nubank).
--
-- Contexto: o sistema busca no Gmail o e-mail "Extrato da sua conta do Nubank",
-- lê o CSV anexado e casa cada transação com uma Conta do Mês (saída/despesa)
-- ou Receita (entrada) já cadastrada, usando valor e uma palavra-chave opcional.

ALTER TABLE conta_mes
    ADD COLUMN identificador_extrato VARCHAR(160) NULL AFTER numero_parcelas;

ALTER TABLE receita
    ADD COLUMN identificador_extrato VARCHAR(160) NULL AFTER categoria;

CREATE TABLE transacao_importada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    identificador_externo VARCHAR(64) NOT NULL,
    data DATE NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    descricao VARCHAR(500) NOT NULL,
    status ENUM('PENDENTE','CONFIRMADA','IGNORADA') NOT NULL DEFAULT 'PENDENTE',
    conta_mes_id INT NULL,
    receita_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_transacao_familia_identificador (familia_id, identificador_externo),
    CONSTRAINT fk_transacao_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE,
    CONSTRAINT fk_transacao_conta_mes FOREIGN KEY (conta_mes_id) REFERENCES conta_mes (id) ON DELETE SET NULL,
    CONSTRAINT fk_transacao_receita FOREIGN KEY (receita_id) REFERENCES receita (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
