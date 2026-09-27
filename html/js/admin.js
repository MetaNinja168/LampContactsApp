const ADMIN_API = "https://lamp.sschn6b.site/API/admins.php";

const token = localStorage.getItem("token");
const firstName = localStorage.getItem("firstName");

const welcomeMessage = document.getElementById("welcomeMessage");
const logoutButton = document.getElementById("logoutButton");

const searchButton = document.getElementById("searchButton");
const showAddButton = document.getElementById("showAddButton");
const cancelAddButton = document.getElementById("cancelAddAdmin");

const searchInput = document.getElementById("searchInput");
const searchMessage = document.getElementById("searchMessage");
const usersList = document.getElementById("usersList");

const adminPanel = document.getElementById("adminPanel");
const addAdminSection = document.getElementById("addAdminSection");
const addAdminMessage = document.getElementById("addMessage");

showAddButton.addEventListener("click", function() {
    adminPanel.hidden = true;
    searchResults.hidden = true;
    addAdminSection.hidden = false;
});

cancelAddButton.addEventListener("click", function() {
    adminPanel.hidden = false;
    searchResults.hidden = false;
    addAdminSection.hidden = true;
});

// Ensure user is logged in. Otherwise, return to login screen
if (!token) {
    window.location.href = "index.html";
}

// Logout
logoutButton.addEventListener("click", function () {
    localStorage.clear();
    window.location.href = "index.html";
});

// Show user's name
welcomeMessage.textContent = `Welcome, ${firstName}!`;

// Ban User
async function banUser(user, actionMessage, button) {
    const confirmed = confirm(`Are you sure you want to ${user.enabled ? "disable" : "enable"} ${user.login}`);

    if (!confirmed) return;

    try {
        const response = await fetch(`${ADMIN_API}?action=setStatus`, {
            method: "POST",
            headers: {
                "Authorization": `Bearer ${token}`,
            },
            action: "setStatus",
            body: JSON.stringify({
                userId: user.id,
                isActive: user.enabled
            })
        });

        if (response.ok) {
            actionMessage.textContent = `Successfully ${user.enabled ? "disabled" : "enabled"} ${user.login}.`;
            user.enabled = !user.enabled;
            button.textContent = `${user.enabled ? "Disable":"Enable"}`;
        } else {
            actionMessage.textContent = `Could not ${user.enabled ? "disable" : "enable"} this account.`
        }

    } catch (error) {
        console.error("Disable/enable error:", error);
        actionMessage.textContent = "Unable to connect to the server."
    }
}

// Change Password
async function changePass(user, actionMessage) {
    const newPass = prompt("Enter the new password:");
    if (newPass === null) return;

    try {
        const response = await fetch(`${ADMIN_API}?action=changePassword`, {
            method: "POST",
            headers: {
                "Content-type": "application/json",
                "Authorization": `Bearer ${token}`
            },
            action: "changePassword",
            body: JSON.stringify({
                userId: user.id,
                newPassword: newPass
            })
        });

        if (response.ok) {
            actionMessage.textContent = await response.json().message;
        }
    } catch (error) {
        console.error("Change password error:", error);
        actionMessage.textContent = "Unable to connect to the server."
    }
}

// Add Admin
const addAdminForm = document.getElementById("addAdminForm");
const addMessage = document.getElementById("addMessage")

addAdminForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const firstName = document.getElementById("firstName").value.trim();
    const lastName = document.getElementById("lastName").value.trim();
    const login = document.getElementById("registerUsername").value.trim();
    const password = document.getElementById("registerPassword").value;

    try {
        const response = await fetch(`${ADMIN_API}?action=addAdmin`, {
            method: "POST",
            header: {
                "Content-Type": "application/json",
                "Authorization": `Bearer ${token}`
            },
            action: "addAdmin",
            body: JSON.stringify({
                firstName: firstName,
                lastName: lastName,
                login: login,
                password: password
            })
        });

        if (response.ok) {
            addAdminMessage.textContent("Successfully created admin");

        }
    } catch (error) {
        console.error("Add admin error:", error);
    }
});



// Search User
searchButton.addEventListener("click", searchUsers());

searchInput.addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
        searchUsers();
    }
});

async function searchUsers() {
    const searchTerm = searchInput.value.trim();

    if (!searchTerm) {
        displayAll();
        return;
    }

    searchMessage.textContent = "Searching...";
    usersList.innerHTML = "";

    try {
        const response = await fetch(
            `${ADMIN_API}?action=searchUsers`,
            {
                method: "POST",
                header: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${token}`
                },
                action: "searchUsers",
                body: JSON.stringify({
                    search: searchTerm
                })
            }
        );

        const data = await response.json();

        if (!response.ok) {
            searchMessage.textContent = data.error || "Search failed.";
            return;
        }

        if (data.users || data.users.length === 0) {
            searchMessage.textContent = "No users found..."
            return;
        }

        data.users.forEach(function (user) {
            if (user.id !== (int)(token)) {

                const userCard = document.createElement("div");
                userCard.className = "user-card";

                const name = document.createElement("h3");
                name.textContent = `${user.firstName} ${user.lastName}`;

                const login = document.createElement("h6");
                login.textContent = `${user.login}`;

                const id = document.createElement("p");
                id.textContent = `User ID: ${user.id}`;

                const actionMessage = document.createElement("p");
                actionMessage.textContent = "";

                const banButton = document.createElement("button");
                banButton.textContent = `${user.enabled ? "Disable" : "Enable"}`;
                banButton.addEventListener("click", function() {
                    banUser(user, actionMessage, banButton);
                });

                const changeButton = document.createElement("button");
                changeButton.textContent = "Change Password";
                changeButton.addEventListener("click", function() {
                    changePass(user, actionMessage);
                });

                const searchConIn = document.createElement("input");
                searchConIn.type = "text";
                searchConIn.id = "searchContact";
                searchConIn.placeholder = "Search Contacts...";

                const searchCon = document.createElement("button");
                searchCon.textContent = "Search Contacts";
                searchCon.addEventListener("click", searchUserContacts(user));


                userCard.appendChild(name);
                userCard.appendChild(login);
                userCard.appendChild(id);
                userCard.appendChild(actionMessage);
                userCard.appendChild(banButton);
                userCard.appendChild(changeButton);
                userCard.appendChild(searchConIn);
                userCard.appendChild(searchCon);

                usersList.appendChild(userCard);
            }
        });

    } catch (error) {
        console.error("Search error:", error);
        searchMessage.textContent = "Unable to connect to the server.";
    }
}

// Display All Users
async function displayAll() {
    usersList.innerHTML = "";

    try {
        const response = await fetch(
            `${ADMIN_API}?action=showAll`,
            {
                method: "GET",
                header: {
                    "Authorization": `Bearer ${token}`
                },
                action: "showAll"
            }
        );

        const data = await response.json();

        if (!response.ok) {
            searchMessage.textContent = data.error || "Search failed.";
            return;
        }

        if (data.users || data.users.length === 0) {
            searchMessage.textContent = "No users found..."
            return;
        }

        data.users.forEach(function (user) {
            if (user.id !== (int)(token)) {

                const userCard = document.createElement("div");
                userCard.className = "user-card";

                const name = document.createElement("h3");
                name.textContent = `${user.firstName} ${user.lastName}`;

                const login = document.createElement("h6");
                login.textContent = `${user.login}`;

                const id = document.createElement("p");
                id.textContent = `User ID: ${user.id}`;

                const actionMessage = document.createElement("p");
                actionMessage.textContent = "";

                const banButton = document.createElement("button");
                banButton.textContent = `${user.enabled ? "Disable" : "Enable"}`;
                banButton.addEventListener("click", function() {
                    banUser(user, actionMessage, banButton);
                });

                const changeButton = document.createElement("button");
                changeButton.textContent = "Change Password";
                changeButton.addEventListener("click", function() {
                    changePass(user, actionMessage);
                });

                const searchConIn = document.createElement("input");
                searchConIn.type = "text";
                searchConIn.id = "searchContact";
                searchConIn.placeholder = "Search Contacts...";

                const searchCon = document.createElement("button");
                searchCon.textContent = "Search Contacts";
                searchCon.addEventListener("click", searchUserContacts(user));


                userCard.appendChild(name);
                userCard.appendChild(login);
                userCard.appendChild(id);
                userCard.appendChild(actionMessage);
                userCard.appendChild(banButton);
                userCard.appendChild(changeButton);
                userCard.appendChild(searchConIn);
                userCard.appendChild(searchCon);

                usersList.appendChild(userCard);
            }
        });

    } catch (error) {
        console.error("Search error:", error);
        searchMessage.textContent = "Unable to connect to the server.";
    }
}

// Search User Contact
async function searchUserContacts(user, userCard) {

}

displayAll();