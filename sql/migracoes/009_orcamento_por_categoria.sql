-- Migração 009: planilha de orçamento mensal por categoria, dentro de
-- Contas do Mês — quanto a família reserva por mês pra cada coisa (água,
-- luz, alimentação, etc.), agrupado em categorias fixas. Diferente de
-- Contas do Mês (que rastreia contas reais com vencimento), isso é só um
-- valor de referência/planejamento por categoria, editável livremente.

CREATE TABLE orcamento_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    subcategoria VARCHAR(80) NOT NULL,
    valor_reservado DECIMAL(12,2) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_orcamento_familia_subcategoria (familia_id, subcategoria),
    CONSTRAINT fk_orcamento_familia FOREIGN KEY (familia_id) REFERENCES familia (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
