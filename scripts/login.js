$(document).ready(() => {

  // ============================
  // INIT
  // ============================
  const csrf_token = $('meta[name="csrf-token"]').attr('content');
  const AppCoreInstance = new AppCore(csrf_token);
  const AuthApp = new Auth(AppCoreInstance);
  const Validator = new FormValidator();

  // ============================
  // SELECTORS
  // ============================
  const $signupForm = $("#signupForm");
  const $loginForm = $("#loginForm");
  const $forgotPwdForm = $("#forgotPwdForm");
  const $forgotQandAForm = $("#forgotQandAForm");
  const $resetPwdForm = $("#resetPwdForm");
  const $questionSelect = $("#cQuestion");
  const $answerWrapper = $("#ans");

  const validateCompanyIdentifier = () => {
    const value = String($("#companyEmail").val() || "").trim();
    const emailRegex = Validator.rules.email.regex;
    const nameRegex = /^[a-zA-Z0-9 .,'&()-]{2,100}$/;
    const isValid = !!value && (emailRegex.test(value) || nameRegex.test(value));

    Validator.setFieldError("#companyEmail", isValid ? "" : "Enter a valid company email or company name", "#companyIdentifierValid");
    return isValid;
  };

  // ============================
  // TABS
  // ============================
  $("#signupLink").on("click", () => AppCoreInstance.switchTab("signupTab"));
  $("#loginLink").on("click", () => AppCoreInstance.switchTab("loginTab"));
  $("#forgotPwdLink").on("click", () => AppCoreInstance.switchTab("forgotPwdTab"));
  $("#backToLogin").on("click", () => AppCoreInstance.switchTab("loginTab"));

  // ============================
  // SIGNUP
  // ============================
  $signupForm.on("submit", e => {
    e.preventDefault();
    const rulesMap = {
      cEmail: Validator.rules.email,
      cPass: Validator.rules.password,
      cName: Validator.rules.name,
      fullName: Validator.rules.name,
      cAnswer: Validator.rules.answer
    };
    const ok = Validator.validateForm($signupForm, rulesMap);
    const passMatch = $("#cPass").val() === $("#conCPass").val();
    if (!passMatch) {
      Validator.setTextError("#conCPasscheck", "Passwords do not match");
      return;
    } else {
      Validator.setTextError("#conCPasscheck", "");
    }
    if (!ok) {
      AppCoreInstance.showAlert("Please correct the errors.", "error");
      return;
    }
    AuthApp.signup({
      cEmail: $("#cEmail").val().trim(),
      cPass: $("#cPass").val().trim(),
      cName: $("#cName").val().trim(),
      cQuestion: $questionSelect.val(),
      cAnswer: $("#cAnswer").val().trim(),
      fullName: $("#fullName").val().trim()
    });
  });

  // ============================
  // LOGIN
  // ============================
  $loginForm.on("submit", e => {
    e.preventDefault();
    const rulesMap = {
      email: Validator.rules.email,
      pass: Validator.rules.password
    };
    const ok = Validator.validateForm($loginForm, rulesMap) && validateCompanyIdentifier();
    if (!ok) {
      AppCoreInstance.showAlert("Invalid login credentials.", "error");
      return;
    }
    AuthApp.login({
      companyEmail: $("#companyEmail").val().trim(),
      email: $("#email").val().trim(),
      pass: $("#pass").val().trim(),
      remember: $("#rememberMe").is(":checked") ? "1" : "0"
    });
  });

  // ============================
  // FORGOT PASSWORD
  // ============================
  $forgotPwdForm.on("submit", e => {
    e.preventDefault();
    const rulesMap = {fEmail: Validator.rules.email};
    if (!Validator.validateForm($forgotPwdForm, rulesMap)) return;
    AuthApp.requestPasswordReset($("#fEmail").val().trim());
  });

  // ============================
  // FORGOT Q & A
  // ============================
  $forgotQandAForm.on("submit", e => {
    e.preventDefault();
    const rulesMap = { fAnswer: Validator.rules.answer };
    if (!Validator.validateForm($forgotQandAForm, rulesMap)) return;
    AuthApp.forgotQandA($("#fAnswer").val().trim());
  });

  // ============================
  // RESET PASSWORD
  // ============================
  $resetPwdForm.on("submit", e => {
    e.preventDefault();
    const rulesMap = { rPass: Validator.rules.password };
    if (!Validator.validateForm($resetPwdForm, rulesMap)) return;
    if ($("#rPass").val() !== $("#rConPass").val()) {
      Validator.setTextError("#rConPassCheck", "Passwords do not match");
      return;
    } else {
      Validator.setTextError("#rConPassCheck", "");
    }
    AuthApp.resetPassword($("#rPass").val().trim());
  });

  // ============================
  // SECURITY QUESTION TOGGLE
  // ============================
  $questionSelect.on("change", function () {
    if (!this.value) {
      $answerWrapper.hide();
      $("#cAnswer").val("");
    } else {
      $answerWrapper.show();
    }
  }).trigger("change");

  // ============================
  // LIVE VALIDATION
  // ============================
  const live = (sel, rule, err) =>
    $(sel).on("input blur", () =>
      Validator.validateField(sel, rule, err)
    );
  $("#companyEmail").on("input blur", validateCompanyIdentifier);
  live("#cEmail", Validator.rules.email, "#cEmailValid");
  live("#cPass", Validator.rules.password, "#cpasscheck");
  live("#cName", Validator.rules.name, "#cNameCheck");
  live("#fullName", Validator.rules.name, "#fullNameCheck");
  live("#cAnswer", Validator.rules.answer, "#cAnswerCheck");
  live("#email", Validator.rules.email, "#emailvalid");
  live("#pass", Validator.rules.password, "#passCheck");
  live("#fEmail", Validator.rules.email, "#fEmailCheck");
  live("#fAnswer", Validator.rules.answer, "#fAnswerCheck");
  live("#rPass", Validator.rules.password, "#rPassCheck");

  // ============================
  // CONFIRM PASSWORD LIVE
  // ============================
  $("#conCPass, #rConPass").on("input", function () {
    const match =
      $(this).val() === $(this).closest("form").find("input[type=password]").first().val();
    $(this)
      .toggleClass("is-valid", match)
      .toggleClass("is-invalid", !match);
  });

  // ============================
  // PASSWORD STRENGTH
  // ============================
  $("#cPass, #rPass").on("input", function () {
    const pass = $(this).val();
    const score =
      (pass.length >= 6) +
      (pass.length >= 10) +
      /[A-Z]/.test(pass) +
      /[0-9]/.test(pass) +
      /[^A-Za-z0-9]/.test(pass);
    $("#passwordStrength").text(
      ["Very weak", "Weak", "Medium", "Strong", "Very strong"][Math.max(0, score - 1)]
    );
  });

  // ============================
  // CAPS LOCK WARNING
  // ============================
  $("input[type=password]").on("keyup", e =>
    $("#capsWarning").toggle(e.getModifierState?.("CapsLock"))
  );

});