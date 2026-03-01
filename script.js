async function sendMessage() {
  const input = document.getElementById("user-input");
  const chatBox = document.getElementById("chat-box");

  const userText = input.value;
  if (!userText) return;

  chatBox.innerHTML += `<div class="message user">You: ${userText}</div>`;
  input.value = "";

  const res = await fetch("/.netlify/functions/chat", {
    method: "POST",
    body: JSON.stringify({ message: userText })
  });

  const data = await res.json();
  chatBox.innerHTML += `<div class="message bot">Bot: ${data.reply}</div>`;
  chatBox.scrollTop = chatBox.scrollHeight;
}