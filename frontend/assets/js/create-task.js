requireAuth();

const user = getUser();
const userNameEl = document.getElementById("userName");

if (userNameEl && user) {
    userNameEl.textContent = user.name;
}

const API = "/TMS/nysc-task-management-system/backend/routes/api.php";

async function loadUsers() {
    const container = document.getElementById("usersList");

    if (!container) {
        return;
    }

    try {
        const response = await fetch(`${API}/users/role/4`, {
            method: "GET",
            headers: buildAuthHeaders()
        });

        const data = await response.json();
        const users = Array.isArray(data.users) ? data.users : [];

        if (!response.ok || data.status !== "success" || users.length === 0) {
            container.innerHTML = `<p class="empty-state">${data.message || "No Assistant Directors available."}</p>`;
            return;
        }

        container.innerHTML = users.map((u) => `
            <label class="user-item">
                <input type="checkbox" value="${u.user_id}">
                ${u.name}
            </label>
        `).join("");
    } catch (error) {
        console.error("Failed to load Assistant Directors:", error);
        container.innerHTML = `<p class="empty-state">Unable to load Assistant Directors.</p>`;
    }
}

document.getElementById("taskForm").addEventListener("submit", async function (e) {
    e.preventDefault();

    const title = document.getElementById("title").value.trim();
    const description = document.getElementById("description").value.trim();
    const priority = document.getElementById("priority").value;
    const deadline = document.getElementById("deadline").value;

    const checked = [...document.querySelectorAll("#usersList input:checked")]
        .map((el) => parseInt(el.value, 10))
        .filter((value) => Number.isInteger(value) && value > 0);

    if (checked.length === 0) {
        alert("Select at least one Assistant Director.");
        return;
    }

    const createResponse = await fetch(`${API}/tasks`, {
        method: "POST",
        headers: buildAuthHeaders({
            "Content-Type": "application/json"
        }),
        body: JSON.stringify({
            title,
            description,
            priority,
            deadline
        })
    });

    const createData = await createResponse.json();

    if (!createResponse.ok || createData.status !== "success") {
        alert(createData.message || "Error creating task");
        return;
    }

    const taskId = createData.task_id;

    const assignResponse = await fetch(`${API}/tasks/assign`, {
        method: "POST",
        headers: buildAuthHeaders({
            "Content-Type": "application/json"
        }),
        body: JSON.stringify({
            task_id: taskId,
            user_ids: checked
        })
    });

    const assignData = await assignResponse.json();

    if (!assignResponse.ok || assignData.status !== "success") {
        alert(assignData.message || "Task created, but assignment failed.");
        return;
    }

    alert("Task created and assigned!");
    window.location.href = "tasks.html";
});

function goDashboard() {
    window.location.href = "dashboard.html";
}

function goTasks() {
    window.location.href = "tasks.html";
}

function goProfile() {
    window.location.href = "profile.html";
}

loadUsers();
