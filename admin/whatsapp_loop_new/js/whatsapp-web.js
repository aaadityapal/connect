// Dynamic data for WhatsApp Web UI Clone connected to MySQL backend
let MOCK_CHATS = [];
let MOCK_CONTACTS = [];

function fetchDatabaseChats() {
  const chatListEl = document.getElementById('wa-chat-list');
  
  // Show subtle loading state inside chat list
  if (chatListEl && MOCK_CHATS.length === 0) {
    chatListEl.innerHTML = `<div style="padding: 20px; text-align: center; color: var(--wa-text-sub); font-size: 14px;"><svg class="wa-animate-spin" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 8px auto; display: block;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>Connecting to database...</div>`;
  }

  fetch('api/get_whatsapp_chats.php')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        MOCK_CHATS = data.chats;
        MOCK_CONTACTS = data.contacts;

        // Fallback welcome chats if the database is completely empty
        if (MOCK_CHATS.length === 0 && MOCK_CONTACTS.length === 0) {
          MOCK_CHATS = [
            {
              id: 9999,
              name: "Aditya (Creator)",
              avatar: "https://ui-avatars.com/api/?name=Aditya&background=00a884&color=fff",
              lastMsg: "Welcome to your workable WhatsApp Web clone! Add contacts above to start chatting.",
              time: "Just Now",
              unread: 1,
              messages: [
                { text: "Welcome to your workable WhatsApp Web clone! Add contacts above to start chatting.", time: "Just Now", type: "in" }
              ]
            }
          ];
          MOCK_CONTACTS = [
            { id: 9999, name: "Aditya (Creator)", phone: "System Admin", avatar: "https://ui-avatars.com/api/?name=Aditya&background=00a884&color=fff" }
          ];
        }

        renderList();

        // If a chat is active, update its messages dynamically in real-time
        if (activeChatId) {
          const activeChat = MOCK_CHATS.find(c => c.id === activeChatId);
          if (activeChat) {
            renderMessages(activeChat);
            renderWindowTimer(activeChat);
          }
        }
      }
    })
    .catch(err => {
      console.error("API error fetching chats: ", err);
      if (chatListEl) {
        chatListEl.innerHTML = `<div style="padding: 20px; text-align: center; color: #ea4335; font-size: 13px;">Failed to fetch chats from database. Please verify backend configurations.</div>`;
      }
    });
}

let currentView = 'chats'; // 'chats' or 'contacts'
let activeChatId = null;
let searchQuery = '';
let windowTimerInterval = null;
let WA_AVAILABLE_TAGS = [];
let waSelectedTags = new Set();

function initWhatsappWeb() {
  currentView = 'chats';
  searchQuery = '';
  const searchInput = document.getElementById('wa-search-input');
  if (searchInput) searchInput.value = '';
  
  // Load real chats from the database
  fetchDatabaseChats();
  fetchWaTags();
  setupEventListeners();
}

function fetchWaTags() {
  fetch('api/get_tags.php')
    .then(res => res.json())
    .then(data => {
      if (Array.isArray(data)) {
        WA_AVAILABLE_TAGS = data.map(t => t.name);
        renderWaTagSelector();
      }
    })
    .catch(err => {
      console.error('Error fetching tags:', err);
      renderWaTagSelector();
    });
}

function renderWaTagSelector() {
  const container = document.getElementById('wa-tag-selector');
  if (!container) return;
  container.innerHTML = WA_AVAILABLE_TAGS.map(t => {
    const active = waSelectedTags.has(t) ? 'active' : '';
    return `<button type="button" class="tag-option ${active}" data-tag="${t}" onclick="toggleWaTag(this)">${t}</button>`;
  }).join('');
}

function toggleWaTag(el) {
  const tag = el?.dataset?.tag;
  if (!tag) return;
  if (waSelectedTags.has(tag)) {
    waSelectedTags.delete(tag);
    el.classList.remove('active');
  } else {
    waSelectedTags.add(tag);
    el.classList.add('active');
  }
}

function addWaCustomTag() {
  const input = document.getElementById('wa-custom-tag-input');
  const container = document.getElementById('wa-tag-selector');
  if (!input || !container) return;

  const val = input.value.trim();
  if (!val) return;

  if (!WA_AVAILABLE_TAGS.includes(val)) {
    WA_AVAILABLE_TAGS.push(val);
  }

  waSelectedTags.add(val);
  renderWaTagSelector();
  input.value = '';
}

window.addWaCustomTag = addWaCustomTag;
window.toggleWaTag = toggleWaTag;

function renderList() {
  const headerEl = document.getElementById('wa-list-header');
  const viewContactsBtn = document.getElementById('wa-view-contacts-btn');
  const backChatsBtn = document.getElementById('wa-back-chats-btn');

  if (currentView === 'chats') {
    if (headerEl) headerEl.querySelector('span').textContent = 'Chats';
    if (viewContactsBtn) viewContactsBtn.classList.remove('hidden');
    if (backChatsBtn) backChatsBtn.classList.add('hidden');
    renderMockChatList();
  } else {
    if (headerEl) headerEl.querySelector('span').textContent = 'Contacts';
    if (viewContactsBtn) viewContactsBtn.classList.add('hidden');
    if (backChatsBtn) backChatsBtn.classList.remove('hidden');
    renderMockContactList();
  }
}

function renderMockChatList() {
  const chatListEl = document.getElementById('wa-chat-list');
  if (!chatListEl) return;

  let chatsToRender = MOCK_CHATS;
  if (searchQuery) {
    chatsToRender = MOCK_CHATS.filter(chat => {
      const name = (chat.name || '').toLowerCase();
      const lastMsg = (chat.lastMsg || '').toLowerCase();
      const phone = (chat.phone || '').toLowerCase();
      return name.includes(searchQuery) || lastMsg.includes(searchQuery) || phone.includes(searchQuery);
    });
  }

  if (chatsToRender.length === 0) {
    chatListEl.innerHTML = `<div style="padding: 20px; text-align: center; color: var(--wa-text-sub); font-size: 14px;">No chats found matching "${searchQuery}"</div>`;
    return;
  }

  chatListEl.innerHTML = chatsToRender.map(chat => `
    <div class="wa-chat-item ${activeChatId === chat.id ? 'active' : ''}" data-id="${chat.id}" onclick="openMockChat(${chat.id})">
      <div class="wa-chat-item-avatar">
        <img src="${chat.avatar}" alt="${chat.name}" class="wa-avatar">
      </div>
      <div class="wa-chat-item-info">
        <div class="wa-chat-item-top">
          <span class="wa-chat-item-name">${chat.name}</span>
          <span class="wa-chat-item-time" style="${chat.unread > 0 ? 'color: var(--wa-badge)' : ''}">${chat.time}</span>
        </div>
        <div class="wa-chat-item-bottom">
          <span class="wa-chat-item-msg">${chat.lastMsg}</span>
          ${chat.unread > 0 ? `<span class="wa-unread-badge">${chat.unread}</span>` : ''}
        </div>
      </div>
    </div>
  `).join('');
}

function renderMockContactList() {
  const chatListEl = document.getElementById('wa-chat-list');
  if (!chatListEl) return;

  let contactsToRender = MOCK_CONTACTS;
  if (searchQuery) {
    contactsToRender = MOCK_CONTACTS.filter(contact => 
      contact.name.toLowerCase().includes(searchQuery) || 
      contact.phone.toLowerCase().includes(searchQuery)
    );
  }

  if (contactsToRender.length === 0) {
    chatListEl.innerHTML = `<div style="padding: 20px; text-align: center; color: var(--wa-text-sub); font-size: 14px;">No contacts found matching "${searchQuery}"</div>`;
    return;
  }

  chatListEl.innerHTML = contactsToRender.map(contact => `
    <div class="wa-chat-item" onclick="startChatWithContact(${contact.id}, '${contact.name}', '${contact.avatar}')">
      <div class="wa-chat-item-avatar">
        <img src="${contact.avatar}" alt="${contact.name}" class="wa-avatar">
      </div>
      <div class="wa-chat-item-info">
        <div class="wa-chat-item-top">
          <span class="wa-chat-item-name">${contact.name}</span>
        </div>
        <div class="wa-chat-item-bottom">
          <span class="wa-chat-item-msg" style="color: var(--wa-text-meta);">${contact.phone}</span>
        </div>
      </div>
    </div>
  `).join('');
}

function startChatWithContact(id, name, avatar) {
  // Check if chat already exists
  let chat = MOCK_CHATS.find(c => c.id === id);
  if (!chat) {
    // Create new temporary in-memory chat
    chat = {
      id: id,
      name: name,
      avatar: avatar,
      lastMsg: "New chat started.",
      time: "Just now",
      unread: 0,
      messages: [
        { text: `Hey there! This is the start of your chat history with ${name}.`, time: "Just now", type: "in" }
      ]
    };
    MOCK_CHATS.unshift(chat);
  }

  // Switch back to chats view
  currentView = 'chats';
  activeChatId = chat.id;
  renderList();
  openMockChat(chat.id);
}

function openMockChat(chatId) {
  activeChatId = chatId;
  const chat = MOCK_CHATS.find(c => c.id === chatId);
  if (!chat) return;

  // Hide emoji picker
  document.getElementById('wa-emoji-picker')?.classList.add('hidden');

  // Clear unread unread
  chat.unread = 0;
  renderMockChatList();

  // Update selection style
  document.querySelectorAll('.wa-chat-item').forEach(el => el.classList.remove('active'));
  document.querySelector(`.wa-chat-item[data-id="${chatId}"]`)?.classList.add('active');

  // Hide empty state, show active chat
  document.getElementById('wa-empty-state').classList.add('hidden');
  document.getElementById('wa-active-chat').classList.remove('hidden');

  // Update header
  document.getElementById('wa-chat-avatar').src = chat.avatar;
  document.getElementById('wa-chat-title').textContent = chat.name;
  document.getElementById('wa-chat-status').textContent = 'Online';
  renderWindowTimer(chat);

  // Render messages
  renderMessages(chat);
}

function renderWindowTimer(chat) {
  const timerEl = document.getElementById('wa-window-timer');
  if (!timerEl) return;

  if (windowTimerInterval) {
    clearInterval(windowTimerInterval);
    windowTimerInterval = null;
  }

  const windowMeta = chat.window || null;
  if (!windowMeta || !windowMeta.endTs) {
    timerEl.textContent = '24h window: waiting for reply';
    timerEl.classList.remove('wa-window-closed');
    return;
  }

  const endTs = windowMeta.endTs * 1000;

  const updateTimer = () => {
    const now = Date.now();
    const remainingMs = endTs - now;
    if (remainingMs <= 0) {
      timerEl.textContent = '24h window: closed';
      timerEl.classList.add('wa-window-closed');
      return;
    }

    const totalSeconds = Math.floor(remainingMs / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    const pad = (v) => String(v).padStart(2, '0');
    timerEl.textContent = `24h window: open · ${pad(hours)}:${pad(minutes)}:${pad(seconds)} remaining`;
    timerEl.classList.remove('wa-window-closed');
  };

  updateTimer();
  windowTimerInterval = setInterval(updateTimer, 1000);
}

function renderMessages(chat) {
  const bodyEl = document.getElementById('wa-chat-body');
  if (!bodyEl) return;

  // Render high-fidelity custom checkmark SVGs based on delivery status
  // Sent = single gray, Delivered = double gray, Read/Replied = double blue
  const checkmarkSent = `<svg viewBox="0 0 16 11" height="11" width="16" fill="none" style="margin-left:3px;"><path d="M1 5.5L5 9.5L11 1.5" stroke="#8696a0" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
  const checkmarkDelivered = `<svg viewBox="0 0 16 11" height="11" width="16" fill="none" style="margin-left:3px;"><path d="M1 5.5L5 9.5L11 1.5" stroke="#8696a0" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 5.5L9 9.5L15 1.5" stroke="#8696a0" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
  const checkmarkRead = `<svg viewBox="0 0 16 11" height="11" width="16" fill="none" style="margin-left:3px;"><path d="M1 5.5L5 9.5L11 1.5" stroke="#53bdeb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 5.5L9 9.5L15 1.5" stroke="#53bdeb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

  function getStatusCheckmark(status) {
    const s = (status || '').toLowerCase();
    if (s === 'delivered') return checkmarkDelivered;
    if (s === 'read' || s === 'seen' || s === 'replied') return checkmarkRead;
    return checkmarkSent;
  }

  let lastDate = '';
  let messagesHTML = '';

  chat.messages.forEach(m => {
    // Dynamic Date Divider Badge
    const msgDate = m.date || 'TODAY';
    if (msgDate !== lastDate) {
      messagesHTML += `<div class="wa-date-badge">${msgDate.toUpperCase()}</div>`;
      lastDate = msgDate;
    }

    messagesHTML += `
      <div class="wa-msg wa-msg-${m.type}">
        <div class="wa-msg-text">${m.text}</div>
        <div class="wa-msg-meta">
          ${m.time}
          ${m.type === 'out' ? getStatusCheckmark(m.status) : ''}
        </div>
      </div>
    `;
  });

  bodyEl.innerHTML = messagesHTML;

  // Scroll to bottom
  bodyEl.scrollTop = bodyEl.scrollHeight;
}

function setupEventListeners() {
  // Modal open
  document.getElementById('wa-add-contact-btn')?.addEventListener('click', () => {
    document.getElementById('wa-add-contact-modal').classList.remove('hidden');
  });

  // Modal close
  const closeModal = () => {
    document.getElementById('wa-add-contact-modal').classList.add('hidden');
    document.getElementById('wa-contact-name').value = '';
    document.getElementById('wa-contact-phone').value = '';
    const ccEl = document.getElementById('wa-country-code');
    if (ccEl) ccEl.value = '91';
    waSelectedTags.clear();
    renderWaTagSelector();
  };

  document.getElementById('wa-modal-close-btn')?.addEventListener('click', closeModal);
  document.getElementById('wa-modal-btn-cancel')?.addEventListener('click', closeModal);

  // Add Contact Save
  document.getElementById('wa-modal-btn-save')?.addEventListener('click', () => {
    const name = document.getElementById('wa-contact-name').value.trim();
    const phone = document.getElementById('wa-contact-phone').value.trim();
    const countryCodeEl = document.getElementById('wa-country-code');
    const countryCode = countryCodeEl ? countryCodeEl.value.trim() : '91';

    if (!name || !phone) {
      alert('Please fill out both the Name and Phone Number fields.');
      return;
    }

    // Save to clients table via API
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    const formattedPhone = `+${countryCode} ${cleanPhone}`;
    const tags = Array.from(waSelectedTags);

    fetch('api/save_client.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: name,
        phone: formattedPhone,
        email: '',
        notes: '',
        tags: tags
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data && data.id) {
        closeModal();
        waSelectedTags.clear();
        renderWaTagSelector();
        currentView = 'contacts';
        fetchDatabaseChats();
      } else {
        alert(data.message || 'Unable to save contact.');
      }
    })
    .catch(err => {
      console.error('Save contact error:', err);
      alert('Unable to save contact. Please try again.');
    });
  });

  // View Contacts Toggle
  document.getElementById('wa-view-contacts-btn')?.addEventListener('click', () => {
    currentView = 'contacts';
    searchQuery = '';
    const searchInput = document.getElementById('wa-search-input');
    if (searchInput) searchInput.value = '';
    renderList();
  });

  // Back to Chats Toggle
  document.getElementById('wa-back-chats-btn')?.addEventListener('click', () => {
    currentView = 'chats';
    searchQuery = '';
    const searchInput = document.getElementById('wa-search-input');
    if (searchInput) searchInput.value = '';
    renderList();
  });

  // Message Sender Input Box
  const inputEl = document.getElementById('wa-message-input');
  if (inputEl) {
    // Remove old listeners to avoid duplicates
    const newEl = inputEl.cloneNode(true);
    inputEl.parentNode.replaceChild(newEl, inputEl);

    newEl.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        sendMessage();
      }
    });
  }

  // Real-time Search Box Input Listener
  const searchInput = document.getElementById('wa-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      searchQuery = e.target.value.toLowerCase().trim();
      if (currentView === 'chats') {
        renderMockChatList();
      } else {
        renderMockContactList();
      }
    });
  }

  // EMOJI PICKER LISTENERS
  const emojiBtn = document.getElementById('wa-emoji-btn');
  const emojiPicker = document.getElementById('wa-emoji-picker');

  if (emojiBtn && emojiPicker) {
    emojiBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      emojiPicker.classList.toggle('hidden');
    });

    // Delegate clicks on emojis
    emojiPicker.querySelector('.wa-emoji-grid')?.addEventListener('click', (e) => {
      const emojiSpan = e.target.closest('span');
      if (emojiSpan) {
        const emoji = emojiSpan.textContent;
        const msgInput = document.getElementById('wa-message-input');
        if (msgInput) {
          msgInput.value += emoji;
          msgInput.focus();
        }
      }
    });
  }

  // Click outside to close Emoji Picker
  document.addEventListener('click', (e) => {
    if (emojiPicker && !emojiPicker.classList.contains('hidden')) {
      if (!e.target.closest('#wa-emoji-picker') && !e.target.closest('#wa-emoji-btn')) {
        emojiPicker.classList.add('hidden');
      }
    }
  });
}

function sendMessage() {
  const inputEl = document.getElementById('wa-message-input');
  if (!inputEl) return;

  const text = inputEl.value.trim();
  if (!text) return;

  const chat = MOCK_CHATS.find(c => c.id === activeChatId);
  if (!chat) return;

  // Clear input immediately to make the chat feel fast and responsive!
  inputEl.value = '';
  document.getElementById('wa-emoji-picker')?.classList.add('hidden');

  // Optimistically append the sent message in real-time
  const now = new Date();
  const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  
  chat.messages.push({
    id: 'temp_' + Date.now(),
    text: text,
    time: timeStr,
    type: 'out',
    status: 'Sent'
  });
  chat.lastMsg = text;
  chat.time = timeStr;

  renderMessages(chat);
  renderMockChatList();

  // POST direct message request to backend database
  fetch('api/send_whatsapp_direct.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      client_id: activeChatId,
      text: text
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      // Re-fetch chats after 1.5 seconds so that the mock replies & real statuses sync perfectly!
      setTimeout(() => {
        fetchDatabaseChats();
      }, 1500);
    } else {
      console.error("Direct message failed: ", data.error);
    }
  })
  .catch(err => {
    console.error("API send_whatsapp_direct error: ", err);
  });
}

// Global hook for app router
window.initWhatsappWeb = initWhatsappWeb;
