-- Migração 002: valor da parcela separado do valor total, em Contas do Mês.
--
-- Contexto: pra contas parceladas (financiamento da casa, por exemplo), a
-- coluna "valor" passa a guardar o valor TOTAL financiado, e "valor_parcela"
-- guarda o valor de cada parcela mensal — mesma separação que Dívidas já
-- tinha (valor_original vs valor_parcela). Contas sem parcelamento continuam
-- usando só "valor" (valor mensal normal), sem preencher valor_parcela.

ALTER TABLE conta_mes
    ADD COLUMN valor_parcela DECIMAL(12,2) NULL AFTER numero_parcelas;
