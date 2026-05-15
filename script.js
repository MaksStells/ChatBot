var messagesList = document.getElementById("messages");
var textInput    = document.getElementById("userInput");
var sendButton   = document.getElementById("sendBtn");
var chatList     = document.getElementById("chat-list");

var currentChatId = null;
var clientHistory = []; 

var userPfpUrl = "";

fetch("profile.php?action=get")
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success && data.profile.pfp_url) {
      userPfpUrl = data.profile.pfp_url;
    }
  });

textInput.addEventListener("input", function() {
  textInput.style.height = "auto";
  textInput.style.height = textInput.scrollHeight + "px";
});

textInput.addEventListener("keydown", function(e) {
  if (e.key === "Enter" && !e.shiftKey) {
    e.preventDefault();
    if (!sendButton.disabled) sendMessage();
  }
});

sendButton.addEventListener("click", function() {
  sendMessage();
});

var topicButtons = document.querySelectorAll(".topic-btn[data-msg]");
for (var i = 0; i < topicButtons.length; i++) {
  topicButtons[i].addEventListener("click", function() {
    textInput.value = this.getAttribute("data-msg");
    sendMessage();
  });
}


// Track the current group so bot replies join the same group as the user msg
var currentGroup = null;
var currentGroupStartedWith = null;
var lastMessageTime = null; 

var TEN_MINUTES = 10 * 60 * 1000;

function formatTime(date) {
  var h = date.getHours();
  var m = date.getMinutes();
  var ampm = h >= 12 ? "pm" : "am";
  h = h % 12 || 12;
  return h + ":" + (m < 10 ? "0" : "") + m + " " + ampm;
}

function addMessage(who, text, timestamp) {
  var now = timestamp ? new Date(timestamp) : new Date();
  var messageDiv = document.createElement("div");
  var pfpDiv  = document.createElement("div");
  var bubbleDiv  = document.createElement("div");

  messageDiv.className = "message " + who;
  pfpDiv.className  = "pfp";
  bubbleDiv.className  = "bubble";

  if (who === "bot") {
    pfpDiv.textContent = "B";
  } else if (userPfpUrl !== "") {
    pfpDiv.innerHTML = '<img src="' + userPfpUrl + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />';
  } else {
    pfpDiv.textContent = "Me";
  }
  bubbleDiv.textContent = text;
  messageDiv.appendChild(pfpDiv);
  messageDiv.appendChild(bubbleDiv);

  if (who === "user") {
    // Only show the time divider if gap since last group > 10 minutes
    var showDivider = !lastMessageTime || (now - lastMessageTime) > TEN_MINUTES;
    lastMessageTime = now;

    currentGroup = document.createElement("div");
    currentGroup.className = "message-group";

    if (showDivider) {
      var timeLabel = document.createElement("div");
      timeLabel.className = "message-group-time";
      timeLabel.textContent = formatTime(now);
      currentGroup.appendChild(timeLabel);
    }

    currentGroup.appendChild(messageDiv);
    messagesList.appendChild(currentGroup);
  } else {
    if (currentGroup) {
      currentGroup.appendChild(messageDiv);
    } else {
      currentGroup = document.createElement("div");
      currentGroup.className = "message-group";
      currentGroup.appendChild(messageDiv);
      messagesList.appendChild(currentGroup);
    }
    currentGroup = null;
  }

  messagesList.scrollTop = messagesList.scrollHeight;
}

function showWelcome() {
  currentGroup = null;
  lastMessageTime = null;
  messagesList.innerHTML = "";
  var group = document.createElement("div");
  group.className = "message-group";
  group.innerHTML =
    '<div class="message-group-time">' + formatTime(new Date()) + '</div>' +
    '<div class="message bot">' +
    '<div class="pfp">B</div>' +
    '<div class="bubble">👋 Hey! What can I help you with?</div>' +
    '</div>';
  messagesList.appendChild(group);
}

function showTyping() {
  var d = document.createElement("div");
  d.className = "message bot typing";
  d.id = "typing";
  d.innerHTML = '<div class="pfp">B</div><div class="bubble"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>';
  // Append inside current group if it exists, otherwise directly
  if (currentGroup) {
    currentGroup.appendChild(d);
  } else {
    messagesList.appendChild(d);
  }
  messagesList.scrollTop = messagesList.scrollHeight;
}

function hideTyping() {
  var t = document.getElementById("typing");
  if (t) t.remove();
}

function renderChatList(chats) {
  chatList.innerHTML = "";

  if (chats.length === 0) {
    chatList.innerHTML = '<p class="no-chats">No chats yet</p>';
    return;
  }

  chats.forEach(function(chat) {
    var item = document.createElement("div");
    item.className = "chat-item" + (chat.id === currentChatId ? " active" : "");
    item.setAttribute("data-id", chat.id);

    var title = document.createElement("span");
    title.className = "chat-item-title";
    title.textContent = chat.title || "New Chat";

    var del = document.createElement("button");
    del.className = "chat-delete-btn";
    del.textContent = "🗑";
    del.title = "Delete chat";
    del.onclick = function(e) {
      e.stopPropagation();
      deleteChat(chat.id);
    };

    item.appendChild(title);
    item.appendChild(del);
    item.addEventListener("click", function() {
      switchChat(chat.id);
    });

    chatList.appendChild(item);
  });
}

function loadChatList(callback) {
  fetch("chats.php?action=list")
    .then(function(r) { return r.json(); })
    .then(function(data) {
      renderChatList(data.chats || []);
      if (callback) callback(data.chats || []);
    })
    .catch(function() {
      if (callback) callback([]);
    });
}

function switchChat(chatId) {
  currentChatId = chatId;

  var items = chatList.querySelectorAll(".chat-item");
  items.forEach(function(item) {
    item.classList.toggle("active", parseInt(item.getAttribute("data-id")) === chatId);
  });

  currentGroup = null;
  clientHistory = [];
  messagesList.innerHTML = '<p class="loading-msgs">Loading...</p>';

  fetch("chat_history.php?chat_id=" + chatId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      messagesList.innerHTML = "";
      currentGroup = null;
      lastMessageTime = null;
      clientHistory = [];
      if (!data.messages || data.messages.length === 0) {
        showWelcome();
      } else {
        data.messages.forEach(function(msg) {
          addMessage(msg.role === "user" ? "user" : "bot", msg.content, msg.created_at || null);
          clientHistory.push({ role: msg.role === "user" ? "user" : "assistant", content: msg.content });
        });
      }
    })
    .catch(function() {
      showWelcome();
    });
}

function newChat() {
  fetch("chats.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "create", title: "New Chat" })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.success) {
      currentChatId = data.chat.id;
      loadChatList(function() {
        showWelcome();
        textInput.focus();
      });
    }
  });
}

function deleteChat(chatId) {
  fetch("chats.php", {
    method: "DELETE",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ chat_id: chatId })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.success) {
      if (currentChatId === chatId) {
        currentChatId = null;
      }
      loadChatList(function(chats) {
        if (currentChatId === null) {
          if (chats.length > 0) {
            switchChat(chats[0].id);
          } else {
            newChat();
          }
        }
      });
    }
  });
}

function getCalendarEvents() {
  return fetch("events.php?action=get")
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.success) return "";
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      var eventList = [];
      var dates = Object.keys(data.events);
      for (var i = 0; i < dates.length; i++) {
        var dateKey  = dates[i];
        var date     = new Date(dateKey);
        var daysLeft = Math.ceil((date - today) / (1000 * 60 * 60 * 24));
        if (daysLeft >= 0) {
          var eventsOnDay = data.events[dateKey];
          for (var j = 0; j < eventsOnDay.length; j++) {
            var ev = eventsOnDay[j];
            eventList.push("- " + ev.title + " (" + ev.type + ") in " + daysLeft + " days");
          }
        }
      }
      if (eventList.length === 0) return "";
      return "\n\n[BACKGROUND ONLY - do not mention unless asked] Student's upcoming events:\n" + eventList.join("\n");
    })
    .catch(function() { return ""; });
}

function setTopicButtonsDisabled(disabled) {
  var btns = document.querySelectorAll(".topic-btn[data-msg]");
  for (var i = 0; i < btns.length; i++) {
    btns[i].disabled = disabled;
    btns[i].style.opacity = disabled ? "0.5" : "1";
    btns[i].style.cursor  = disabled ? "not-allowed" : "pointer";
  }
}

function sendMessage() {
  if (!currentChatId) return;
  var text = textInput.value.trim();
  if (text === "") return;

  textInput.value = "";
  textInput.style.height = "auto";
  sendButton.disabled = true;
  setTopicButtonsDisabled(true);

  addMessage("user", text);
  showTyping();

  // Add user message to client-side history
  clientHistory.push({ role: "user", content: text });

  getCalendarEvents().then(function(calendarContext) {
    return fetch("api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: text, history: clientHistory.slice(0, -1).slice(-30), calendarContext: calendarContext, chat_id: currentChatId })
    });
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    hideTyping();
    if (data.reply) {
      addMessage("bot", data.reply);
      // Add bot reply to client-side history
      clientHistory.push({ role: "assistant", content: data.reply });
      if (clientHistory.length > 60) clientHistory = clientHistory.slice(-60);
      if (data.auto_title) {
        loadChatList();
      }
    } else if (data.error) {
      addMessage("bot", "Error: " + (data.details || data.error));
    } else {
      addMessage("bot", "Sorry, something went wrong. Please try again.");
    }
  })
  .catch(function() {
    hideTyping();
    addMessage("bot", "Could not connect to the server. Please check your internet connection.");
  })
  .finally(function() {
    setTimeout(function() {
      sendButton.disabled = false;
      setTopicButtonsDisabled(false);
      textInput.focus();
    }, 1000);
  });
}

function init() {
  loadChatList(function(chats) {
    if (chats.length > 0) {
      switchChat(chats[0].id);
    } else {
      newChat();
    }
  });
}