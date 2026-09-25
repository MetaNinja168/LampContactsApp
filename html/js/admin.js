const ADMIN_API = "https://sschn6b.site/API/admins.php";

const token = localStorage.getItem("token");
const firstName = localStorage.getItem("firstName");

const welcomeMessage = document.getElementById("welcomeMessage");
const logoutButton = document.getElementById("logoutButton");

const searchButton = document.getElementById("searchButton");
const showAddButton = document.getElementById("showAddButton");

const searchInput = document.getElementById("searchInput");
const searchMessage = document.getElementById("searchMessage");
const usersList = document.getElementById("usersList");

const adminPanel = document.getElementById("adminPanel");
const searchResults = document.getElementById("searchResults");
const addAdminSection = document.getElementById("addAdminSection");
const banSection = document.getElementById("banSection");
const changePassSection = document.getElementById("changePassSection");

showAddButton.addEventListener("click", function() {
    adminPanel.hidden = true;
    searchResults.hidden = true;
    addAdminSection.hidden = false;
});

// Ensure user is logged in. Otherwise, return to login screen
if (!token) {
    window.location.href = "index.html";
}

// Show user's name
welcomeMessage = `Welcome, ${firstName}!`;

// Ban User
async function banUser() {

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
});

// Search User
searchButton.addEventListener("click", searchUsers);

searchInput.addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
        searchUsers();
    }
});

async function searchUsers() {
    const searchTerm = searchInput.value.trim();

    if (!searchTerm) {
        searchMessage.textContent = "Please enter the username of the user you wish to view."
        usersList.innerHTML = "";
        return;
    }

    searchMessage.textContent = "Searching...";
    contactsList.innerHTML = "";

    try {
        const response = await fetch(
            `${ADMIN_API}?q=${encodeURIComponent(searchTerm)}`,
            {
                method: "POST",
                header: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${token}`
                },
                body: JSON.stringify({
                    firstName: firstName,
                    lastName: lastName,
                    login: login
                })
            }
        );

        const data = await response.json();

        if (!response.ok) {
            searchMessage.textContent = data.error || "Search failed.";
            return;
        }

        //if (!data.users)

    } catch (error) {
        console.error("Search error:", error);
        searchMessage.textContent = "Unable to connect to the server.";
    }
}