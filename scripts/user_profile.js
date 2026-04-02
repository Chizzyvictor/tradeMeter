class UserProfilePage {
  constructor() {
    const csrf = $('meta[name="csrf-token"]').attr('content') || '';
    this.app = new AppCore(csrf);
    this.currentUserId = 0;
    this.users = [];
    this.messagePool = [];
    this.activeConversationUserId = 0;
    this.chatFilter = '';
    this.messagesRefreshTimer = null;
    this.isLoadingMessages = false;
    this.wasMobileView = window.matchMedia('(max-width: 991.98px)').matches;
    this.bindEvents();
    this.initialize();
  }

  initialize() {
    this.loadUserProfile();
    this.loadMessagingData();
    this.startMessagesAutoRefresh();
  }

  bindEvents() {
    $(document).on('click', '.profile-tab-btn', (e) => {
      const tabId = String($(e.currentTarget).data('profileTab') || '');
      if (tabId) {
        this.switchProfileTab(tabId);
      }
    });

    $('#emailForm').on('submit', (e) => {
      e.preventDefault();
      this.changeEmail();
    });

    $('#passwordForm').on('submit', (e) => {
      e.preventDefault();
      this.changePassword();
    });

    $('#messageForm').on('submit', (e) => {
      e.preventDefault();
      this.sendMessage();
    });

    $('#chatSearch').on('input', (e) => {
      this.chatFilter = String($(e.currentTarget).val() || '').trim().toLowerCase();
      this.renderConversationList();
    });

    $('#refreshMessagesBtn').on('click', () => {
      this.loadMessagingData();
    });

    $('#chatBackBtn').on('click', () => {
      this.showMobileConversationList();
    });

    $(window).on('resize', () => {
      this.applyMobileChatMode();
    });

    $(document).on('click', '.chat-conversation-item', (e) => {
      const userId = parseInt($(e.currentTarget).data('userId'), 10);
      if (!Number.isNaN(userId) && userId > 0) {
        this.selectConversation(userId);
      }
    });

    $(document).on('click', '.message-read-btn', (e) => {
      const messageId = parseInt($(e.currentTarget).data('messageId'), 10);
      if (!Number.isNaN(messageId) && messageId > 0) {
        this.markMessageRead(messageId);
      }
    });
  }

  switchProfileTab(tabId) {
    $('.profile-tab-btn').removeClass('active');
    $(`.profile-tab-btn[data-profile-tab="${tabId}"]`).addClass('active');
    $('.profile-tab-panel').removeClass('is-active');
    $(`#${tabId}`).addClass('is-active');

    if (tabId === 'messagesTab') {
      this.showMobileConversationList();
      this.loadMessagingData();
    }
  }

  isMobileView() {
    return window.matchMedia('(max-width: 991.98px)').matches;
  }

  applyMobileChatMode() {
    const isMobile = this.isMobileView();

    if (!isMobile) {
      $('.chat-shell').removeClass('mobile-chat-open');
      $('body').removeClass('chat-mobile-open');
      this.wasMobileView = false;
      return;
    }

    if (!this.wasMobileView && $('#messagesTab').hasClass('is-active')) {
      this.showMobileConversationList();
    }

    this.wasMobileView = true;
  }

  showMobileConversationList() {
    if (!this.isMobileView()) {
      return;
    }
    $('.chat-shell').removeClass('mobile-chat-open');
    $('body').removeClass('chat-mobile-open');
  }

  openMobileConversation() {
    if (!this.isMobileView()) {
      return;
    }
    $('.chat-shell').addClass('mobile-chat-open');
    $('body').addClass('chat-mobile-open');
  }

  startMessagesAutoRefresh() {
    if (this.messagesRefreshTimer) {
      clearInterval(this.messagesRefreshTimer);
    }

    this.messagesRefreshTimer = setInterval(() => {
      if ($('#messagesTab').hasClass('is-active')) {
        this.loadMessagingData();
      }
    }, 5000);
  }

  loadUserProfile() {
    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'getUserProfile',
      silent: true,
      onSuccess: (response) => {
        const user = response.data;
        $('#userFullName').text(user.full_name || 'N/A');
        $('#userCompany').text(user.company || 'N/A');
        $('#userRole').text(user.role || 'User');
        $('#currentEmail').val(user.email || '');

        if (user.created_at) {
          const date = new Date(parseInt(user.created_at, 10) * 1000);
          $('#userCreatedAt').text(date.toLocaleDateString());
        }
      },
      errorMsg: 'Error loading profile'
    });
  }

  changeEmail() {
    const newEmail = $('#newEmail').val().trim();
    const password = $('#emailPassword').val();

    if (!newEmail) {
      this.app.showAlert('Please enter new email', 'error');
      return;
    }

    if (!password) {
      this.app.showAlert('Please enter your password to confirm', 'error');
      return;
    }

    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'changeEmail',
      data: { newEmail, password },
      successMsg: 'Email changed successfully. Please log in with your new email.',
      errorMsg: 'Failed to change email',
      onSuccess: () => {
        $('#emailForm')[0].reset();
        setTimeout(() => {
          window.location.href = 'login.php';
        }, 2000);
      }
    });
  }

  changePassword() {
    const current = $('#currentPassword').val();
    const newPwd = $('#newPassword').val();
    const confirm = $('#confirmPassword').val();

    if (!current || !newPwd || !confirm) {
      this.app.showAlert('All password fields are required', 'error');
      return;
    }

    if (newPwd.length < 6) {
      this.app.showAlert('New password must be at least 6 characters', 'error');
      return;
    }

    if (newPwd !== confirm) {
      this.app.showAlert('New passwords do not match', 'error');
      return;
    }

    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'changePassword',
      data: {
        currentPassword: current,
        newPassword: newPwd,
        confirmPassword: confirm
      },
      successMsg: 'Password changed successfully',
      errorMsg: 'Failed to change password',
      onSuccess: () => {
        $('#passwordForm')[0].reset();
      }
    });
  }

  loadMessagingData() {
    if (this.isLoadingMessages) {
      return;
    }

    this.isLoadingMessages = true;
    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'loadMessagingData',
      silent: true,
      errorMsg: 'Failed to load company messages',
      onSuccess: (response) => {
        const data = response.data || {};
        this.currentUserId = parseInt(data.current_user_id || 0, 10);
        this.users = Array.isArray(data.users) ? data.users : [];
        const inbox = Array.isArray(data.inbox) ? data.inbox : [];
        const sent = Array.isArray(data.sent) ? data.sent : [];
        this.messagePool = [...inbox, ...sent];

        const unreadCount = parseInt(data.unread_count || 0, 10);
        $('#messageUnreadBadge').text(String(unreadCount));
        $('#globalMessageUnreadBadge').text(String(unreadCount));
        $('#globalMessageUnreadBadge').toggleClass('badge-danger', unreadCount > 0).toggleClass('badge-light', unreadCount <= 0);

        if (this.activeConversationUserId <= 0 && this.users.length) {
          this.activeConversationUserId = parseInt(this.users[0].user_id || 0, 10);
        }

        this.renderConversationList();
        this.renderActiveConversation();
      },
      onComplete: () => {
        this.isLoadingMessages = false;
      }
    });
  }

  getUserById(userId) {
    return this.users.find((item) => parseInt(item.user_id || 0, 10) === parseInt(userId || 0, 10)) || null;
  }

  buildConversationRows() {
    return this.users.map((user) => {
      const userId = parseInt(user.user_id || 0, 10);
      const messages = this.messagePool
        .filter((message) => {
          const senderId = parseInt(message.sender_user_id || 0, 10);
          const recipientId = parseInt(message.recipient_user_id || 0, 10);
          return (senderId === userId && recipientId === this.currentUserId)
            || (recipientId === userId && senderId === this.currentUserId);
        })
        .sort((a, b) => parseInt(b.created_at || 0, 10) - parseInt(a.created_at || 0, 10));

      const latest = messages[0] || null;
      const unread = messages.filter((message) => {
        const senderId = parseInt(message.sender_user_id || 0, 10);
        return senderId === userId && parseInt(message.is_read || 0, 10) === 0;
      }).length;

      return {
        user,
        userId,
        latest,
        unread
      };
    }).sort((a, b) => {
      const at = parseInt(a.latest?.created_at || 0, 10);
      const bt = parseInt(b.latest?.created_at || 0, 10);
      return bt - at;
    });
  }

  renderConversationList() {
    const rows = this.buildConversationRows().filter((row) => {
      if (!this.chatFilter) return true;
      const hay = `${row.user.full_name || ''} ${row.user.email || ''} ${row.user.role_name || ''}`.toLowerCase();
      return hay.includes(this.chatFilter);
    });

    const $container = $('#chatConversationList');
    if (!rows.length) {
      $container.html('<div class="text-muted p-3">No matching teammates.</div>');
      return;
    }

    const html = rows.map((row) => {
      const activeClass = row.userId === this.activeConversationUserId ? 'is-active' : '';
      const name = AppCore.escapeHtml(row.user.full_name || 'User');
      const meta = AppCore.escapeHtml(row.user.role_name || 'User');
      const previewRaw = row.latest ? (row.latest.body || row.latest.subject || '') : 'No messages yet';
      const preview = AppCore.escapeHtml(previewRaw).slice(0, 58);
      const time = row.latest ? this.app.formatDateSafe(row.latest.created_at, '-') : '-';
      const unreadBadge = row.unread > 0 ? `<span class="badge badge-success">${row.unread}</span>` : '';
      return `
        <button type="button" class="chat-conversation-item ${activeClass}" data-user-id="${row.userId}">
          <div class="d-flex justify-content-between align-items-start">
            <div class="text-left">
              <div class="font-weight-bold">${name}</div>
              <small class="text-muted d-block">${meta}</small>
              <small class="text-muted d-block">${preview}</small>
            </div>
            <div class="text-right ml-2">
              <small class="text-muted d-block">${time}</small>
              ${unreadBadge}
            </div>
          </div>
        </button>
      `;
    }).join('');

    $container.html(html);
  }

  getActiveConversationMessages() {
    const activeUserId = parseInt(this.activeConversationUserId || 0, 10);
    if (activeUserId <= 0) return [];

    return this.messagePool
      .filter((message) => {
        const senderId = parseInt(message.sender_user_id || 0, 10);
        const recipientId = parseInt(message.recipient_user_id || 0, 10);
        return (senderId === activeUserId && recipientId === this.currentUserId)
          || (recipientId === activeUserId && senderId === this.currentUserId);
      })
      .sort((a, b) => parseInt(a.created_at || 0, 10) - parseInt(b.created_at || 0, 10));
  }

  renderActiveConversation() {
    const activeUser = this.getUserById(this.activeConversationUserId);
    const $thread = $('#chatThread');

    if (!activeUser) {
      $('#chatActiveName').text('Select a teammate');
      $('#chatActiveMeta').text('Use this channel for information, reports, and suggestions.');
      $thread.html('<div class="chat-empty">Choose a teammate from the left to start messaging.</div>');
      return;
    }

    $('#chatActiveName').text(activeUser.full_name || 'User');
    $('#chatActiveMeta').text(`${activeUser.role_name || 'User'} | ${activeUser.email || ''}`);

    const messages = this.getActiveConversationMessages();
    if (!messages.length) {
      $thread.html('<div class="chat-empty">No messages yet. Say hello.</div>');
      return;
    }

    const html = messages.map((message) => {
      const senderId = parseInt(message.sender_user_id || 0, 10);
      const outgoing = senderId === this.currentUserId;
      const bubbleClass = outgoing ? 'chat-bubble-out' : 'chat-bubble-in';
      const status = outgoing
        ? (parseInt(message.is_read || 0, 10) === 1 ? 'Read' : 'Sent')
        : (parseInt(message.is_read || 0, 10) === 1 ? 'Read' : 'Unread');
      const body = AppCore.escapeHtml(message.body || '').replace(/\n/g, '<br>');
      const category = AppCore.escapeHtml(message.category || 'info');
      const subject = AppCore.escapeHtml(message.subject || 'Message');
      const time = this.app.formatDateSafe(message.created_at, '-');
      const markBtn = (!outgoing && parseInt(message.is_read || 0, 10) === 0)
        ? `<button type="button" class="btn btn-sm btn-outline-success message-read-btn" data-message-id="${parseInt(message.message_id || 0, 10)}">Mark read</button>`
        : '';

      return `
        <div class="chat-bubble-row ${outgoing ? 'is-out' : 'is-in'}">
          <div class="chat-bubble ${bubbleClass}">
            <div class="chat-bubble-title">${subject}</div>
            <div class="chat-bubble-category">${category}</div>
            <div class="chat-bubble-body">${body}</div>
            <div class="chat-bubble-meta">${time} • ${status}</div>
            ${markBtn}
          </div>
        </div>
      `;
    }).join('');

    $thread.html(html);
    $thread.scrollTop($thread[0].scrollHeight);
  }

  selectConversation(userId) {
    this.activeConversationUserId = parseInt(userId || 0, 10);
    this.renderConversationList();
    this.renderActiveConversation();
    this.openMobileConversation();
  }

  sendMessage() {
    const recipientUserId = parseInt(this.activeConversationUserId || 0, 10);
    const category = $('#messageCategory').val();
    const subject = $('#messageSubject').val().trim();
    const body = $('#messageBody').val().trim();

    if (Number.isNaN(recipientUserId) || recipientUserId <= 0) {
      this.app.showAlert('Select a colleague to receive the message', 'error');
      return;
    }

    if (!subject) {
      this.app.showAlert('Message subject is required', 'error');
      return;
    }

    if (!body) {
      this.app.showAlert('Message body is required', 'error');
      return;
    }

    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'sendMessage',
      data: {
        recipient_user_id: recipientUserId,
        category,
        subject: subject || `${category.toUpperCase()} update`,
        body
      },
      successMsg: 'Message sent successfully',
      errorMsg: 'Failed to send message',
      onSuccess: () => {
        $('#messageBody').val('');
        $('#messageSubject').val('');
        this.loadMessagingData();
      }
    });
  }

  markMessageRead(messageId) {
    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'markMessageRead',
      data: { message_id: messageId },
      silent: true,
      errorMsg: 'Failed to update message',
      onSuccess: () => {
        this.loadMessagingData();
      }
    });
  }
}

$(document).ready(() => {
  new UserProfilePage();
});
