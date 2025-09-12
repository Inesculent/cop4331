const API_BASE = "http://localhost/backend"; // adjust

// LOGIN
async function login() {
  const email = document.getElementById("login-email").value;
  const password = document.getElementById("login-password").value;

  const response = await fetch(`${API_BASE}/login.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email, password })
  });

  const data = await response.json();
  //if login successful, save token and go to dash
  if (response.ok) {
    localStorage.setItem("token", data.token);
    window.location.href = "dashboard.html";
  } else {
    //error
    document.getElementById("message").innerText = data.message || "Login failed";
  }
}

// SIGNUP 
async function signup() {
  //get signup data
  const name = document.getElementById("signup-name").value;
  const email = document.getElementById("signup-email").value;
  const password = document.getElementById("signup-password").value;

  //signup request
  const response = await fetch(`${API_BASE}/signup.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name, email, password })
  });

  const data = await response.json();

  //shows a success or error
  document.getElementById("message").innerText = data.message;
}

// LOGOUT
function logout() {
  //remove token and back to loginpage
  localStorage.removeItem("token");
  window.location.href = "index.html";
}

// ADD CONTACT
async function addContact() {
  //login token
  const token = localStorage.getItem("token");
  const name = document.getElementById("contact-name").value;
  const phone = document.getElementById("contact-phone").value;
  const email = document.getElementById("contact-email").value;

  //send request and add contact
  const response = await fetch(`${API_BASE}/contacts.php`, {
    method: "POST",
    headers: { 
      "Content-Type": "application/json",
      "Authorization": "Bearer " + token
    },
    body: JSON.stringify({ name, phone, email })
  });

  const data = await response.json();
  //show message and refresh
  document.getElementById("message").innerText = data.message;
  searchContacts();
}

//SEARCH CONTACTS
async function searchContacts() {
  //get log in token
  const token = localStorage.getItem("token");
  //get search term (if any)
  const query = document.getElementById("search-query").value;

  // Build URL with or without search (dont know about this one)
  let url = `${API_BASE}/contacts.php`;
  if (query) {
    url += `?search=${query}`;
  }

  //get contacts from server
  const response = await fetch(`${API_BASE}/contacts.php?search=${encodeURIComponent(query)}`, {
    headers: { "Authorization": "Bearer " + token }
  });

  const contacts = await response.json();

  //Display contacts in the list
  const list = document.getElementById("contact-list");
  list.innerHTML = ""; //clear old

  //add each to list
  contacts.forEach(c => {
    const li = document.createElement("li");
    li.textContent = `${c.name} - ${c.phone || ""} ${c.email || ""}`;
    list.appendChild(li);
  });
}
