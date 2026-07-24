(function () {
  'use strict';

  var raiz = document.getElementById('simulador-raiz');
  if (!raiz) return;

  var rendaInicial = parseFloat(raiz.dataset.rendaInicial) || 0;
  var itensIniciais = JSON.parse(raiz.dataset.itens || '[]');

  var CATEGORIAS_PADRAO = ['Contas do Mês', 'Dívidas', 'Metas', 'Prioridades', 'Investimentos'];
  var COR_POR_CATEGORIA_PADRAO = {
    'Contas do Mês': '#3987e5',
    'Dívidas': '#d95926',
    'Metas': '#199e70',
    'Prioridades': '#c98500',
    'Investimentos': '#d55181',
  };
  var COR_PERSONALIZADA_PRINCIPAL = '#008300';
  var COR_PERSONALIZADA_OUTRAS = '#898781';
  var COR_SOBRA = '#f0c13a';
  var COR_FALTANDO = '#e66767';

  var DESCRICAO_POR_CATEGORIA = {
    'Contas do Mês': 'Contas pendentes e atrasadas cadastradas em Contas do Mês. Já vêm marcadas, mas você pode desmarcar ou mudar o valor pra testar cenários.',
    'Dívidas': 'Valor da parcela de cada dívida ativa. Use o seletor de parcelas pra simular adiantar mais de uma parcela no mês. Se uma dívida não tem parcela definida, o valor sugerido é zero.',
    'Metas': 'Quanto seria preciso guardar por mês em cada meta pra chegar na data desejada.',
    'Prioridades': 'Quanto seria preciso guardar por mês em cada prioridade pra chegar no mês planejado.',
    'Investimentos': 'Aporte mensal planejado de cada investimento cadastrado.',
  };

  var estado = {
    renda: rendaInicial,
    itens: itensIniciais.map(function (item) {
      return Object.assign({}, item, {
        valor: item.valorSugerido,
        incluido: item.valorSugerido > 0,
        parcelas: 1,
      });
    }),
    categoriasPersonalizadas: [],
    calculado: false,
  };

  function gerarId(prefixo) {
    return prefixo + '-' + Math.random().toString(36).slice(2, 10);
  }

  function formatarMoeda(v) {
    var n = Number(v) || 0;
    var negativo = n < 0;
    n = Math.abs(n);
    var partes = n.toFixed(2).split('.');
    var inteiro = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (negativo ? '-' : '') + 'R$ ' + inteiro + ',' + partes[1];
  }

  function escaparHtml(s) {
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function corDaCategoria(categoria) {
    if (COR_POR_CATEGORIA_PADRAO[categoria]) return COR_POR_CATEGORIA_PADRAO[categoria];
    var indice = estado.categoriasPersonalizadas.indexOf(categoria);
    return indice === 0 ? COR_PERSONALIZADA_PRINCIPAL : COR_PERSONALIZADA_OUTRAS;
  }

  function todasCategorias() {
    return CATEGORIAS_PADRAO.concat(estado.categoriasPersonalizadas);
  }

  function totalDestinado() {
    return estado.itens.filter(function (i) { return i.incluido; }).reduce(function (s, i) { return s + i.valor; }, 0);
  }

  function fatiasAlocacao() {
    var td = totalDestinado();
    var saldoRestante = estado.renda - td;
    var fatias = todasCategorias().map(function (categoria) {
      var valor = estado.itens.filter(function (i) { return i.categoria === categoria && i.incluido; }).reduce(function (s, i) { return s + i.valor; }, 0);
      return { categoria: categoria, valor: valor, cor: corDaCategoria(categoria) };
    }).filter(function (f) { return f.valor > 0; });

    if (saldoRestante >= 0) {
      fatias.push({ categoria: 'Sobra', valor: saldoRestante, cor: COR_SOBRA });
    } else {
      fatias.push({ categoria: 'Faltando', valor: -saldoRestante, cor: COR_FALTANDO });
    }
    return fatias;
  }

  function template(strings) {
    var values = Array.prototype.slice.call(arguments, 1);
    return strings.reduce(function (acc, s, i) { return acc + s + (values[i] !== undefined ? values[i] : ''); }, '');
  }

  var SVG_INFO = '<svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="7.5" x2="12.01" y2="7.5"/></svg>';

  function renderInfoIcone(texto) {
    return '<div class="info-icone-wrapper">'
      + '<button type="button" class="botao-icone-info" aria-label="Sobre esta seção" aria-expanded="false">' + SVG_INFO + '</button>'
      + '<div class="info-balao" role="tooltip" hidden>' + escaparHtml(texto) + '</div>'
      + '</div>';
  }

  function renderItem(item) {
    var parcelasHtml = '';
    if (item.valorParcela) {
      parcelasHtml = '<label class="texto-suave" style="display:flex;align-items:center;gap:0.35rem;font-size:0.8rem;' + (item.incluido ? '' : 'opacity:0.5') + '" title="Quantas parcelas pagar de uma vez neste mês">'
        + 'Parcelas <input type="number" min="1" ' + (item.parcelasRestantes ? 'max="' + item.parcelasRestantes + '"' : '') + ' step="1" value="' + item.parcelas + '" ' + (item.incluido ? '' : 'disabled') + ' data-acao="parcelas" data-id="' + item.id + '" class="campo campo-tabela" style="width:3.5rem;text-align:center">'
        + (item.parcelasRestantes ? '/' + item.parcelasRestantes : '') + '</label>';
    }
    return '<div class="linha-flex item-bloco linha-flex-compacta" style="gap:0.75rem">'
      + '<label class="campo-checkbox" style="flex:1;min-width:0">'
      + '<input type="checkbox" ' + (item.incluido ? 'checked' : '') + ' data-acao="alternar" data-id="' + item.id + '">'
      + '<span style="opacity:' + (item.incluido ? 1 : 0.5) + '">' + escaparHtml(item.nome) + '</span></label>'
      + parcelasHtml
      + '<input type="number" step="0.01" value="' + item.valor + '" ' + (item.incluido ? '' : 'disabled') + ' data-acao="valor" data-id="' + item.id + '" class="campo campo-tabela" style="width:8rem;text-align:right;opacity:' + (item.incluido ? 1 : 0.5) + '">'
      + '<button type="button" data-acao="remover" data-id="' + item.id + '" class="link-acao link-perigo">Remover</button>'
      + '</div>';
  }

  function renderFormNovoItem(categoria) {
    return '<form data-acao="adicionar-item" data-categoria="' + escaparHtml(categoria) + '" class="linha-flex linha-flex-compacta" style="gap:0.5rem">'
      + '<input name="nome" placeholder="Adicionar item (ex: Conserto do carro)" class="campo campo-tabela" style="flex:1">'
      + '<input name="valor" type="number" step="0.01" placeholder="Valor" class="campo campo-tabela" style="width:7rem;text-align:right">'
      + '<button type="submit" class="botao botao-pequeno">+ Adicionar</button></form>';
  }

  function renderBarraAlocacao() {
    var fatias = fatiasAlocacao();
    var totalBase = Math.max(estado.renda, totalDestinado(), 1);
    var segmentos = fatias.map(function (f) {
      var pct = Math.max((f.valor / totalBase) * 100, f.valor > 0 ? 0.5 : 0);
      return '<div title="' + escaparHtml(f.categoria) + ': ' + formatarMoeda(f.valor) + '" style="flex:' + pct + ' 0 0%;background:' + f.cor + ';height:100%"></div>';
    }).join('');
    var legenda = fatias.map(function (f) {
      return '<span class="legenda-item"><span class="legenda-marcador" style="background:' + f.cor + '"></span>' + escaparHtml(f.categoria) + ' <span class="texto-suave">' + formatarMoeda(f.valor) + '</span></span>';
    }).join('');
    return '<div style="display:flex;height:22px;border-radius:999px;overflow:hidden;background:rgba(255,255,255,0.05)">' + segmentos + '</div>'
      + '<div class="grafico-legenda" style="flex-wrap:wrap;margin-top:0.75rem">' + legenda + '</div>';
  }

  function renderCategoriaPersonalizada(categoria) {
    var itensDoProjeto = estado.itens.filter(function (i) { return i.categoria === categoria; });
    var totalProjeto = itensDoProjeto.filter(function (i) { return i.incluido; }).reduce(function (s, i) { return s + i.valor; }, 0);
    var itensHtml = itensDoProjeto.length === 0
      ? '<p class="item-vazio">Nenhum item ainda — adicione o que esse projeto vai precisar abaixo.</p>'
      : itensDoProjeto.map(renderItem).join('');

    return '<div class="cartao pilha-pequena" style="border-left:4px solid ' + corDaCategoria(categoria) + '" data-categoria-card="' + escaparHtml(categoria) + '">'
      + '<div class="linha-flex"><h2 class="cartao-titulo" style="display:flex;align-items:center;gap:0.5rem">' + escaparHtml(categoria) + ' <span class="selo selo-neutro">Projeto</span></h2>'
      + '<button type="button" data-acao="remover-categoria" data-categoria="' + escaparHtml(categoria) + '" class="link-acao link-perigo">Remover simulação</button></div>'
      + itensHtml
      + renderFormNovoItem(categoria)
      + '<div class="linha-flex" style="border-top:1px solid var(--vidro-borda);padding-top:0.6rem">'
      + '<p class="cartao-hero-rotulo" style="margin:0">Total do projeto</p>'
      + '<p class="cartao-hero-valor" style="font-size:1.3rem">' + formatarMoeda(totalProjeto) + '</p></div>'
      + '</div>';
  }

  function renderCategoriaPadrao(categoria) {
    var itensDaCategoria = estado.itens.filter(function (i) { return i.categoria === categoria; });
    var subtotal = itensDaCategoria.filter(function (i) { return i.incluido; }).reduce(function (s, i) { return s + i.valor; }, 0);
    var itensHtml = itensDaCategoria.length === 0
      ? '<p class="item-vazio">Nada aqui ainda — adicione um item abaixo pra simular.</p>'
      : itensDaCategoria.map(renderItem).join('');

    return '<div class="cartao pilha-pequena">'
      + renderInfoIcone(DESCRICAO_POR_CATEGORIA[categoria] || '')
      + '<div class="linha-flex"><h2 class="cartao-titulo">' + escaparHtml(categoria) + '</h2>'
      + '<p class="texto-suave" style="font-size:0.85rem;margin:0">Subtotal: <strong>' + formatarMoeda(subtotal) + '</strong></p></div>'
      + itensHtml
      + renderFormNovoItem(categoria)
      + '</div>';
  }

  function renderResultado() {
    if (!estado.calculado) return '';
    var td = totalDestinado();
    var saldoRestante = estado.renda - td;
    var pct = estado.renda > 0 ? Math.min(100, (td / estado.renda) * 100) : 0;
    var totalBase = Math.max(estado.renda, td, 1);
    var linhas = fatiasAlocacao().map(function (f) {
      return '<tr><td><span class="legenda-marcador" style="background:' + f.cor + ';margin-right:0.5rem"></span>' + escaparHtml(f.categoria) + '</td>'
        + '<td>' + formatarMoeda(f.valor) + '</td><td>' + Math.round((f.valor / totalBase) * 100) + '%</td></tr>';
    }).join('');

    return '<div class="cartao pilha-pequena">'
      + renderInfoIcone('Resumo final: quanto sobra ou falta, e a porcentagem da renda que vai pra cada categoria — inclusive projetos personalizados que você criou.')
      + '<h2 class="cartao-titulo">Resultado da simulação</h2>'
      + '<div class="linha-flex"><p class="texto-suave" style="margin:0">Renda simulada</p><p style="margin:0;font-weight:700">' + formatarMoeda(estado.renda) + '</p></div>'
      + '<div class="linha-flex"><p class="texto-suave" style="margin:0">Total destinado</p><p style="margin:0;font-weight:700">' + formatarMoeda(td) + ' (' + Math.round(pct) + '%)</p></div>'
      + '<div class="linha-flex"><p class="texto-suave" style="margin:0">Saldo restante</p><p style="margin:0;font-weight:700;color:' + (saldoRestante >= 0 ? 'var(--sucesso)' : 'var(--perigo)') + '">' + formatarMoeda(saldoRestante) + '</p></div>'
      + '<div class="tabela-wrap"><table class="tabela"><thead><tr><th>Para onde vai</th><th>Valor</th><th>% da renda</th></tr></thead><tbody>' + linhas + '</tbody></table></div>'
      + '</div>';
  }

  function render() {
    var td = totalDestinado();
    var saldoRestante = estado.renda - td;
    var pct = estado.renda > 0 ? Math.min(100, (td / estado.renda) * 100) : 0;

    var html = ''
      + '<div class="pilha">'
      + '<div class="hero-coluna">'
      + '<div class="cartao cartao-hero" style="min-width:260px">'
      + renderInfoIcone('Comece digitando uma renda hipotética. Os cartões abaixo recalculam tudo em cima desse valor.')
      + '<p class="cartao-hero-rotulo">Renda simulada</p>'
      + '<input type="number" step="0.01" value="' + estado.renda + '" data-acao="renda" class="campo" style="font-size:1.7rem;font-weight:700;border:none;background:transparent;padding:0.3rem 0">'
      + '</div>'
      + '<div class="cartao cartao-hero" style="min-width:260px">'
      + renderInfoIcone('Renda simulada menos tudo que está marcado (incluído) nas categorias abaixo.')
      + '<p class="cartao-hero-rotulo">Saldo restante</p>'
      + '<p class="cartao-hero-valor" style="color:' + (saldoRestante >= 0 ? 'var(--sucesso)' : 'var(--perigo)') + '">' + formatarMoeda(saldoRestante) + '</p>'
      + '<p class="cartao-hero-sub">Destinado: <strong>' + formatarMoeda(td) + '</strong> (' + Math.round(pct) + '% da renda)</p>'
      + '</div></div>'

      + '<div class="cartao pilha-pequena">'
      + renderInfoIcone('Barra mostrando como a renda simulada se divide entre as categorias marcadas, mais o que sobra (ou falta). Passe o mouse numa fatia pra ver o valor exato.')
      + '<h2 class="cartao-titulo">Para onde vai a renda simulada</h2>'
      + renderBarraAlocacao()
      + '</div>'

      + '<button type="button" data-acao="restaurar" class="botao botao-pequeno" style="align-self:flex-start">Restaurar valores sugeridos</button>'

      + '<div class="cartao cartao-cta pilha-pequena">'
      + '<div><p class="cartao-cta-titulo">Simular um projeto</p>'
      + '<p class="cartao-cta-texto">Reforma, viagem, compra grande — dê um nome ao projeto, liste os itens com o valor de cada um, e veja o total que vai precisar.</p></div>'
      + '<form data-acao="nova-categoria" class="cartao linha-flex linha-flex-compacta" style="gap:0.5rem">'
      + '<input name="nome" placeholder="Simular algo novo (ex: Reforma da cozinha, Viagem pra praia)" class="campo" style="flex:1">'
      + '<button type="submit" class="botao">+ Nova simulação</button></form>'
      + '</div>'

      + estado.categoriasPersonalizadas.map(renderCategoriaPersonalizada).join('')
      + CATEGORIAS_PADRAO.map(renderCategoriaPadrao).join('')

      + '<div class="cartao" style="align-items:center;text-align:center">'
      + '<button type="button" data-acao="calcular" class="botao" style="font-size:1rem;padding:0.75rem 2.5rem">Calcular simulação</button></div>'

      + renderResultado()
      + '</div>';

    raiz.innerHTML = html;
  }

  raiz.addEventListener('input', function (e) {
    var acao = e.target.dataset.acao;
    if (acao === 'renda') {
      estado.renda = Number(e.target.value) || 0;
    } else if (acao === 'valor') {
      var item = estado.itens.find(function (i) { return i.id === e.target.dataset.id; });
      if (item) item.valor = Number(e.target.value) || 0;
    } else if (acao === 'parcelas') {
      var item2 = estado.itens.find(function (i) { return i.id === e.target.dataset.id; });
      if (item2 && item2.valorParcela) {
        item2.parcelas = Math.max(1, Number(e.target.value) || 1);
        item2.valor = item2.valorParcela * item2.parcelas;
      }
    }
  });

  raiz.addEventListener('change', function (e) {
    if (e.target.dataset.acao === 'alternar') {
      var item = estado.itens.find(function (i) { return i.id === e.target.dataset.id; });
      if (item) { item.incluido = !item.incluido; render(); }
    } else if (e.target.dataset.acao === 'valor' || e.target.dataset.acao === 'renda' || e.target.dataset.acao === 'parcelas') {
      render();
    }
  });

  raiz.addEventListener('click', function (e) {
    var alvo = e.target.closest('[data-acao]');
    if (!alvo) return;
    var acao = alvo.dataset.acao;

    if (acao === 'remover') {
      estado.itens = estado.itens.filter(function (i) { return i.id !== alvo.dataset.id; });
      render();
    } else if (acao === 'remover-categoria') {
      estado.categoriasPersonalizadas = estado.categoriasPersonalizadas.filter(function (c) { return c !== alvo.dataset.categoria; });
      estado.itens = estado.itens.filter(function (i) { return i.categoria !== alvo.dataset.categoria; });
      render();
    } else if (acao === 'restaurar') {
      estado.itens = estado.itens
        .filter(function (i) { return i.id.indexOf('item-') !== 0; })
        .map(function (i) { return Object.assign({}, i, { valor: i.valorSugerido, incluido: i.valorSugerido > 0, parcelas: 1 }); });
      estado.categoriasPersonalizadas = [];
      render();
    } else if (acao === 'calcular') {
      estado.calculado = true;
      render();
    }
  });

  raiz.addEventListener('submit', function (e) {
    e.preventDefault();
    var form = e.target;
    var acao = form.dataset.acao;

    if (acao === 'adicionar-item') {
      var nome = form.elements['nome'].value.trim();
      var valor = Number(form.elements['valor'].value) || 0;
      if (!nome) return;
      estado.itens.push({ id: gerarId('item'), nome: nome, categoria: form.dataset.categoria, valor: valor, valorSugerido: valor, incluido: true, parcelas: 1 });
      render();
    } else if (acao === 'nova-categoria') {
      var nomeCategoria = form.elements['nome'].value.trim();
      if (!nomeCategoria || estado.categoriasPersonalizadas.indexOf(nomeCategoria) !== -1) return;
      estado.categoriasPersonalizadas.push(nomeCategoria);
      render();
    }
  });

  render();
})();
