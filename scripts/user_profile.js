class UserProfilePage {
  constructor() {
    const csrf   = $('meta[name="csrf-token"]').attr('content') || '';
    this.app     = new AppCore(csrf);
    this.bindEvents();
    this.initialize();
  }

  initialize() {
    this.loadUserProfile();
    this.loadMessagingData();
  }

  bindEvents() {
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

    $('#refreshMessagesBtn').on('click', () => {
      this.loadMessagingData();
    });

    $(document).on('click', '.message-read-btn', (e) => {
      const messageId = parseInt($(e.currentTarget).data('messageId'), 10);
      if (!Number.isNaN(messageId) && messageId > 0) {
        this.markMessageRead(messageId);
      }
    });
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
    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'loadMessagingData',
      silent: true,
      errorMsg: 'Failed to load company messages',
      onSuccess: (response) => {
        const data = response.data || {};
        this.renderMessagingUsers(data.users || []);
        this.renderInbox(data.inbox || []);
        this.renderSent(data.sent || []);
        $('#messageUnreadBadge').text(`Unread: ${parseInt(data.unread_count || 0, 10)}`);
      }
    });
  }

  renderMessagingUsers(users) {
    const $select = $('#messageRecipient');
    $select.empty();
    $select.append('<option value="">Select colleague</option>');

    if (!Array.isArray(users) || !users.length) {
      $select.append('<option value="" disabled>No other active users found</option>');
      return;
    }

    users.forEach((user) => {
      const userId = parseInt(user.user_id || 0, 10);
      const label = `${user.full_name || 'User'} (${user.role_name || 'User'}) - ${user.email || ''}`;
      $select.append(`<option value="${userId}">${AppCore.escapeHtml(label)}</option>`);
    });
  }

  renderInbox(messages) {
    this.renderMessageList({
      selector: '#messageInboxList',
      messages,
      mode: 'inbox',
      emptyText: 'No messages received yet.'
    });
  }

  renderSent(messages) {
    this.renderMessageList({
      selector: '#messageSentList',
      messages,
      mode: 'sent',
      emptyText: 'No messages sent yet.'
    });
  }

  renderMessageList({ selector, messages, mode, emptyText }) {
    const $container = $(selector);
    if (!Array.isArray(messages) || !messages.length) {
      $container.html(`<div class="list-group-item text-muted">${AppCore.escapeHtml(emptyText)}</div>`);
      return;
    }

    const html = messages.map((message) => {
      const subject = AppCore.escapeHtml(message.subject || '(No subject)');
      const body = AppCore.escapeHtml(message.body || '').replace(/\n/g, '<br>');
      const category = AppCore.escapeHtml(message.category || 'info');
      const createdAt = this.app.formatDateSafe(message.created_at, '-');
      const isRead = parseInt(message.is_read || 0, 10) === 1;
      const partyName = mode === 'inbox'
        ? AppCore.escapeHtml(message.sender_name || 'Unknown user')
        : AppCore.escapeHtml(message.recipient_name || 'Unknown user');
      const partyEmail = mode === 'inbox'
        ? AppCore.escapeHtml(message.sender_email || '')
        : AppCore.escapeHtml(message.recipient_email || '');
      const badgeClass = category === 'report'
        ? 'badge-warning'
        : category === 'suggestion'
          ? 'badge-primary'
          : 'badge-info';
      const readButton = mode === 'inbox' && !isRead
        ? `<button type="button" class="btn btn-sm btn-outline-success message-read-btn" data-message-id="${parseInt(message.message_id || 0, 10)}">Mark read</button>`
        : '';

      return `
        <div class="list-group-item ${mode === 'inbox' && !isRead ? 'bg-light' : ''}">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <div class="font-weight-bold">${subject}</div>
              <div class="small text-muted">${mode === 'inbox' ? 'From' : 'To'}: ${partyName}${partyEmail ? ` &lt;${partyEmail}&gt;` : ''}</div>
            </div>
            <div class="text-right ml-2">
              <span class="badge ${badgeClass}">${category}</span>
              <div class="small text-muted mt-1">${createdAt}</div>
            </div>
          </div>
          <div class="small mb-2" style="white-space: normal;">${body}</div>
          <div class="d-flex justify-content-between align-items-center">
            <span class="small ${isRead ? 'text-success' : 'text-warning'}">${isRead ? 'Read' : 'Unread'}</span>
            ${readButton}
          </div>
        </div>
      `;
    }).join('');

    $container.html(html);
  }

  sendMessage() {
    const recipientUserId = parseInt($('#messageRecipient').val(), 10);
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
        subject,
        body
      },
      successMsg: 'Message sent successfully',
      errorMsg: 'Failed to send message',
      onSuccess: () => {
        $('#messageForm')[0].reset();
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
