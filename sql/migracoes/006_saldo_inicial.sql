-- Migração 006: saldo inicial da conta, por família — o extrato importado só
-- cobre a partir de um certo mês, então "Saldo disponível"/"Saldo do período"
-- (calculados só pela soma do extrato) não refletiam o que a família já tinha
-- guardado ANTES do histórico do extrato começar. Cadastrado uma vez em
-- Configurações, soma por cima do saldo calculado pelo extrato.

ALTER TABLE familia
    ADD COLUMN saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
    ADD COLUMN saldo_inicial_data DATE NULL;
