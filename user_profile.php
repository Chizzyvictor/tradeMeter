<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>

<div class="content user-profile-page">
	<div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1 class="m-0">My Profile</h1>
				</div>
			</div>
		</div>
	</div>

	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-8 offset-lg-2">

					<!-- Profile Card -->
					<div class="card shadow-sm mb-4 user-profile-card">
						<div class="card-header font-weight-bold">User Information</div>
						<div class="card-body">
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Full Name:</label>
								<div class="col-sm-9">
									<p id="userFullName" class="form-control-plaintext">-</p>
								</div>
							</div>
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Company:</label>
								<div class="col-sm-9">
									<p id="userCompany" class="form-control-plaintext">-</p>
								</div>
							</div>
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Role:</label>
								<div class="col-sm-9">
									<p id="userRole" class="form-control-plaintext">-</p>
								</div>
							</div>
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Member Since:</label>
								<div class="col-sm-9">
									<p id="userCreatedAt" class="form-control-plaintext">-</p>
								</div>
							</div>
						</div>
					</div>

					<!-- Email Change -->
					<div class="card shadow-sm mb-4 user-profile-section-card">
						<div class="card-header font-weight-bold">Change Email</div>
						<div class="card-body">
							<form id="emailForm">
								<div class="form-group">
									<label for="currentEmail">Current Email</label>
									<input type="email" id="currentEmail" class="form-control" disabled>
									<small class="form-text text-muted">Your current email address</small>
								</div>
								<div class="form-group">
									<label for="newEmail">New Email</label>
									<input type="email" id="newEmail" name="newEmail" class="form-control" required>
									<small class="form-text text-muted">Must be a valid, unused email address</small>
								</div>
								<div class="form-group">
									<label for="emailPassword">Confirm Password</label>
									<input type="password" id="emailPassword" name="password" class="form-control" required>
									<small class="form-text text-muted">Enter your password to confirm this change</small>
								</div>
								<div class="user-profile-form-actions">
									<button type="submit" class="btn btn-primary" id="changeEmailBtn">Change Email</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Password Change -->
					<div class="card shadow-sm user-profile-section-card">
						<div class="card-header font-weight-bold">Change Password</div>
						<div class="card-body">
							<form id="passwordForm">
								<div class="form-row">
									<div class="form-group col-md-4">
										<label for="currentPassword">Current Password</label>
										<input type="password" id="currentPassword" name="currentPassword" class="form-control" required>
									</div>
									<div class="form-group col-md-4">
										<label for="newPassword">New Password</label>
										<input type="password" id="newPassword" name="newPassword" class="form-control" required>
										<small class="form-text text-muted">At least 6 characters</small>
									</div>
									<div class="form-group col-md-4">
										<label for="confirmPassword">Confirm Password</label>
										<input type="password" id="confirmPassword" name="confirmPassword" class="form-control" required>
									</div>
								</div>
								<div class="user-profile-form-actions">
									<button type="submit" class="btn btn-warning" id="changePasswordBtn">Change Password</button>
								</div>
							</form>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>

<?php include "INC/footer.php"; ?>

<script src="scripts/user_profile.js?v=<?= asset_ver('scripts/user_profile.js') ?>"></script>
