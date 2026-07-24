-- Migração 001: contador de parcelas em Contas do Mês.
--
-- schema.sql não serve pra atualizar um banco que já tem dados (ele começa
-- com DROP TABLE). Migrações como esta são pra rodar manualmente, uma vez,
-- num banco já existente (local ou produção), via phpMyAdmin > aba SQL.
--
-- Contexto: financiamentos/parcelamentos longos (ex: financiamento da casa)
-- cadastrados em Contas do Mês agora podem ter um número fixo de parcelas,
-- com o vencimento avançando um mês a cada parcela paga (ver contas-processar.php).

ALTER TABLE conta_mes
    ADD COLUMN numero_parcelas INT NULL AFTER recorrente_mensal,
    ADD COLUMN parcelas_pagas INT NOT NULL DEFAULT 0 AFTER numero_parcelas;
