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
    this.pendingAutoScroll = false;
    this.lastRenderedConversationUserId = 0;
    // Smart-scroll state
    this.userScrolledUp = false;
    this.seenMessageIds = new Set();
    this.newMsgCount = 0;
    // IntersectionObserver for auto-marking messages read
    this._readObserver = null;
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

    // Thread scroll — track whether user has scrolled up
    $('#chatThread').on('scroll', () => {
      this.onThreadScroll();
    });

    // New-message badge click — jump to bottom
    $('#chatNewMsgBadge').on('click', () => {
      this.userScrolledUp = false;
      this.hideNewMsgBadge();
      this.scrollThreadToBottom(true);
    });

    // Auto-grow textarea
    $('#messageBody').on('input', function () {
      this.style.height = 'auto';
      this.style.height = `${Math.min(this.scrollHeight, 150)}px`;
    });

    // Ctrl+Enter to send
    $('#messageBody').on('keydown', (e) => {
      if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        this.sendMessage();
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
    $('#refreshMessagesBtn i').addClass('fa-spin');
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
        $('#messageUnreadBadge').text(unreadCount > 0 ? String(unreadCount) : '');
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
        $('#refreshMessagesBtn i').removeClass('fa-spin');
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
      $container.html('<div class="chat-conv-empty"><i class="fas fa-user-friends fa-2x mb-2 d-block"></i>No matching teammates.</div>');
      return;
    }

    const html = rows.map((row) => {
      const activeClass = row.userId === this.activeConversationUserId ? 'is-active' : '';
      const name = AppCore.escapeHtml(row.user.full_name || 'User');
      const initials = UserProfilePage.avatarInitials(row.user.full_name || 'U');
      const color = UserProfilePage.avatarColor(row.user.full_name || 'U');
      const online = parseInt(row.user.is_online || 0, 10) === 1;
      let previewRaw = '';
      if (row.latest) {
        const isMine = parseInt(row.latest.sender_user_id || 0, 10) === this.currentUserId;
        previewRaw = (isMine ? 'You: ' : '') + (row.latest.body || row.latest.subject || '');
      } else {
        previewRaw = 'No messages yet';
      }
      const preview = AppCore.escapeHtml(previewRaw).slice(0, 55);
      const time = row.latest ? this.relativeTime(row.latest.created_at) : '';
      const unreadBadge = row.unread > 0 ? `<span class="chat-unread-badge">${row.unread}</span>` : '';
      const presenceDot = `<span class="chat-presence-dot ${online ? 'is-online' : 'is-offline'}" title="${online ? 'Online' : 'Offline'}"></span>`;
      return `
        <button type="button" class="chat-conversation-item ${activeClass}" data-user-id="${row.userId}">
          <div class="chat-avatar" style="background:${color}">${initials}</div>
          <div class="chat-conv-body">
            <div class="chat-conv-top">
              <span class="chat-conv-name-wrap">${presenceDot}<span class="chat-conv-name">${name}</span></span>
              <span class="chat-conv-time">${time}</span>
            </div>
            <div class="chat-conv-bottom">
              <span class="chat-conv-preview">${preview}</span>
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

  isThreadNearBottom() {
    const thread = $('#chatThread')[0];
    if (!thread) {
      return true;
    }

    const remaining = thread.scrollHeight - thread.scrollTop - thread.clientHeight;
    return remaining < 120;
  }

  scrollThreadToBottom(force = false) {
    const thread = $('#chatThread')[0];
    if (!thread) {
      return;
    }

    if (force || this.isThreadNearBottom()) {
      thread.scrollTop = thread.scrollHeight;
    }
  }

  renderActiveConversation() {
    const activeUser = this.getUserById(this.activeConversationUserId);
    const $thread = $('#chatThread');
    const activeConversationUserId = parseInt(this.activeConversationUserId || 0, 10);
    const isNewConversation = this.lastRenderedConversationUserId !== activeConversationUserId;

    if (isNewConversation) {
      this.userScrolledUp = false;
      this.seenMessageIds.clear();
      this.newMsgCount = 0;
      this.hideNewMsgBadge();
    }

    if (!activeUser) {
      $('#chatActiveName').text('Select a teammate');
      $('#chatActiveMeta').text('Choose someone from the list to start chatting');
      $('#chatHeadAvatar').html('');
      $thread.html('<div class="chat-empty"><i class="fas fa-comments fa-3x mb-3 d-block"></i>Choose a teammate from the left to start messaging.</div>');
      this.lastRenderedConversationUserId = 0;
      this.pendingAutoScroll = false;
      return;
    }

    // Update chat header
    const initials = UserProfilePage.avatarInitials(activeUser.full_name || 'U');
    const color = UserProfilePage.avatarColor(activeUser.full_name || 'U');
    const isOnline = parseInt(activeUser.is_online || 0, 10) === 1;
    $('#chatHeadAvatar').html(`<div class="chat-avatar chat-avatar-sm" style="background:${color}">${initials}</div>`);
    $('#chatActiveName').text(activeUser.full_name || 'User');
    if (isOnline) {
      $('#chatActiveMeta').html('<span class="chat-presence-dot is-online"></span> Active now');
    } else {
      const lastSeen = this.formatLastSeen(activeUser.last_seen_at);
      $('#chatActiveMeta').text(lastSeen ? `Last seen ${lastSeen}` : 'Last seen recently');
    }

    const messages = this.getActiveConversationMessages();
    if (!messages.length) {
      $thread.html('<div class="chat-empty"><i class="fas fa-comment-dots fa-3x mb-3 d-block"></i>No messages yet — say hello!</div>');
      this.seenMessageIds.clear();
      this.lastRenderedConversationUserId = activeConversationUserId;
      this.pendingAutoScroll = false;
      return;
    }

    // Detect newly arrived messages (IDs not seen in previous render)
    const newlyArrivedIds = !isNewConversation
      ? messages.filter((m) => !this.seenMessageIds.has(parseInt(m.message_id, 10))).map((m) => parseInt(m.message_id, 10))
      : [];

    // Update the seen-IDs set
    messages.forEach((m) => this.seenMessageIds.add(parseInt(m.message_id, 10)));

    // Find first unread incoming message index for divider
    const firstUnreadIdx = messages.findIndex((m) =>
      parseInt(m.sender_user_id || 0, 10) !== this.currentUserId &&
      parseInt(m.is_read || 0, 10) === 0
    );

    let html = '';
    let lastDateGroup = '';

    messages.forEach((message, index) => {
      const senderId = parseInt(message.sender_user_id || 0, 10);
      const outgoing = senderId === this.currentUserId;
      const ts = parseInt(message.created_at || 0, 10);
      const isRead = parseInt(message.is_read || 0, 10) === 1;
      const isDelivered = parseInt(message.is_delivered || 0, 10) === 1;
      const msgId = parseInt(message.message_id || 0, 10);

      // Date group separator
      const dateLabel = this.dateGroupLabel(ts);
      if (dateLabel !== lastDateGroup) {
        html += `<div class="chat-date-sep"><span>${dateLabel}</span></div>`;
        lastDateGroup = dateLabel;
      }

      // Unread divider before first unread incoming message
      if (index === firstUnreadIdx && firstUnreadIdx > 0) {
        const remaining = messages.length - firstUnreadIdx;
        html += `<div class="chat-unread-sep"><span><i class="fas fa-arrow-down mr-1"></i>${remaining} unread message${remaining !== 1 ? 's' : ''} below</span></div>`;
      }

      const body = AppCore.escapeHtml(message.body || '').replace(/\n/g, '<br>');
      const category = AppCore.escapeHtml(message.category || 'info');
      const subject = message.subject ? AppCore.escapeHtml(message.subject) : '';
      const timeStr = this.formatTime(ts);

      // 4-state tick indicator for outgoing messages
      let readIndicator = '';
      if (outgoing) {
        if (isRead) {
          readIndicator = '<span class="chat-tick tick-read" title="Read"><i class="fas fa-check-double"></i></span>';
        } else if (isDelivered) {
          readIndicator = '<span class="chat-tick tick-delivered" title="Delivered"><i class="fas fa-check-double"></i></span>';
        } else {
          readIndicator = '<span class="chat-tick tick-sent" title="Sent"><i class="fas fa-check"></i></span>';
        }
      }

      const isUnread = !outgoing && !isRead;
      const markBtn = isUnread
        ? `<button type="button" class="chat-mark-read-btn message-read-btn" data-message-id="${msgId}"><i class="fas fa-check mr-1"></i>Mark as read</button>`
        : '';

      // Add data attrs on incoming rows for IntersectionObserver
      const rowAttrs = !outgoing
        ? `data-message-id="${msgId}"${isUnread ? ' data-unread="1"' : ''}`
        : '';

      html += `
        <div class="chat-bubble-row ${outgoing ? 'is-out' : 'is-in'}" ${rowAttrs}>
          <div class="chat-bubble ${outgoing ? 'chat-bubble-out' : 'chat-bubble-in'}">
            ${subject ? `<div class="chat-bubble-title">${subject}</div>` : ''}
            <span class="chat-cat-chip ${category}">${category}</span>
            <div class="chat-bubble-body">${body}</div>
            <div class="chat-bubble-meta">
              <span>${timeStr}</span>
              ${readIndicator}
            </div>
            ${markBtn}
          </div>
        </div>
      `;
    });

    $thread.html(html);

    // ---- Scroll decision ----
    const shouldScroll = this.pendingAutoScroll || !this.userScrolledUp;
    if (shouldScroll) {
      this.scrollThreadToBottom(true);
      this.hideNewMsgBadge();
      this.newMsgCount = 0;
    } else if (newlyArrivedIds.length > 0) {
      this.newMsgCount += newlyArrivedIds.length;
      this.showNewMsgBadge(this.newMsgCount);
    }

    // IntersectionObserver: auto-mark incoming unread messages as read when visible
    this.setupReadObserver();

    this.lastRenderedConversationUserId = activeConversationUserId;
    this.pendingAutoScroll = false;
  }

  selectConversation(userId) {
    this.activeConversationUserId = parseInt(userId || 0, 10);
    this.pendingAutoScroll = true;
    this.userScrolledUp = false;
    this.seenMessageIds.clear();
    this.newMsgCount = 0;
    this.hideNewMsgBadge();
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

    // Optimistic bubble
    this.appendOptimisticBubble(subject, category, body);
    $('#messageBody').val('').css('height', 'auto');
    $('#messageSubject').val('');
    this.userScrolledUp = false;
    this.scrollThreadToBottom(true);

    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'sendMessage',
      data: {
        recipient_user_id: recipientUserId,
        category,
        subject: subject || `${category.toUpperCase()} update`,
        body
      },
      silent: true,
      errorMsg: 'Failed to send message',
      onSuccess: () => {
        this.pendingAutoScroll = true;
        this.userScrolledUp = false;
        this.loadMessagingData();
      },
      onError: () => {
        this.markOptimisticFailed();
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

  /* -------------------------------------------------------
   * Thread scroll tracker
   * ------------------------------------------------------- */
  onThreadScroll() {
    const nearBottom = this.isThreadNearBottom();
    if (nearBottom) {
      if (this.userScrolledUp) {
        this.userScrolledUp = false;
        this.hideNewMsgBadge();
        this.newMsgCount = 0;
      }
    } else {
      this.userScrolledUp = true;
    }
  }

  /* -------------------------------------------------------
   * New-message badge
   * ------------------------------------------------------- */
  showNewMsgBadge(count) {
    $('#chatNewMsgCount').text(count);
    $('#chatNewMsgBadge').addClass('visible');
  }

  hideNewMsgBadge() {
    $('#chatNewMsgBadge').removeClass('visible');
  }

  /* -------------------------------------------------------
   * Optimistic bubble for send
   * ------------------------------------------------------- */
  appendOptimisticBubble(subject, category, body) {
    const now = this.formatTime(Math.floor(Date.now() / 1000));
    const safeSubject = subject ? `<div class="chat-bubble-title">${AppCore.escapeHtml(subject)}</div>` : '';
    const safeCat = AppCore.escapeHtml(category || 'info');
    const safeBody = AppCore.escapeHtml(body || '').replace(/\n/g, '<br>');
    const html = `
      <div class="chat-bubble-row is-out" data-optimistic="true">
        <div class="chat-bubble chat-bubble-out">
          ${safeSubject}
          <span class="chat-cat-chip ${safeCat}">${safeCat}</span>
          <div class="chat-bubble-body">${safeBody}</div>
          <div class="chat-bubble-meta">
            <span>${now}</span>
            <span class="chat-tick tick-pending" title="Sending…"><i class="fas fa-clock"></i></span>
          </div>
        </div>
      </div>
    `;
    $('#chatThread').append(html);
  }

  markOptimisticFailed() {
    const $last = $('#chatThread [data-optimistic="true"]').last();
    $last.find('.chat-tick')
      .removeClass('tick-pending')
      .addClass('tick-failed')
      .attr('title', 'Not sent')
      .html('<i class="fas fa-exclamation-circle"></i>');
    $last.find('.chat-bubble-meta').append(
      ' <span style="font-size:0.7rem;color:#ef4444;">Not sent</span>'
    );
  }

  /* -------------------------------------------------------
   * IntersectionObserver: auto-mark incoming unread as read
   * ------------------------------------------------------- */
  setupReadObserver() {
    if (this._readObserver) {
      this._readObserver.disconnect();
      this._readObserver = null;
    }

    const thread = document.getElementById('chatThread');
    if (!thread) return;

    const unreadRows = thread.querySelectorAll('.chat-bubble-row.is-in[data-unread="1"]');
    if (!unreadRows.length) return;

    this._readObserver = new IntersectionObserver((entries) => {
      const toMark = [];
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const msgId = parseInt(entry.target.dataset.messageId, 10);
          if (msgId > 0) {
            toMark.push(msgId);
            this._readObserver.unobserve(entry.target);
          }
        }
      });
      if (toMark.length) {
        this.batchMarkRead(toMark);
      }
    }, { root: thread, threshold: 0.6 });

    unreadRows.forEach((el) => this._readObserver.observe(el));
  }

  /* -------------------------------------------------------
   * Batch mark-read + local update (no full re-render)
   * ------------------------------------------------------- */
  batchMarkRead(messageIds) {
    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'markMessagesRead',
      data: { message_ids: JSON.stringify(messageIds) },
      silent: true,
      errorMsg: null,
      onSuccess: () => {
        // Update local pool
        messageIds.forEach((id) => {
          const msg = this.messagePool.find((m) => parseInt(m.message_id, 10) === id);
          if (msg) {
            msg.is_read = 1;
            msg.read_at = Math.floor(Date.now() / 1000);
          }
        });

        // Remove the mark-read buttons and data-unread attrs from those rows
        messageIds.forEach((id) => {
          const $row = $(`#chatThread .chat-bubble-row.is-in[data-message-id="${id}"]`);
          $row.find('.chat-mark-read-btn').remove();
          $row.removeAttr('data-unread');
        });

        // Update unread count badges
        const unread = this.messagePool.filter((m) =>
          parseInt(m.recipient_user_id, 10) === this.currentUserId &&
          parseInt(m.is_read, 10) === 0
        ).length;
        $('#messageUnreadBadge').text(unread > 0 ? String(unread) : '');
        $('#globalMessageUnreadBadge').text(String(unread));
        $('#globalMessageUnreadBadge')
          .toggleClass('badge-danger', unread > 0)
          .toggleClass('badge-light', unread <= 0);

        // Refresh conversation list preview
        this.renderConversationList();
      }
    });
  }

  /* -------------------------------------------------------
   * Instance helpers: time formatting
   * ------------------------------------------------------- */
  relativeTime(ts) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - parseInt(ts || 0, 10);
    if (diff < 60)        return 'Just now';
    if (diff < 3600)      return `${Math.floor(diff / 60)}m`;
    if (diff < 86400)     return `${Math.floor(diff / 3600)}h`;
    if (diff < 86400 * 6) return `${Math.floor(diff / 86400)}d`;
    return this.app.formatDateSafe(ts, '-');
  }

  formatTime(ts) {
    if (!ts) return '';
    const d = new Date(parseInt(ts, 10) * 1000);
    return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
  }

  dateGroupLabel(ts) {
    const ms = parseInt(ts || 0, 10) * 1000;
    const msgDay = new Date(ms);
    msgDay.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (msgDay.getTime() === today.getTime())     return 'Today';
    if (msgDay.getTime() === yesterday.getTime()) return 'Yesterday';
    return new Date(ms).toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });
  }

  formatLastSeen(ts) {
    const seen = parseInt(ts || 0, 10);
    if (!seen) return '';

    const now = Math.floor(Date.now() / 1000);
    const diff = Math.max(0, now - seen);

    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;

    const d = new Date(seen * 1000);
    return d.toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  /* -------------------------------------------------------
   * Static helpers: avatar initials + color
   * ------------------------------------------------------- */
  static avatarInitials(name) {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }

  static avatarColor(name) {
    const palette = [
      '#075e54', '#0369a1', '#7c3aed', '#b45309',
      '#c2410c', '#047857', '#1d4ed8', '#9333ea',
      '#0e7490', '#b91c1c'
    ];
    let h = 0;
    const s = name || '';
    for (let i = 0; i < s.length; i++) {
      h = (h * 31 + s.charCodeAt(i)) & 0x7fffffff;
    }
    return palette[h % palette.length];
  }
}

$(document).ready(() => {
  new UserProfilePage();
});
