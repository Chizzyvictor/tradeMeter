// HIDE BACK BUTTON ON LARGER SCREENS
function toggleBackButton() {
    if ($(window).width() < 992) {
        $(".back-btn").show();
    } else {
        $(".back-btn").hide();
    }
}

// INITIAL CHECK
toggleBackButton();

// CHECK ON RESIZE
$(window).on("resize", function () {
    toggleBackButton();
});

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

    // CLOSE SIDEBAR ON BACK BUTTON (MOBILE)
    $(document).on("click", ".back-btn", function (e) {
      if ($(window).width() < 992) {
        $("#sidebar").removeClass("show");
    }
});

//CLOSE SIDEBAR ON OUTSIDE CLICK (MOBILE)
$(document).on("click", function (e) {
    if (!$(e.target).closest("#sidebar, #mobileMenuBtn").length) {
        if ($(window).width() < 992) {
            $("#sidebar").removeClass("show");
        }
    }
});

// CLOSE SIDEBAR ON RESIZE
$(window).on("resize", function () {
    if ($(window).width() >= 992) {
        $("#sidebar").removeClass("show");
    }
});
