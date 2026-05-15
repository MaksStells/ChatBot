
var currentDate  = new Date(); 
var clickedDate  = null;       
var allEvents    = {};        

var today = new Date();
today.setHours(0, 0, 0, 0);

function checkIfLoggedIn() {
  fetch("auth.php?action=check")
    .then(function(response) {
      return response.json();
    })
    .then(function(data) {
      if (data.loggedIn === false) {
        window.location.href = "login.html";
      } else {
        document.getElementById("username-display").textContent = data.username;
        loadEvents(); // Load calendar events after confirming login
      }
    });
}

// Load events from the database
function loadEvents() {
  fetch("events.php?action=get")
    .then(function(response) {
      return response.json();
    })
    .then(function(data) {
      if (data.success) {
        allEvents = data.events;
        drawCalendar();
      }
    });
}

function drawCalendar() {
  var year  = currentDate.getFullYear();
  var month = currentDate.getMonth();

  document.getElementById("monthTitle").textContent =
    currentDate.toLocaleString("default", { month: "long", year: "numeric" });

  var container = document.getElementById("calendarDays");
  container.innerHTML = ""; // clear old days

  var firstDay     = new Date(year, month, 1).getDay();
  var totalDays    = new Date(year, month + 1, 0).getDate();
  var emptySpaces  = (firstDay + 6) % 7; // shift so Monday is first

  // Add empty boxes before day 1
  for (var i = 0; i < emptySpaces; i++) {
    var emptyBox = document.createElement("div");
    emptyBox.className = "day-cell empty";
    container.appendChild(emptyBox);
  }

  // Add a box for each day
  for (var d = 1; d <= totalDays; d++) {
    var month2    = String(month + 1).padStart(2, "0");
    var day2      = String(d).padStart(2, "0");
    var dateKey   = year + "-" + month2 + "-" + day2;
    var cellDate  = new Date(year, month, d);
    var daysLeft  = Math.ceil((cellDate - today) / (1000 * 60 * 60 * 24));

    var box = document.createElement("div");
    box.className = "day-cell";

    // Highlight today
    if (cellDate.getTime() === today.getTime()) {
      box.className += " today";
    }

    var eventsOnDay  = allEvents[dateKey] || [];
    var hasDeadline  = false;

    for (var j = 0; j < eventsOnDay.length; j++) {
      if (eventsOnDay[j].type === "deadline" || eventsOnDay[j].type === "exam") {
        hasDeadline = true;
      }
    }

    if (hasDeadline) box.className += " has-deadline";
    if (hasDeadline && daysLeft >= 0 && daysLeft <= 3) box.className += " warning";

    // Day number
    var dayNumber = document.createElement("div");
    dayNumber.className   = "day-number";
    dayNumber.textContent = d;
    box.appendChild(dayNumber);

    // Show up to 3 events as coloured pills
    var showCount = Math.min(eventsOnDay.length, 3);
    for (var k = 0; k < showCount; k++) {
      var pill = document.createElement("span");
      pill.className   = "event-pill " + eventsOnDay[k].type;
      pill.textContent = eventsOnDay[k].title;
      box.appendChild(pill);
    }

    // Show how many more events are hidden
    if (eventsOnDay.length > 3) {
      var morePill = document.createElement("span");
      morePill.className   = "event-pill other";
      morePill.textContent = "+" + (eventsOnDay.length - 3) + " more";
      box.appendChild(morePill);
    }

    // Open popup when a day is clicked
    box.addEventListener("click", function(date, day, m, y) {
      return function() { openModal(date, day, m, y); };
    }(dateKey, d, month, year));

    container.appendChild(box);
  }

  showUpcoming();
}

function showUpcoming() {
  var list     = document.getElementById("upcoming-list");
  list.innerHTML = "";

  var upcoming = [];

  var dates = Object.keys(allEvents);
  for (var i = 0; i < dates.length; i++) {
    var dateKey  = dates[i];
    var date     = new Date(dateKey);
    var daysLeft = Math.ceil((date - today) / (1000 * 60 * 60 * 24));

    if (daysLeft >= 0 && daysLeft <= 14) {
      var eventsOnDay = allEvents[dateKey];
      for (var j = 0; j < eventsOnDay.length; j++) {
        upcoming.push({
          title:    eventsOnDay[j].title,
          date:     date,
          daysLeft: daysLeft
        });
      }
    }
  }

  // Sort by soonest first
  upcoming.sort(function(a, b) { return a.daysLeft - b.daysLeft; });

  if (upcoming.length === 0) {
    list.innerHTML = '<p class="no-events">No events in the next 14 days</p>';
    return;
  }

  for (var i = 0; i < upcoming.length; i++) {
    var ev   = upcoming[i];
    var item = document.createElement("div");
    item.className = "upcoming-item";
    if (ev.daysLeft <= 3) item.className += " warning";

    var label = "";
    if (ev.daysLeft === 0) label = "Today";
    else if (ev.daysLeft === 1) label = "Tomorrow";
    else label = "In " + ev.daysLeft + " days";

    var dateStr = ev.date.toLocaleDateString("en-GB", { day: "numeric", month: "short" });

    item.innerHTML =
      '<div class="evt-title">' + ev.title + '</div>' +
      '<div class="evt-date' + (ev.daysLeft <= 3 ? " urgent" : "") + '">' + label + " - " + dateStr + '</div>';

    list.appendChild(item);
  }
}

// Open the popup for a specific day
function openModal(dateKey, day, month, year) {
  clickedDate = dateKey;

  var monthName = new Date(year, month).toLocaleString("default", { month: "long" });
  document.getElementById("modal-date-title").textContent = day + " " + monthName + " " + year;

  showModalEvents(allEvents[dateKey] || []);
  document.getElementById("eventTitle").value = "";
  document.getElementById("modal").classList.remove("hidden");
  document.getElementById("eventTitle").focus();
}

// Show the events list inside the popup
function showModalEvents(events) {
  var list = document.getElementById("modal-events-list");
  list.innerHTML = "";

  for (var i = 0; i < events.length; i++) {
    var ev   = events[i];
    var item = document.createElement("div");
    item.className = "modal-event-item";
    item.innerHTML =
      '<span class="event-pill ' + ev.type + '" style="margin:0">' + ev.title + '</span>' +
      '<button onclick="deleteEvent(' + ev.id + ')">Remove</button>';
    list.appendChild(item);
  }
}

// Delete an event
function deleteEvent(id) {
  fetch("events.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "delete", id: id })
  })
  .then(function() {
    loadEvents();
    // Wait a bit then refresh the modal
    setTimeout(function() {
      showModalEvents(allEvents[clickedDate] || []);
    }, 300);
  });
}

document.getElementById("addEventBtn").addEventListener("click", function() {
  var title = document.getElementById("eventTitle").value.trim();
  var type  = document.getElementById("eventType").value;

  if (title === "") return;

  fetch("events.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "add", date: clickedDate, title: title, type: type })
  })
  .then(function() {
    document.getElementById("eventTitle").value = "";
    loadEvents();
    setTimeout(function() {
      showModalEvents(allEvents[clickedDate] || []);
    }, 300);
  });
});

document.getElementById("closeModal").addEventListener("click", function() {
  document.getElementById("modal").classList.add("hidden");
});

document.getElementById("eventTitle").addEventListener("keydown", function(e) {
  if (e.key === "Enter") {
    document.getElementById("addEventBtn").click();
  }
});

document.getElementById("modal").addEventListener("click", function(e) {
  if (e.target === document.getElementById("modal")) {
    document.getElementById("modal").classList.add("hidden");
  }
});

document.getElementById("prevMonth").addEventListener("click", function() {
  currentDate.setMonth(currentDate.getMonth() - 1);
  drawCalendar();
});

document.getElementById("nextMonth").addEventListener("click", function() {
  currentDate.setMonth(currentDate.getMonth() + 1);
  drawCalendar();
});

document.getElementById("logoutBtn").addEventListener("click", function() {
  fetch("auth.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "logout" })
  })
  .then(function() {
    window.location.href = "login.html";
  });
});

checkIfLoggedIn();