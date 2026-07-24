(function () {
  'use strict';

  // Delegação de eventos no document (em vez de listeners por elemento) —
  // funciona tanto pros cartões renderizados pelo PHP quanto pros cartões do
  // Simulador, que são reconstruídos em JS a cada atualização de estado
  // (listeners presos a um elemento específico se perderiam nesse reconstruir).

  function abrir(wrap) {
    var balao = wrap.querySelector('.info-balao');
    var botao = wrap.querySelector('.botao-icone-info');
    if (balao) balao.hidden = false;
    if (botao) botao.setAttribute('aria-expanded', 'true');
  }

  function fechar(wrap) {
    var balao = wrap.querySelector('.info-balao');
    var botao = wrap.querySelector('.botao-icone-info');
    if (balao) balao.hidden = true;
    if (botao) botao.setAttribute('aria-expanded', 'false');
  }

  function fecharTodosExceto(exceto) {
    document.querySelectorAll('.info-icone-wrapper').forEach(function (wrap) {
      if (wrap !== exceto) fechar(wrap);
    });
  }

  document.addEventListener('click', function (e) {
    var wrap = e.target.closest('.info-icone-wrapper');
    if (!wrap) {
      fecharTodosExceto(null);
      return;
    }
    e.stopPropagation();
    var balao = wrap.querySelector('.info-balao');
    if (balao && balao.hidden) {
      fecharTodosExceto(wrap);
      abrir(wrap);
    } else {
      fechar(wrap);
    }
  });

  document.addEventListener('mouseover', function (e) {
    var wrap = e.target.closest('.info-icone-wrapper');
    if (!wrap) return;
    if (wrap.contains(e.relatedTarget)) return;
    abrir(wrap);
  });

  document.addEventListener('mouseout', function (e) {
    var wrap = e.target.closest('.info-icone-wrapper');
    if (!wrap) return;
    if (wrap.contains(e.relatedTarget)) return;
    fechar(wrap);
  });
})();
