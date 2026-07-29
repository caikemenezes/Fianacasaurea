(function () {
  'use strict';

  // Sem isso, toda <details class="secao-recolhivel"> volta a fechar sozinha
  // a cada recarregamento de página — e planilhas como Orçamento por
  // categoria recarregam a página a cada campo salvo (form POST + redirect).
  // Guarda o último estado (aberto/fechado) de cada seção com id, por
  // navegador, e restaura antes do usuário notar.

  document.querySelectorAll('.secao-recolhivel[id]').forEach(function (el) {
    var chave = 'secao-aberta:' + el.id;
    var salvo = localStorage.getItem(chave);
    if (salvo === '1') el.setAttribute('open', '');
    else if (salvo === '0') el.removeAttribute('open');

    el.addEventListener('toggle', function () {
      localStorage.setItem(chave, el.open ? '1' : '0');
    });
  });
})();
