<?php
session_start();

// Redirect if already logged in
if (!empty($_SESSION['isLogedin'])) {
    header("Location: index.php");
    exit;
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
                    <div id="passwordStrength" class="small text-muted"></div>
                    <div id="capsWarning" class="text-warning small" style="display:none"></div>
                </div>

                <input type="submit"
                       id="loginBtn"
                       value="Login"
                       class="btn btn-primary btn-block">

                <!-- Remember Me -->
                <div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="rememberMe" value="1">
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

   <!-- Secret question  -->
<div class="form-group">
<label for="cQuestion">Secret question :</label>
<select class="form-control" id="cQuestion">
<option value="">select your secret question</option>
<option value="what is your mothers maiden name">what is your mother's maiden name</option>
<option value="what is your favourite colour">what is your favourite colour </option>
<option value="what is your favourite game">what is your favourite game</option>
</select>
  <small id="cQuestionCheck" class="form-text text-danger" style="display:none;">
     choose a secret question 
  </small>
</div>

   <!-- Secret answer-->
<div class="form-group" id="ans" style="display: none;">
<label for="cAnswer">Your secret answer :</label>
<input type="text" 
         id="cAnswer" 
         name="cAnswer" 
         class="form-control">
  <small id="cAnswerCheck" class="form-text text-danger" style="display:none;">
     Answer your secret question 
  </small>
  <hr>
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
        <h4 class="text-center text-info">Forgotten password ?</h4>
        <div class="col-lg-8 m-auto d-block">
       <form id="forgotPwdForm">
<div class="form-group">
<label for="fEmail">Enter the email address associated with your account</label>
<input type="email" id="fEmail" name="fEmail" class="form-control" required>
<small id="fEmailCheck" 
       class="form-text invalid-feedback text-danger">
       Your email must be a valid email
</small>
</div>
<input type="submit"
       value="NEXT"
       class="btn btn-primary btn-block">
       </form>
</div>
</div>
    <!-- forgotQandATab -->
    <div class="tab-content panel-box" id="forgotQandATab">
        <h4 class="text-center text-info">Forgotten password ?</h4>
        <div class="col-lg-8 m-auto d-block">
       <form id="forgotQandAForm">
<div class="form-group">
<label for="fQuestion">Your secret question</label>
  <input type="text" 
           id="fQuestion" 
         name="fQuestion"
        class="form-control" 
        readonly>
        <hr>
</div>
<div class="form-group" id="fAnswerDiv">
<label for="fAnswer">Your secret answer</label>
  <input type="text" 
           id="fAnswer" 
         name="fAnswer"
        class="form-control">
   <small id="fAnswerCheck" class="form-text text-danger" style="display:none;">
   Your secret answer is required
   </small>
<hr>
</div>
<button type="submit" class="btn btn-success btn-block">SUBMIT ANSWER</button>
       </form>
</div>
</div>

    <!-- resetPwdTab -->
    <div class="tab-content panel-box" id="resetPwdTab">
        <h4 class="text-center text-info">Reset your password ?</h4>
        <div class="col-lg-8 m-auto d-block">
       <form id="resetPwdForm">
<div class="form-group">
<label for="rPass">Enter new password </label>
   <input type="password" 
            id="rPass" 
          name="rPass"
         class="form-control">
  <h5 id="rPassCheck" style="color: red;display: none;">**Please Fill the password</h5>
</div>
<div class="form-group">
<label for="rConPass">Enter new password again</label>
    <input type="password" 
    id="rConPass" 
  name="rConPass"
 class="form-control">
<h5 id="rConPassCheck" style="color: red;display: none;">**Please Fill the password</h5>
</div>

<button type="submit" class="btn btn-success btn-block">RESET PASSWORD</button>
       </form>
</div>

<?php include "INC/footer.php";?>

<script src="scripts/login.js?ver=<?= time() ?>"></script>

</div>
</body>
</html>