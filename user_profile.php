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
				<div class="col-lg-10 offset-lg-1">
					<div class="profile-tab-nav" role="tablist" aria-label="Profile sections">
						<button type="button" class="profile-tab-btn active" data-profile-tab="infoTab">User Info</button>
						<button type="button" class="profile-tab-btn" data-profile-tab="messagesTab">Messaging <span class="badge badge-light ml-1" id="messageUnreadBadge">0</span></button>
					</div>

					<div id="infoTab" class="profile-tab-panel is-active">
						<div class="card shadow-sm mb-4 user-profile-card">
							<div class="card-header font-weight-bold">User Information</div>
							<div class="card-body">
								<div class="form-group row">
									<label class="col-sm-3 col-form-label">Full Name:</label>
									<div class="col-sm-9"><p id="userFullName" class="form-control-plaintext">-</p></div>
								</div>
								<div class="form-group row">
									<label class="col-sm-3 col-form-label">Company:</label>
									<div class="col-sm-9"><p id="userCompany" class="form-control-plaintext">-</p></div>
								</div>
								<div class="form-group row">
									<label class="col-sm-3 col-form-label">Role:</label>
									<div class="col-sm-9"><p id="userRole" class="form-control-plaintext">-</p></div>
								</div>
								<div class="form-group row mb-0">
									<label class="col-sm-3 col-form-label">Member Since:</label>
									<div class="col-sm-9"><p id="userCreatedAt" class="form-control-plaintext">-</p></div>
								</div>
							</div>
						</div>

						<div class="card shadow-sm mb-4 user-profile-section-card">
							<div class="card-header font-weight-bold">Change Email</div>
							<div class="card-body">
								<form id="emailForm">
									<div class="form-group">
										<label for="currentEmail">Current Email</label>
										<input type="email" id="currentEmail" class="form-control" disabled>
									</div>
									<div class="form-group">
										<label for="newEmail">New Email</label>
										<input type="email" id="newEmail" name="newEmail" class="form-control" required>
									</div>
									<div class="form-group">
										<label for="emailPassword">Confirm Password</label>
										<input type="password" id="emailPassword" name="password" class="form-control" required>
									</div>
									<div class="user-profile-form-actions">
										<button type="submit" class="btn btn-primary" id="changeEmailBtn">Change Email</button>
									</div>
								</form>
							</div>
						</div>

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

					<div id="messagesTab" class="profile-tab-panel">
						<div class="chat-shell card shadow-sm">
							<div class="chat-sidebar border-right">
								<div class="chat-sidebar-head d-flex justify-content-between align-items-center">
									<h5 class="mb-0">Chats</h5>
									<button type="button" class="btn btn-sm btn-outline-secondary" id="refreshMessagesBtn">Refresh</button>
								</div>
								<div class="chat-sidebar-search p-2 border-bottom">
									<input type="text" id="chatSearch" class="form-control form-control-sm" placeholder="Search teammate">
								</div>
								<div class="chat-conversation-list" id="chatConversationList">
									<div class="text-muted p-3">Loading chats...</div>
								</div>
							</div>

							<div class="chat-main">
								<div class="chat-main-head border-bottom d-flex justify-content-between align-items-center" id="chatMainHead">
									<div>
										<div class="font-weight-bold" id="chatActiveName">Select a teammate</div>
										<small class="text-muted" id="chatActiveMeta">Use this channel for information, reports, and suggestions.</small>
									</div>
								</div>
								<div class="chat-thread" id="chatThread">
									<div class="chat-empty">Choose a teammate from the left to start messaging.</div>
								</div>
								<div class="chat-compose border-top">
									<form id="messageForm" class="chat-compose-form">
										<div class="form-row mb-2">
											<div class="col-6 col-md-4">
												<select id="messageCategory" class="form-control form-control-sm">
													<option value="info">Information</option>
													<option value="report">Report</option>
													<option value="suggestion">Suggestion</option>
												</select>
											</div>
											<div class="col-6 col-md-8">
												<input type="text" id="messageSubject" class="form-control form-control-sm" maxlength="150" placeholder="Subject (optional)">
											</div>
										</div>
										<div class="d-flex align-items-end">
											<textarea id="messageBody" class="form-control chat-compose-input" rows="2" maxlength="5000" placeholder="Type a message"></textarea>
											<button type="submit" class="btn btn-success ml-2" id="sendMessageBtn">Send</button>
										</div>
									</form>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include "INC/footer.php"; ?>

<script src="scripts/user_profile.js?v=<?= asset_ver('scripts/user_profile.js') ?>"></script>
