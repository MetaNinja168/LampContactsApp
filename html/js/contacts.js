const CONTACTS_API = "https://lamp.sschn6b.site/index.php"; 

const token = localStorage.getItem("token");
const firstName = localStorage.getItem("firstName");
const lastName = localStorage.getItem("lastName");

const welcomeMessage = document.getElementById("welcomeMessage");
const logoutButton = document.getElementById("logoutButton");

const showAddContactButton = document.getElementById("showAddContactButton");
const addContactSection = document.getElementById("addContactSection");
const cancelAddButton = document.getElementById("cancelAddButton");
const addContactForm = document.getElementById("addContactForm");
const contactMessage = document.getElementById("contactMessage");

const searchInput = document.getElementById("searchInput");
const searchButton = document.getElementById("searchButton");
const searchMessage = document.getElementById("searchMessage");
const contactsList = document.getElementById("contactsList");

// Make sure user is logged in
if (!token) {
    window.location.href = "index.html";
}

// Show user's name
welcomeMessage.textContent = `Welcome, ${firstName} ${lastName}!`;

// Show Add Contact form
showAddContactButton.addEventListener("click", function () {
    addContactSection.hidden = false;
});

// Hide Add Contact form
cancelAddButton.addEventListener("click", function () {
    addContactSection.hidden = true;
    addContactForm.reset();
    contactMessage.textContent = "";
});

// Log out
logoutButton.addEventListener("click", function () {
    const saveTheme = localStorage.getItem("theme.sschn6b");
    localStorage.clear();
    localStorage.setItem("theme.sschn6b", saveTheme);
    window.location.href = "index.html";
});

// Add a contact
addContactForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const firstName = document.getElementById("contactFirstName").value.trim();
    const lastName = document.getElementById("contactLastName").value.trim();
    const email = document.getElementById("contactEmail").value.trim();
    const phoneNumber = document.getElementById("contactPhone").value.trim();

    contactMessage.textContent = "Adding contact...";

    try {
        const response = await fetch(CONTACTS_API, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": `Bearer ${token}`
            },
            body: JSON.stringify({
                firstName: firstName,
                lastName: lastName,
                email: email,
                phoneNumber: phoneNumber
            })
        });

        const data = await response.json();

        if (response.ok) {
            contactMessage.textContent = "Contact added successfully!";
            addContactForm.reset();
            addContactSection.hidden = true;
            await displayAll();
        } else {
            contactMessage.textContent = data.error || "Unable to add contact.";
        }
    } catch (error) {
        console.error("Add contact error:", error);
        contactMessage.textContent = "Unable to connect to the server.";
    }
});

// Search contacts
searchButton.addEventListener("click", searchContacts);

searchInput.addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
        searchContacts();
    }
});

async function searchContacts() {
    const searchTerm = searchInput.value.trim();

    if (!searchTerm) {
        displayAll();
        return;
    }

    searchMessage.textContent = "Searching...";
    contactsList.innerHTML = "";

    try {
        const response = await fetch(
            `${CONTACTS_API}?q=${encodeURIComponent(searchTerm)}`,
            {
                method: "GET",
                headers: {
                    "Authorization": `Bearer ${token}`
                }
            }
        );

        const data = await response.json();

        if (!response.ok) {
            searchMessage.textContent = data.error || "Search failed.";
            return;
        }

        if (!data.contacts || data.contacts.length === 0) {
            searchMessage.textContent = "No contacts found.";
            return;
        }

        searchMessage.textContent = `Displaying results for \"${searchTerm}\"`;

        data.contacts.forEach(function (contact) {
            displayContact(contact);
        });

    } catch (error) {
        console.error("Search error:", error);
        searchMessage.textContent = "Unable to connect to the server.";
    }
}

// Display all contacts
async function displayAll() {

    contactsList.innerHTML = "";

    try {
        const response = await fetch(
            `${CONTACTS_API}`,
            {
                method: "GET",
                headers: {
                    "Authorization": `Bearer ${token}`
                }
            }
        );

        const data = await response.json();

        if (!response.ok) {
            searchMessage.textContent = "Unable to display all contacts";
            return;
        }

        if (!data.contacts || data.contacts.length === 0) {
            searchMessage.textContent = "You don't have any contacts so far.";
            return;
        }

        searchMessage.textContent = "Displaying all contacts. Enter a name, email, or phone number to search for specific contacts.";

        data.contacts.forEach(function (contact) {
            displayContact(contact);
        });

    } catch (error) {
        console.error("Search error:", error);
        searchMessage.textContent = "Unable to connect to the server.";
    }
}

// Display Contact
async function displayContact(contact) {
    const contactCard = document.createElement("div");
    contactCard.className = "contact-card";

    const name = document.createElement("h3");
    name.textContent = `${contact.first_name} ${contact.last_name}`;

    const email = document.createElement("p");
    email.textContent = `Email: ${contact.email}`;

    const phone = document.createElement("p");
    phone.textContent = `Phone: ${contact.phone_number}`;

    const editForm = document.createElement("form");
    editForm.id = "editForm";
    editForm.hidden = true;
    createEditForm(editForm, contact);

    const editButton = document.createElement("button");
    editButton.textContent = "Edit";
    editButton.addEventListener("click", function () {
        editForm.hidden = false;
    });

    const deleteButton = document.createElement("button");
    deleteButton.textContent = "Delete";
    deleteButton.addEventListener("click", function () {
        deleteContact(contact.id);
    });

    contactCard.appendChild(name);
    contactCard.appendChild(email);
    contactCard.appendChild(phone);
    contactCard.appendChild(editButton);
    contactCard.appendChild(deleteButton);
    contactCard.appendChild(editForm);

    contactsList.appendChild(contactCard);
}

// Create the Edit Form
async function createEditForm(form, contact) {

    const formHeader = document.createElement("h2");
    formHeader.textContent = "Edit Contact";

    const labelFN = document.createElement("label");
    labelFN.for = "editFN";
    labelFN.textContent = "First Name";

    const editFN = document.createElement("input");
    editFN.type = "text";
    editFN.id = "editFN";
    editFN.value = `${contact.first_name}`;
    editFN.required = true;

    const labelLN = document.createElement("label");
    labelLN.for = `editLN${contact.id}`;
    labelLN.textContent = "Last Name";

    const editLN = document.createElement("input");
    editLN.type = "text";
    editLN.id = "editLN";
    editLN.value = `${contact.last_name}`;
    editLN.required = true;

    const labelMail = document.createElement("label");
    labelMail.for = "editMail";
    labelMail.textContent = "Email";

    const editMail = document.createElement("input");
    editMail.type = "text";
    editMail.id = "editMail";
    editMail.value = `${contact.email}`;
    editMail.required = true;

    const labelPhone = document.createElement("label");
    labelPhone.for = "editPhone";
    labelPhone.textContent = "Phone Number";

    const editPhone = document.createElement("input");
    editPhone.type = "text";
    editPhone.id = "editPhone";
    editPhone.value = `${contact.phone_number}`;
    editPhone.required = true;

    const acceptEdit = document.createElement("button");
    acceptEdit.type = "button";
    acceptEdit.id = "acceptEdit";
    acceptEdit.textContent = "Submit Edits";

    const cancelEdit = document.createElement("button");
    cancelEdit.type = "button";
    cancelEdit.id = "cancelEdit";
    cancelEdit.textContent = "Cancel Edit";

    form.appendChild(formHeader);
    form.appendChild(labelFN);
    form.appendChild(editFN);
    form.appendChild(labelLN);
    form.appendChild(editLN);
    form.appendChild(labelMail);
    form.appendChild(editMail);
    form.appendChild(labelPhone);
    form.appendChild(editPhone);
    form.appendChild(acceptEdit);
    form.appendChild(cancelEdit);

    acceptEdit.addEventListener("click", function() {
        form.hidden = true;
        editContact(contact, editFN.value, editLN.value, editMail.value, editPhone.value);
    });

    cancelEdit.addEventListener("click", function() {
        form.hidden = true;
    });
}

// Edit contact
async function editContact(contact, firstName, lastName, email, phoneNumber) {

    try {
        const response = await fetch(`${CONTACTS_API}?id=${contact.id}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                "Authorization": `Bearer ${token}`
            },
            body: JSON.stringify({
                firstName: firstName.trim(),
                lastName: lastName.trim(),
                email: email.trim(),
                phoneNumber: phoneNumber.trim()
            })
        });

        const data = await response.json();

        if (response.ok) {
            await displayAll();
            searchMessage.textContent = "Contact updated successfully!";
        } else {
            searchMessage.textContent = data.error || "Unable to update contact.";
        }
    } catch (error) {
        console.error("Edit error:", error);
        searchMessage.textContent = "Unable to connect to the server.";
    }
}

// Delete contact
async function deleteContact(contactId) {
    const confirmed = confirm("Are you sure you want to delete this contact?");

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch(`${CONTACTS_API}?id=${contactId}`, {
            method: "DELETE",
            headers: {
                "Authorization": `Bearer ${token}`
            }
        });

        const data = await response.json();

        if (response.ok) {
            await displayAll();
            searchMessage.textContent = "Contact deleted successfully!";
        } else {
            searchMessage.textContent = data.error || "Unable to delete contact.";
        }
    } catch (error) {
        console.error("Delete error:", error);
        searchMessage.textContent = "Unable to connect to the server.";
    }
}

displayAll();
// Light / Dark Mode

const themeToggle = document.getElementById("themeToggle");

// Keep the user's selected theme after refresh
if (localStorage.getItem("theme.sschn6b") === "dark") {
    document.body.classList.add("dark-mode");
    themeToggle.textContent = "☀️ Light Mode";
}

themeToggle.addEventListener("click", function () {
    document.body.classList.toggle("dark-mode");

    if (document.body.classList.contains("dark-mode")) {
        localStorage.setItem("theme.sschn6b", "dark");
        themeToggle.textContent = "☀️ Light Mode";
    } else {
        localStorage.setItem("theme.sschn6b", "light");
        themeToggle.textContent = "🌙 Dark Mode";
    }
});