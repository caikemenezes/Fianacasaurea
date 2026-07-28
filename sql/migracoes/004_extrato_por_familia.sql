-- Migração 004: credenciais do Gmail (extrato automático) por família, não mais
-- globais no .env — cada família cadastra o próprio e-mail/senha de app em
-- Configurações, pra famílias diferentes puxarem o extrato de contas bancárias
-- diferentes sem misturar dados.

ALTER TABLE familia
    ADD COLUMN imap_email VARCHAR(190) NULL,
    ADD COLUMN imap_senha_app_cifrada VARCHAR(500) NULL;
