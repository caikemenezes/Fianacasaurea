(function () {
  'use strict';

  // Tabela de Dívidas funciona como planilha: os campos ficam sempre
  // editáveis (associados a um <form> oculto por linha via atributo
  // form="divida-form-ID"), e cada edição salva sozinha ao sair do campo —
  // sem precisar de um botão "Salvar" visível.

  document.addEventListener('change', function (e) {
    var formId = e.target.getAttribute('form');
    if (!formId || formId.indexOf('divida-form-') !== 0) return;

    var form = document.getElementById(formId);
    if (form) form.requestSubmit();
  });
})();
