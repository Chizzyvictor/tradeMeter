// MOBILE SIDEBAR
$("#mobileMenuBtn").on("click", function () {
    $("#sidebar").toggleClass("show");
});

// TAB SWITCHING
$(".settings-item").on("click", function () {

    // REMOVE ACTIVE CLASS
    $(".settings-item").removeClass("active");

    // ADD ACTIVE TO CLICKED ITEM
    $(this).addClass("active");

    // GET TARGET TAB
    let target = $(this).data("tab");

    // HIDE ALL TABS
    $(".tab-content").removeClass("active");

    // SHOW SELECTED TAB
    $("#" + target).addClass("active");

    // CLOSE SIDEBAR ON MOBILE
    if ($(window).width() < 992) {
        $("#sidebar").removeClass("show");
    }

});