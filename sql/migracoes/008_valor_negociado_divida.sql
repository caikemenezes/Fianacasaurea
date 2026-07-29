-- Migração 008: valor negociado (com desconto) que o credor está oferecendo
-- pra quitar a dívida de uma vez — separado do valor_atual real, pra dar pra
-- comparar os dois e decidir se vale a pena pagar à vista com desconto.

ALTER TABLE divida
    ADD COLUMN valor_negociado DECIMAL(12,2) NULL;
