const ADMIN_API = "https://sschn6b.site/index.php";

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
// if (!token) {
//     window.location.href = "index.html";
// }

// Show user's name
welcomeMessage = `Welcome, ${firstName}!`;

// Ban User
const banUserForm = document.getElementById("banUserForm");
const banMessage = document.getElementById("banMessage");

banUserForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const targetUser = document.getElementById("targetUser").value.trim();

    banMessage = "Disabling Account..."
    try {

    } catch (error) {
        console.error("Disabling error:", error);
        banMessage = "Unable to disable account.";
    }
});



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