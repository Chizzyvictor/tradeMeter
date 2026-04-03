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
					<div class="profile-tab-nav-wrap">
						<div class="profile-tab-nav" role="tablist" aria-label="Profile sections">
							<button type="button" class="profile-tab-btn active" data-profile-tab="infoTab">
								<i class="fas fa-user mr-1"></i> User Info
							</button>
							<button type="button" class="profile-tab-btn" data-profile-tab="messagesTab">
								<i class="fas fa-comments mr-1"></i> Messages
								<span class="chat-tab-badge" id="messageUnreadBadge"></span>
							</button>
						</div>
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
						<div class="chat-shell">
							<!-- Sidebar -->
							<div class="chat-sidebar">
								<div class="chat-sidebar-head">
									<div class="d-flex align-items-center gap-2">
										<span class="chat-live-dot mr-2" title="Auto-refreshing every 5s"></span>
										<span class="font-weight-bold" style="font-size:.95rem;">Messages</span>
									</div>
									<button type="button" class="btn btn-sm btn-outline-light" id="refreshMessagesBtn" title="Refresh conversations">
										<i class="fas fa-sync-alt"></i>
									</button>
								</div>
								<div class="chat-sidebar-search">
									<input type="text" id="chatSearch" class="form-control form-control-sm" placeholder="&#128269; Search teammates...">
								</div>
								<div class="chat-conversation-list" id="chatConversationList">
									<div class="chat-conv-empty">Loading conversations...</div>
								</div>
							</div>

							<!-- Main chat pane -->
							<div class="chat-main">
								<div class="chat-main-head" id="chatMainHead">
									<button type="button" class="btn btn-link d-none p-0 chat-back-btn" id="chatBackBtn">
										<i class="fas fa-chevron-left"></i>
									</button>
									<div id="chatHeadAvatar"></div>
									<div class="chat-main-head-info">
										<div class="font-weight-bold" id="chatActiveName">Select a teammate</div>
										<small class="text-muted" id="chatActiveMeta">Choose someone from the list to start chatting</small>
									</div>
									<div class="ml-auto">
										<span class="chat-live-dot" title="Live — updates every 5s"></span>
									</div>
								</div>

								<div class="chat-thread" id="chatThread">
									<div class="chat-empty">
										<i class="fas fa-comments fa-3x mb-3 d-block"></i>
										Choose a teammate to start messaging
									</div>
								</div>

								<div class="chat-new-msg-wrap" id="chatNewMsgWrap">
									<button type="button" class="chat-new-msg-badge" id="chatNewMsgBadge">
										<i class="fas fa-arrow-down"></i>
										<span id="chatNewMsgCount">0</span>
										<span id="chatNewMsgLabel">new messages</span>
									</button>
								</div>

								<div class="chat-compose">
									<form id="messageForm">
										<div class="chat-compose-meta">
											<select id="messageCategory" class="form-control form-control-sm chat-category-select">
												<option value="info">&#128203; Info</option>
												<option value="report">&#128202; Report</option>
												<option value="suggestion">&#128161; Suggestion</option>
											</select>
											<input type="text" id="messageSubject" class="form-control form-control-sm" maxlength="150" placeholder="Subject (optional)">
										</div>
										<div class="chat-compose-row">
											<textarea id="messageBody" class="form-control chat-compose-input" rows="1" maxlength="5000" placeholder="Type a message… (Ctrl+Enter to send)"></textarea>
											<button type="submit" class="btn btn-success chat-send-btn" id="sendMessageBtn">
												<i class="fas fa-paper-plane"></i>
											</button>
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
