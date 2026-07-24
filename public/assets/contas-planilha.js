(function () {
  'use strict';

  // Mesmo padrão do dividas-planilha.js: campos de parcelas/vencimento em
  // Contas do Mês ficam associados a um <form> oculto por linha (via
  // form="conta-form-ID") e salvam sozinhos ao perder o foco.

  document.addEventListener('change', function (e) {
    var formId = e.target.getAttribute('form');
    if (!formId || formId.indexOf('conta-form-') !== 0) return;

    var form = document.getElementById(formId);
    if (form) form.requestSubmit();
  });
})();
