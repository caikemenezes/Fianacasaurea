<?php

declare(strict_types=1);

require_once __DIR__ . '/icons.php';

/**
 * Equivalente ao src/app/(app)/layout.tsx do sistema original: sidebar + cabeçalho
 * em volta de toda página protegida. Cada page.php futuro chama layout_topo() no
 * início e layout_rodape() no fim, em vez de duplicar HTML de sidebar/header.
 */

/**
 * Ordem e ícones batendo com o menu real do sistema original
 * (src/components/sidebar-nav.tsx) — Configurações não entra aqui porque lá
 * também não entra na sidebar (fica só no menu de perfil, ver layout_rodape()).
 * Escola e Reforma da Casa nunca existiram como páginas reais no original
 * (eram só planejamento no Índice) — por isso não estão aqui.
 */
const ITENS_MENU = [
    ['chave' => 'dashboard', 'label' => 'Dashboard', 'icone' => 'dashboard', 'href' => '/index.php', 'pronto' => true],
    ['chave' => 'simulador', 'label' => 'Simulador', 'icone' => 'simulador', 'href' => '/simulador.php', 'pronto' => true],
    ['chave' => 'receitas', 'label' => 'Receitas', 'icone' => 'receitas', 'href' => '/receitas.php', 'pronto' => true],
    ['chave' => 'contas', 'label' => 'Contas do Mês', 'icone' => 'contas', 'href' => '/contas.php', 'pronto' => true],
    ['chave' => 'metas', 'label' => 'Metas', 'icone' => 'metas', 'href' => '/metas.php', 'pronto' => true],
    ['chave' => 'prioridades', 'label' => 'Prioridades', 'icone' => 'relogio', 'href' => '/prioridades.php', 'pronto' => true],
    ['chave' => 'dividas', 'label' => 'Dívidas', 'icone' => 'dividas', 'href' => '/dividas.php', 'pronto' => true],
    ['chave' => 'investimentos', 'label' => 'Investimentos e Reservas', 'icone' => 'carteira', 'href' => '/investimentos.php', 'pronto' => true],
    ['chave' => 'relatorios', 'label' => 'Relatórios', 'icone' => 'relatorios', 'href' => '/relatorios.php', 'pronto' => true],
];

function pagina_pronta(string $chave): bool
{
    foreach (ITENS_MENU as $item) {
        if ($item['chave'] === $chave) {
            return $item['pronto'];
        }
    }
    return false;
}

/** Abre um <a> se o módulo já existe, ou um <span> desabilitado ("em breve") caso contrário. */
function tag_link(string $href, string $chaveModulo, string $classe = ''): string
{
    $classeAtributo = htmlspecialchars($classe, ENT_QUOTES, 'UTF-8');
    if (pagina_pronta($chaveModulo)) {
        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . $classeAtributo . '">';
    }
    return '<span class="' . $classeAtributo . ' desabilitado" title="Módulo ainda não construído">';
}

function fechar_tag_link(string $chaveModulo): string
{
    return pagina_pronta($chaveModulo) ? '</a>' : '</span>';
}

/** Extrai a "chave" do módulo a partir do link salvo nos alertas (ex: "/contas.php" -> "contas"). */
function chave_da_rota(string $rota): string
{
    return pathinfo($rota, PATHINFO_FILENAME);
}

function layout_topo(array $usuarioAtual, string $paginaAtiva, string $tituloPagina): void
{
    ?><!DOCTYPE html>
<html lang="pt-BR" data-tema="escuro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8') ?> — Áurea</title>
<link rel="icon" href="/favicon.png" type="image/png">
<link rel="stylesheet" href="/assets/dashboard.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar" id="sidebar">
      <button type="button" class="sidebar-toggle" id="botaoColapsar" aria-label="Recolher menu" title="Recolher menu">
        <span class="sidebar-toggle-icone" id="iconeColapsar"><?= icone('colapsar') ?></span>
      </button>
      <div class="sidebar-topo">
        <span class="marca">
          <span class="marca-icone"><img src="/assets/porquinho.png" alt=""></span>
          <span class="marca-texto">
            <strong>Áurea</strong>
            <small>Familiar</small>
          </span>
        </span>
      </div>
      <span class="rotulo-secao-menu">Menu principal</span>
      <nav class="menu">
        <?php foreach (ITENS_MENU as $item): ?>
          <?php $ativo = $item['chave'] === $paginaAtiva; ?>
          <?php if ($item['pronto']): ?>
            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" class="item-menu<?= $ativo ? ' ativo' : '' ?>">
              <?= icone($item['icone']) ?><span class="item-menu-texto"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php else: ?>
            <span class="item-menu desabilitado" title="Módulo ainda não construído">
              <?= icone($item['icone']) ?><span class="item-menu-texto"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-rodape">
        <div class="chip-perfil">
          <span class="avatar"><?= mb_strtoupper(mb_substr($usuarioAtual['nome'], 0, 1)) ?></span>
          <span class="chip-perfil-texto">
            <strong><?= htmlspecialchars($usuarioAtual['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars($usuarioAtual['email'], ENT_QUOTES, 'UTF-8') ?></small>
          </span>
        </div>
      </div>
    </aside>

    <div class="overlay-gaveta" id="overlayGaveta"></div>

    <div class="conteudo">
      <header class="topo-pagina">
        <button type="button" class="botao-hamburguer" id="botaoHamburguer" aria-label="Abrir menu"><?= icone('menu') ?></button>
        <h1><?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="acoes-topo">
<?php
}

function layout_rodape(array $usuarioAtual): void
{
?>
          <div class="chip-perfil-topo" id="chipPerfilTopo">
            <button type="button" class="botao-chip-perfil" id="botaoChipPerfil">
              <span class="avatar avatar-pequeno"><?= mb_strtoupper(mb_substr($usuarioAtual['nome'], 0, 1)) ?></span>
              <span class="chip-perfil-texto">
                <strong><?= htmlspecialchars($usuarioAtual['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars($usuarioAtual['email'], ENT_QUOTES, 'UTF-8') ?></small>
              </span>
            </button>
            <div class="menu-perfil-dropdown" id="menuPerfilDropdown">
              <a href="/configuracoes.php" class="item-menu"><?= icone('config') ?><span class="item-menu-texto">Configurações</span></a>
              <a href="/logout.php" class="link-sair"><?= icone('sair') ?><span class="item-menu-texto">Sair</span></a>
            </div>
          </div>
        </div>
      </header>
      <main class="area-principal">
<?php
}

function fechar_layout(): void
{
?>
      </main>
    </div>
  </div>
  <script>
    (function () {
      var sidebar = document.getElementById('sidebar');
      var botaoColapsar = document.getElementById('botaoColapsar');
      var botaoHamburguer = document.getElementById('botaoHamburguer');
      var overlay = document.getElementById('overlayGaveta');

      var recolhido = localStorage.getItem('sidebarRecolhida') === '1';
      if (recolhido && window.innerWidth > 860) sidebar.classList.add('recolhida');

      botaoColapsar.addEventListener('click', function () {
        sidebar.classList.toggle('recolhida');
        localStorage.setItem('sidebarRecolhida', sidebar.classList.contains('recolhida') ? '1' : '0');
      });

      function abrirGaveta() {
        sidebar.classList.add('gaveta-aberta');
        overlay.classList.add('visivel');
      }
      function fecharGaveta() {
        sidebar.classList.remove('gaveta-aberta');
        overlay.classList.remove('visivel');
      }
      botaoHamburguer.addEventListener('click', abrirGaveta);
      overlay.addEventListener('click', fecharGaveta);
      sidebar.querySelectorAll('a.item-menu').forEach(function (a) {
        a.addEventListener('click', fecharGaveta);
      });

      var botaoChipPerfil = document.getElementById('botaoChipPerfil');
      var chipPerfilTopo = document.getElementById('chipPerfilTopo');
      botaoChipPerfil.addEventListener('click', function (e) {
        e.stopPropagation();
        chipPerfilTopo.classList.toggle('aberto');
      });
      document.addEventListener('click', function () {
        chipPerfilTopo.classList.remove('aberto');
      });
    })();
  </script>
  <script src="/assets/graficos.js"></script>
  <script src="/assets/info-icone.js"></script>
  <script src="/assets/mascara-moeda.js"></script>
  <script src="/assets/secao-recolhivel.js"></script>
</body>
</html>
<?php
}
