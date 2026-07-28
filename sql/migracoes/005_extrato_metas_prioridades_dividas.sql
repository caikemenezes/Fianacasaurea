-- Migração 005: extrato reconhece pagamentos/aportes pra Metas, Prioridades
-- (necessidade) e Dívidas também, mesmo padrão de palavra-chave já usado em
-- Contas do Mês e Receitas (identificador_extrato).

ALTER TABLE meta
    ADD COLUMN identificador_extrato VARCHAR(160) NULL;

ALTER TABLE necessidade
    ADD COLUMN identificador_extrato VARCHAR(160) NULL;

ALTER TABLE divida
    ADD COLUMN identificador_extrato VARCHAR(160) NULL;

ALTER TABLE transacao_importada
    ADD COLUMN meta_id INT NULL AFTER conta_mes_id,
    ADD COLUMN necessidade_id INT NULL AFTER meta_id,
    ADD COLUMN divida_id INT NULL AFTER necessidade_id,
    ADD CONSTRAINT fk_transacao_meta FOREIGN KEY (meta_id) REFERENCES meta (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_transacao_necessidade FOREIGN KEY (necessidade_id) REFERENCES necessidade (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_transacao_divida FOREIGN KEY (divida_id) REFERENCES divida (id) ON DELETE SET NULL;
