(function () {
  'use strict';

  document.querySelectorAll('.grafico-interativo').forEach(function (wrap) {
    var svg = wrap.querySelector('svg');
    var crosshair = wrap.querySelector('.grafico-crosshair');
    var linha = wrap.querySelector('.grafico-crosshair-linha');
    var ponto = wrap.querySelector('.grafico-crosshair-ponto');
    var tooltip = wrap.querySelector('.grafico-tooltip');
    var pontosDado = Array.prototype.slice.call(wrap.querySelectorAll('.ponto-dado'));
    if (!svg || pontosDado.length === 0) return;

    var largura = parseFloat(wrap.dataset.largura);
    var altura = parseFloat(wrap.dataset.altura);

    function pontoMaisProximo(svgX) {
      var maisProximo = pontosDado[0];
      var menorDistancia = Infinity;
      pontosDado.forEach(function (p) {
        var cx = parseFloat(p.getAttribute('cx'));
        var distancia = Math.abs(cx - svgX);
        if (distancia < menorDistancia) {
          menorDistancia = distancia;
          maisProximo = p;
        }
      });
      return maisProximo;
    }

    function atualizar(clientX) {
      var rect = svg.getBoundingClientRect();
      var percentX = (clientX - rect.left) / rect.width;
      percentX = Math.min(1, Math.max(0, percentX));
      var svgX = percentX * largura;
      var p = pontoMaisProximo(svgX);
      var cx = parseFloat(p.getAttribute('cx'));
      var cy = parseFloat(p.getAttribute('cy'));

      crosshair.style.display = 'block';
      linha.setAttribute('x1', cx);
      linha.setAttribute('x2', cx);
      ponto.setAttribute('cx', cx);
      ponto.setAttribute('cy', cy);

      tooltip.style.display = 'block';
      tooltip.style.left = ((cx / largura) * 100) + '%';
      tooltip.style.top = ((cy / altura) * 100) + '%';
      tooltip.innerHTML = '<strong>' + p.dataset.rotulo + '</strong><span>' + p.dataset.valor + '</span>';
    }

    function esconder() {
      crosshair.style.display = 'none';
      tooltip.style.display = 'none';
    }

    // Mouse (desktop)
    svg.addEventListener('mousemove', function (e) { atualizar(e.clientX); });
    svg.addEventListener('mouseleave', esconder);

    // Toque (celular/tablet) — arrastar o dedo pelo gráfico move o crosshair,
    // sem rolar a página junto (por isso passive:false + preventDefault).
    svg.addEventListener('touchstart', function (e) {
      if (e.touches.length > 0) atualizar(e.touches[0].clientX);
    }, { passive: true });

    svg.addEventListener('touchmove', function (e) {
      if (e.touches.length > 0) {
        atualizar(e.touches[0].clientX);
        e.preventDefault();
      }
    }, { passive: false });

    svg.addEventListener('touchend', esconder);
    svg.addEventListener('touchcancel', esconder);
  });
})();
