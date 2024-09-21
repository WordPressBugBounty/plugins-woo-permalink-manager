jQuery(document).ready(function ($) {
  jQuery(":input").inputmask();

  $('#permalink_url_suffix').on('input', function () {
    $('.permalink_url_suffix').text($(this).val());
  });
});
