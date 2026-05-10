// SIDEBAR COLLAPSE

$('#sidebarToggle').click(function(){

    $('#settingsSidebar').toggleClass('collapsed');

});


// MOBILE SIDEBAR

$('#mobileToggle').click(function(){

    $('#settingsSidebar').toggleClass('mobile-open');

});


// TAB SWITCHING

$('.menu-link').click(function(){

    $('.menu-link').removeClass('active');

    $(this).addClass('active');

    const tab = $(this).data('tab');

    $('.settings-tab').removeClass('active');

    $('#' + tab).addClass('active');

    // CHANGE PAGE TITLE

    $('#pageTitle').text($(this).find('span').text());

});
