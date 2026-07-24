(function () {
  'use strict';

  // Máscara de moeda pra qualquer <input data-moeda>: formata "10.000,00"
  // enquanto o usuário digita, sempre crescendo da direita pra esquerda
  // (trata os dígitos digitados como centavos) — evita ambiguidade de onde
  // fica a vírgula decimal enquanto o campo ainda está sendo preenchido.
  // O valor final enviado no POST já vem nesse formato; quem desfaz isso do
  // lado do PHP é parse_valor() em src/util.php.

  function formatarCentavos(digitos) {
    var semZerosExtras = digitos.replace(/^0+(?=\d)/, '');
    var valor = (parseInt(semZerosExtras || '0', 10) / 100).toFixed(2);
    var partes = valor.split('.');
    var inteiro = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return inteiro + ',' + partes[1];
  }

  document.addEventListener('input', function (e) {
    if (!e.target.matches('input[data-moeda]')) return;
    var digitos = e.target.value.replace(/\D/g, '');
    e.target.value = digitos === '' ? '' : formatarCentavos(digitos);
  });
})();
