-- Fundação do Áurea (versão PHP do Sistema Financeiro Familiar original)
-- Equivalente às tabelas Familia / Usuario / Sessao do schema.prisma original.
-- Toda tabela de dados financeiros tem familia_id, seguindo a mesma regra de
-- isolamento por família descrita no vault "Finanças Php Memoria".

CREATE TABLE IF NOT EXISTS familia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- Credenciais do Gmail pro extrato automático (Contas do Mês > Verificar
    -- extrato), uma por família — cada família puxa o extrato da própria
    -- conta bancária, sem misturar com a de outra família. Senha de app
    -- guardada cifrada (ver src/cripto.php), nunca em texto puro.
    imap_email VARCHAR(190) NULL,
    imap_senha_app_cifrada VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_email (email),
    CONSTRAINT fk_usuario_familia FOREIGN KEY (familia_id) REFERENCES familia (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessao (
    id CHAR(64) PRIMARY KEY,
    usuario_id INT NOT NULL,
    expira_em DATETIME NOT NULL,
    csrf_token CHAR(64) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessao_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contador de tentativas de login erradas, pra bloqueio temporário por e-mail
-- (proteção contra força bruta — ver src/auth.php, bloqueado_por_tentativas()).
CREATE TABLE IF NOT EXISTS tentativa_login_falha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tentativa_email (email, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelas dos módulos financeiros, alinhadas ao schema.prisma real do sistema
-- original (D:\DOCUMENTOS\SISTEMAS\Finanças\Sistema Financeiro\prisma\schema.prisma).
-- Recriadas em 2026-07-23 pra bater com os campos reais de cada módulo (estavam
-- mínimas, só pro Dashboard funcionar) — seguro, nenhuma tinha dado de usuário ainda.

DROP TABLE IF EXISTS meta_item_necessario;
DROP TABLE IF EXISTS meta_cotacao;
DROP TABLE IF EXISTS necessidade;
DROP TABLE IF EXISTS meta;
DROP TABLE IF EXISTS divida;
DROP TABLE IF EXISTS investimento;
DROP TABLE IF EXISTS conta_mes;
DROP TABLE IF EXISTS receita;
DROP TABLE IF EXISTS familia_membro;

CREATE TABLE familia_membro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    parentesco VARCHAR(60) NOT NULL,
    data_nascimento DATE NULL,
    observacoes VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_familia_membro_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE receita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    nome VARCHAR(160) NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    valor_previsto DECIMAL(12,2) NOT NULL,
    valor_recebido DECIMAL(12,2) NULL,
    data_prevista DATE NOT NULL,
    data_recebimento DATE NULL,
    categoria VARCHAR(60) NULL,
    identificador_extrato VARCHAR(160) NULL,
    recorrente TINYINT(1) NOT NULL DEFAULT 0,
    conta_bancaria VARCHAR(120) NULL,
    status ENUM('PREVISTO','RECEBIDO') NOT NULL DEFAULT 'PREVISTO',
    observacao VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receita_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE,
    INDEX idx_receita_familia_data (familia_id, data_prevista)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conta_mes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    nome VARCHAR(160) NOT NULL,
    categoria VARCHAR(60) NOT NULL,
    subcategoria VARCHAR(60) NULL,
    valor DECIMAL(12,2) NOT NULL,
    vencimento DATE NOT NULL,
    forma_pagamento VARCHAR(60) NULL,
    conta_bancaria VARCHAR(120) NULL,
    tipo ENUM('FIXA','VARIAVEL') NOT NULL DEFAULT 'FIXA',
    recorrente_mensal TINYINT(1) NOT NULL DEFAULT 1,
    numero_parcelas INT NULL,
    identificador_extrato VARCHAR(160) NULL,
    valor_parcela DECIMAL(12,2) NULL,
    parcelas_pagas INT NOT NULL DEFAULT 0,
    status ENUM('PENDENTE','ATRASADA','PAGA') NOT NULL DEFAULT 'PENDENTE',
    paga_em DATE NULL,
    observacoes VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conta_mes_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE,
    INDEX idx_conta_mes_familia_venc (familia_id, vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    familia_membro_id INT NULL,
    nome VARCHAR(160) NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    categoria VARCHAR(60) NULL,
    valor_estimado DECIMAL(12,2) NOT NULL,
    valor_guardado DECIMAL(12,2) NOT NULL DEFAULT 0,
    data_desejada DATE NULL,
    prioridade ENUM('URGENTE','ALTA','MEDIA','BAIXA') NOT NULL DEFAULT 'MEDIA',
    status ENUM('PLANEJADA','EM_ANDAMENTO','CONCLUIDA','CANCELADA') NOT NULL DEFAULT 'PLANEJADA',
    observacoes VARCHAR(1000) NULL,
    links_pesquisados VARCHAR(500) NULL,
    orcamentos_encontrados VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_meta_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE,
    CONSTRAINT fk_meta_membro FOREIGN KEY (familia_membro_id) REFERENCES familia_membro (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sub-tabelas da página de detalhe da meta (/metas-detalhe.php) — cotações
-- comparadas por item + checklist do que a meta vai precisar.
CREATE TABLE meta_cotacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meta_id INT NOT NULL,
    item VARCHAR(160) NOT NULL,
    fornecedor VARCHAR(160) NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    link VARCHAR(500) NULL,
    escolhida TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_meta_cotacao_meta FOREIGN KEY (meta_id) REFERENCES meta (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meta_item_necessario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meta_id INT NOT NULL,
    nome VARCHAR(160) NOT NULL,
    valor_estimado DECIMAL(12,2) NOT NULL,
    concluido TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_meta_item_meta FOREIGN KEY (meta_id) REFERENCES meta (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE necessidade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    familia_membro_id INT NULL,
    item VARCHAR(160) NOT NULL,
    pessoa_nome VARCHAR(120) NULL,
    categoria VARCHAR(60) NOT NULL,
    prioridade ENUM('URGENTE','ALTA','MEDIA','BAIXA') NOT NULL DEFAULT 'MEDIA',
    valor_estimado DECIMAL(12,2) NOT NULL,
    valor_guardado DECIMAL(12,2) NOT NULL DEFAULT 0,
    mes_planejado DATE NOT NULL,
    status ENUM('PLANEJADA','EM_ANDAMENTO','CONCLUIDA','CANCELADA') NOT NULL DEFAULT 'PLANEJADA',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_necessidade_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE,
    CONSTRAINT fk_necessidade_membro FOREIGN KEY (familia_membro_id) REFERENCES familia_membro (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE divida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    nome VARCHAR(160) NOT NULL,
    credor VARCHAR(160) NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    valor_original DECIMAL(12,2) NOT NULL,
    valor_atual DECIMAL(12,2) NOT NULL,
    juros DECIMAL(6,2) NULL,
    numero_parcelas INT NULL,
    valor_parcela DECIMAL(12,2) NULL,
    vencimento DATE NULL,
    parcelas_pagas INT NOT NULL DEFAULT 0,
    status ENUM('EM_DIA','ATRASADA','QUITADA') NOT NULL DEFAULT 'EM_DIA',
    prioridade ENUM('URGENTE','ALTA','MEDIA','BAIXA') NOT NULL DEFAULT 'MEDIA',
    possibilidade_negociacao TINYINT(1) NOT NULL DEFAULT 0,
    observacoes VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_divida_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE investimento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    nome VARCHAR(160) NOT NULL,
    objetivo VARCHAR(60) NOT NULL,
    instituicao VARCHAR(120) NULL,
    tipo VARCHAR(60) NULL,
    valor_aplicado DECIMAL(12,2) NOT NULL,
    valor_atual DECIMAL(12,2) NOT NULL,
    aporte_mensal DECIMAL(12,2) NULL,
    data_ultimo_aporte DATE NULL,
    prazo DATE NULL,
    liquidez VARCHAR(60) NULL,
    rentabilidade_informada VARCHAR(60) NULL,
    observacoes VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_investimento_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extrato bancário importado automaticamente do Gmail (CSV anexado no e-mail
-- "Extrato da sua conta do Nubank") — ver sql/migracoes/003_extrato_automatico.sql
-- pra decisão de design completa.
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
