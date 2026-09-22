const loginSection = document.getElementById("loginSection");
const registerSection = document.getElementById("registerSection");

const showRegisterButton = document.getElementById("showRegisterButton");
const showLoginButton = document.getElementById("showLoginButton");

showRegisterButton.addEventListener("click", function () {
    loginSection.hidden = true;
    registerSection.hidden = false;
});

showLoginButton.addEventListener("click", function () {
    registerSection.hidden = true;
    loginSection.hidden = false;
});

// API endpoint
const API_URL = "http://167.99.2.103/API/auth.php";


// Register a new user
const registerForm = document.getElementById("registerForm");
const registerMessage = document.getElementById("registerMessage");

registerForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const firstName = document.getElementById("firstName").value.trim();
    const lastName = document.getElementById("lastName").value.trim();
    const login = document.getElementById("registerUsername").value.trim();
    const password = document.getElementById("registerPassword").value;

    registerMessage.textContent = "Creating account...";

    try {
        const response = await fetch(API_URL, {
            method: "POST",

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify({
                firstName: firstName,
                lastName: lastName,
                login: login,
                password: password
            })
        });

        const data = await response.json();

        if (response.ok) {
            registerMessage.textContent = "Account created successfully! You can now sign in.";

            registerForm.reset();

            setTimeout(function () {
                registerSection.hidden = true;
                loginSection.hidden = false;
                registerMessage.textContent = "";
            }, 1500);
        } else {
            registerMessage.textContent = data.error || "Registration failed.";
        }

    } catch (error) {
        console.error("Registration error:", error);
        registerMessage.textContent = "Unable to connect to the server.";
    }
});
// Login user
const loginForm = document.getElementById("loginForm");
const loginMessage = document.getElementById("loginMessage");

loginForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const login = document.getElementById("loginUsername").value.trim();
    const password = document.getElementById("loginPassword").value;

    loginMessage.textContent = "Signing in...";

    try {
        const response = await fetch(API_URL, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                login: login,
                password: password
            })
        });

        const data = await response.json();

        if (response.ok) {
            localStorage.setItem("userId", data.id);
            localStorage.setItem("token", data.token);
            localStorage.setItem("firstName", data.firstName);
            localStorage.setItem("lastName", data.lastName);
            localStorage.setItem("role", data.role);

            loginMessage.textContent = "Login successful!";

            if (data.role === "admin") {
                window.location.href = "admin.html";
            } else {
                window.location.href = "contacts.html";
            }
        } else {
            loginMessage.textContent = data.error || "Login failed.";
        }

    } catch (error) {
        console.error("Login error:", error);
        loginMessage.textContent = "Unable to connect to the server.";
    }
});