-- Migração 007: saldo real da conta, lido direto do anexo OFX de cada e-mail
-- de extrato (campo LEDGERBAL/BALAMT, o próprio banco informa) — mais preciso
-- que pedir pro usuário digitar um saldo inicial manualmente, e atualiza
-- sozinho toda vez que "Verificar extrato" processa um e-mail novo.

CREATE TABLE saldo_extrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    data DATE NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saldo_extrato_familia_data (familia_id, data),
    CONSTRAINT fk_saldo_extrato_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
