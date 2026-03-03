const chatBox = document.getElementById("chat-box");
const inputField = document.getElementById("user-input");

async function sendMessage() {
  const message = inputField.value.trim();
  if (!message) return;

  addMessage(message, "user");
  inputField.value = "";

  try {
    const response = await fetch("https://my-repository-chi-beryl.vercel.app/api/chat", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ message })
    });

    const data = await response.json();
    addMessage(data.reply, "bot");

  } catch (error) {
    addMessage("Error connecting to the server.", "bot");
  }
}

function addMessage(text, sender) {
  const messageDiv = document.createElement("div");
  messageDiv.classList.add("message", sender);
  messageDiv.textContent = text;
  chatBox.appendChild(messageDiv);
  chatBox.scrollTop = chatBox.scrollHeight;
}
