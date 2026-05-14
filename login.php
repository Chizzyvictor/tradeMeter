<?php
session_start();

// Redirect if already logged in
if (!empty($_SESSION['isLogedin'])) {
    header("Location: index.php");
    exit;
}

$rememberedCompanyIdentifier = '';
$rememberedUserEmail = '';
$rememberMeChecked = false;

if (!empty($_COOKIE['remember_login_hint'])) {
    $decodedHint = json_decode(rawurldecode((string)$_COOKIE['remember_login_hint']), true);
    if (is_array($decodedHint)) {
        $companyCandidate = trim((string)($decodedHint['company'] ?? ''));
        $emailCandidate = strtolower(trim((string)($decodedHint['email'] ?? '')));

        if ($companyCandidate !== '' && strlen($companyCandidate) <= 100) {
            $rememberedCompanyIdentifier = $companyCandidate;
        }

        if ($emailCandidate !== '' && filter_var($emailCandidate, FILTER_VALIDATE_EMAIL)) {
            $rememberedUserEmail = $emailCandidate;
        }

        if ($rememberedCompanyIdentifier !== '' && $rememberedUserEmail !== '') {
            $rememberMeChecked = true;
        }
    }
}

include "INC/header.php";
?>

<div class="container py-4">

    <!-- LOGIN TAB -->
    <div class="tab-content panel-box" id="loginTab">
        <h4 class="text-center text-info">LOGIN</h4>
        <div class="col-lg-8 m-auto d-block">
            <form id="loginForm">


                <!-- Company (Email or Name) -->
                <div class="form-group">
                    <label for="companyEmail">Company (Email or Name):</label>
                    <input type="text"
                           name="companyEmail"
                           id="companyEmail"
                           placeholder="acme@company.com or Acme Ltd"
                           class="form-control"
                           value="<?= htmlspecialchars($rememberedCompanyIdentifier, ENT_QUOTES, 'UTF-8') ?>"
                           required>
                    <small id="companyIdentifierValid" class="form-text invalid-feedback text-danger" style="display:none;">
                        Enter a valid company email or company name
                    </small>
                </div>

                <!-- User Email -->
                <div class="form-group">
                    <label for="email">User Email:</label>
                    <input type="email"
                           name="email"
                           id="email"
                           class="form-control"
                           value="<?= htmlspecialchars($rememberedUserEmail, ENT_QUOTES, 'UTF-8') ?>"
                           required>
                    <small id="emailvalid" class="form-text invalid-feedback text-danger" style="display:none;">
                        Your email must be a valid email
                    </small>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="pass">Password:</label>
                    <input type="password"
                           name="pass"
                           id="pass"
                           class="form-control"
                           required>
                    <small id="passCheck" class="form-text text-danger" style="display:none;">
                        Please fill in the password
                    </small>
                    <div id="capsWarning" class="text-warning small" style="display:none"></div>
                </div>

                <input type="submit"
                       id="loginBtn"
                       value="Login"
                       class="btn btn-primary btn-block">

                <!-- Remember Me -->
                <div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="rememberMe" value="1" <?= $rememberMeChecked ? 'checked' : '' ?>>
                    <label class="form-check-label text-muted small" for="rememberMe">Remember me for 30 days</label>
                </div>
            </form>

            <div class="mt-3">
                <div class="">
             <button type="button" id="forgotPwdLink" class="btn text-primary"> Forgotten password?</button>
                </div>
                <hr>
                <p>View other <a href="companies.php">companies</a></p>
                <p>Don't have an account yet?
                    <button type="button" id="signupLink" class="btn btn-success">Sign Up</button>
                </p>
            </div>
        </div>
    </div>

    <!-- SIGNUP TAB -->
    <div class="tab-content panel-box" id="signupTab">
        <h4 class="text-center text-info">SIGNUP</h4>
        <div class="col-lg-8 m-auto d-block">
            <form id="signupForm" >


                <!-- Company Name -->
                <div class="form-group">
                    <label for="cName">Company name:</label>
                    <input type="text"
                           name="cName"
                           id="cName"
                           placeholder="Miracle Ventures Ltd"
                           class="form-control"
                           required>
                    <small id="cNameCheck" class="form-text text-danger" style="display:none;">
                        Company name is required
                    </small>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="cEmail">Email:</label>
                    <input type="email"
                           name="cEmail"
                           placeholder="miracle@gmail.com"
                           id="cEmail"
                           class="form-control"
                           required>
                    <small id="cEmailValid" class="form-text text-danger" style="display:none;">
                        Your email must be a valid email
                    </small>
                </div>

                <!-- Owner Full Name -->
                <div class="form-group">
                    <label for="fullName">Your Full Name:</label>
                    <input type="text"
                           name="fullName"
                           id="fullName"
                           placeholder="John Doe"
                           class="form-control"
                           required>
                    <small id="fullNameCheck" class="form-text text-danger" style="display:none;">
                        Full name is required
                    </small>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="cPass">Password:</label>
                    <input type="password"
                           name="cPass"
                           id="cPass"
                           placeholder="Enter Password"
                           class="form-control"
                           required>
                    <small id="cpasscheck" class="form-text text-danger" style="display:none;">
                        Please fill in the password
                    </small>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="conCPass">Confirm Password:</label>
                    <input type="password"
                           name="conCPass"
                           id="conCPass"
                           placeholder="Enter password again"
                           class="form-control"
                           required>
                    <small id="conCPasscheck" class="form-text text-danger" style="display:none;">
                        Passwords didn't match
                    </small>
                </div>

                <input type="submit"
                       id="signupBtn"
                       value="Sign Up"
                       class="btn btn-primary btn-block">
            </form>

            <hr>
            <p>
                <input type="checkbox" name="agree" value="agree" id="agree" checked>
                I have read and agreed to the
                <a href="#">terms</a> and <a href="#">conditions</a> of this application
            </p>
            <small id="agreecheck" class="form-text text-danger" style="display:none;">
                Check the box to confirm you have read the terms and conditions
            </small>
            <p>
                Already have an account?
                <button type="button" id="loginLink" class="btn btn-success">Login</button>
            </p>
        </div>
    </div>


    <!-- forgotPwdTab -->
    <div class="tab-content panel-box" id="forgotPwdTab">
        <button type="button" id="backToLogin" class="btn btn-success"><-Back</button>
        <h4 class="text-center text-info">Forgotten password?</h4>
        <div class="col-lg-8 m-auto d-block">
            <p class="text-muted text-center mb-3">Enter your email and company name to receive a password reset link.</p>
            <form id="forgotPwdForm">
                <div class="form-group">
                    <label for="fpCompanyEmail">Company (Email or Name):</label>
                    <input type="text"
                           id="fpCompanyEmail"
                           name="company"
                           placeholder="acme@company.com or Acme Ltd"
                           class="form-control"
                           required>
                    <small id="fpCompanyCheck" class="form-text invalid-feedback text-danger">
                        Enter a valid company email or company name
                    </small>
                </div>
                <div class="form-group">
                    <label for="fpUserEmail">Your Email:</label>
                    <input type="email"
                           id="fpUserEmail"
                           name="email"
                           class="form-control"
                           required>
                    <small id="fpEmailCheck" class="form-text invalid-feedback text-danger">
                        Your email must be valid
                    </small>
                </div>
                <input type="submit"
                       value="Send Reset Link"
                       class="btn btn-primary btn-block">
            </form>
        </div>
    </div>

<?php include "INC/footer.php";?>

<script src="scripts/login.js?v=<?= asset_ver('scripts/login.js') ?>"></script>

</div>
</body>
</html>
