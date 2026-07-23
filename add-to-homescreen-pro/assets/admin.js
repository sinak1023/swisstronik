/* Add to Home Screen Pro — admin */
jQuery(function ($) {
  'use strict';

  // Color pickers
  $('.a2hsp-color').wpColorPicker();

  // Tabs
  $('.a2hsp-tabs .nav-tab').on('click', function (e) {
    e.preventDefault();
    $('.a2hsp-tabs .nav-tab').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');
    $('.a2hsp-tab').removeClass('active');
    $($(this).attr('href')).addClass('active');
  });

  // Icon picker
  var iconFrame = null;
  $('#a2hsp-pick-icon').on('click', function (e) {
    e.preventDefault();
    if (!iconFrame) {
      iconFrame = wp.media({
        title: 'انتخاب آیکون اپ',
        library: { type: 'image' },
        multiple: false,
        button: { text: 'انتخاب' }
      });
      iconFrame.on('select', function () {
        var att = iconFrame.state().get('selection').first().toJSON();
        $('#a2hsp_icon_url').val(att.url);
        $('.a2hsp-icon-preview').not('.a2hsp-splash-preview').html('<img src="' + att.url + '" alt="">');
      });
    }
    iconFrame.open();
  });

  $('#a2hsp-remove-icon').on('click', function () {
    $('#a2hsp_icon_url').val('');
    $('.a2hsp-icon-preview').not('.a2hsp-splash-preview').empty();
  });

  // Splash screen picker
  var splashFrame = null;
  $('#a2hsp-pick-splash').on('click', function (e) {
    e.preventDefault();
    if (!splashFrame) {
      splashFrame = wp.media({
        title: 'انتخاب تصویر اسپلش اسکرین',
        library: { type: 'image' },
        multiple: false,
        button: { text: 'انتخاب' }
      });
      splashFrame.on('select', function () {
        var att = splashFrame.state().get('selection').first().toJSON();
        $('#a2hsp_splash_url').val(att.url);
        $('.a2hsp-splash-preview').html('<img src="' + att.url + '" alt="" style="width:auto;height:160px;border-radius:12px;">');
      });
    }
    splashFrame.open();
  });

  $('#a2hsp-remove-splash').on('click', function () {
    $('#a2hsp_splash_url').val('');
    $('.a2hsp-splash-preview').empty();
  });

  // Screenshots picker (multiple)
  var shotsFrame = null;
  $('#a2hsp-add-shots').on('click', function (e) {
    e.preventDefault();
    if (!shotsFrame) {
      shotsFrame = wp.media({
        title: 'انتخاب اسکرین‌شات‌ها',
        library: { type: 'image' },
        multiple: 'add',
        button: { text: 'افزودن' }
      });
      shotsFrame.on('select', function () {
        shotsFrame.state().get('selection').each(function (att) {
          var url = att.toJSON().url;
          if ($('#a2hsp-shots .a2hsp-shot-item').length >= 8) return;
          $('#a2hsp-shots').append(
            '<div class="a2hsp-shot-item">' +
              '<img src="' + url + '" alt="">' +
              '<input type="hidden" name="a2hsp_settings[screenshots][]" value="' + url + '">' +
              '<button type="button" class="a2hsp-shot-remove">&times;</button>' +
            '</div>'
          );
        });
      });
    }
    shotsFrame.open();
  });

  $(document).on('click', '.a2hsp-shot-remove', function () {
    $(this).closest('.a2hsp-shot-item').remove();
  });
});
