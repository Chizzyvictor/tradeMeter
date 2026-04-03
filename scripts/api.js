// ============================
// Init
// ============================
$(document).ready(function () {

  const path = String(window.location.pathname || "").toLowerCase();
  const isPublicPage = /\/(login|reset_password|verify_email|companies)\.php$/.test(path);

  if (isPublicPage) {
    return;
  }


  const csrf_token = $('meta[name="csrf-token"]').attr('content') || "";
  const App = new AppCore(csrf_token);
  const AuthApp = new Auth(App);

  App.loadUserPermissions();

  AuthApp.loadCurrentUserContext((user) => {
    const role = user?.role || "User";
    const name = user?.full_name || "";
    const $badge = $("#currentUserRoleBadge");
    const $dashboardUserContext = $("#dashboardUserContext");

    if ($badge.length) {
      $badge.text(`Role: ${role}`);
      if (name) {
        $badge.attr("title", name);
      }
    }

    if ($dashboardUserContext.length) {
      $dashboardUserContext.text(`User: ${name || "-"} | Role: ${role}`);
    }
  });

  AuthApp.loadGlobalMessageUnreadBadge();
  AuthApp.startPresenceHeartbeat();

  
    AuthApp.loadCompanyLogo();

    
  $(document).off('click', '#logout').on('click', '#logout', function (e) {
    e.preventDefault();
    if (confirm('End your session?')) {
      AuthApp.logout();
    }
  });


// === Image Viewer === 
// open viewer
$(document).on("click",".image",function(){

    var imgSrc = $(this).attr("src");

    $("#viewerImg").attr("src", imgSrc);
    $("#imageViewer").fadeIn();

});

// close viewer button
$(".close-viewer").click(function(){
    $("#imageViewer").fadeOut();
});

// close when clicking background
$("#imageViewer").click(function(e){
    if(e.target.id === "imageViewer"){
        $("#imageViewer").fadeOut();
    }
});


// === Theme Toggle ===
const $body = $("body");
const $toggleBtn = $("#toggleTheme");

// Apply saved theme on load
function applyTheme() {
  const savedTheme = localStorage.getItem("theme");

  if (savedTheme) {
    $body.toggleClass("dark-mode", savedTheme === "dark");
  } else {
    // Fallback to system preference
    const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    $body.toggleClass("dark-mode", prefersDark);
  }
}

// Run on page load
applyTheme();

// Toggle on click
$toggleBtn.on("click", function () {
  const isDark = $body.toggleClass("dark-mode").hasClass("dark-mode");
  localStorage.setItem("theme", isDark ? "dark" : "light");
});


    
});