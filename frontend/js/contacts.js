const CONTACTS_API = "http://167.99.2.103/index.php";

const token = localStorage.getItem("token");
const firstName = localStorage.getItem("firstName");

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
welcomeMessage.textContent = `Welcome, ${firstName}!`;

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
    localStorage.clear();
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
        searchMessage.textContent = "Enter a name, email, or phone number to search.";
        contactsList.innerHTML = "";
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

        searchMessage.textContent = "";

        data.contacts.forEach(function (contact) {
            const contactCard = document.createElement("div");
            contactCard.className = "contact-card";

            const name = document.createElement("h3");
            name.textContent = `${contact.first_name} ${contact.last_name}`;

            const email = document.createElement("p");
            email.textContent = `Email: ${contact.email}`;

            const phone = document.createElement("p");
            phone.textContent = `Phone: ${contact.phone_number}`;

            const editButton = document.createElement("button");
            editButton.textContent = "Edit";
            editButton.addEventListener("click", function () {
                editContact(contact);
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

            contactsList.appendChild(contactCard);
        });

    } catch (error) {
        console.error("Search error:", error);
        searchMessage.textContent = "Unable to connect to the server.";
    }
}

// Edit contact
async function editContact(contact) {
    const firstName = prompt("First name:", contact.first_name);
    if (firstName === null) return;

    const lastName = prompt("Last name:", contact.last_name);
    if (lastName === null) return;

    const email = prompt("Email:", contact.email);
    if (email === null) return;

    const phoneNumber = prompt("Phone number:", contact.phone_number);
    if (phoneNumber === null) return;

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
            searchMessage.textContent = "Contact updated successfully!";
            await searchContacts();
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
            searchMessage.textContent = "Contact deleted successfully!";
            await searchContacts();
        } else {
            searchMessage.textContent = data.error || "Unable to delete contact.";
        }
    } catch (error) {
        console.error("Delete error:", error);
        searchMessage.textContent = "Unable to connect to the server.";
    }
}