requireAuth();

const user = getUser();
document.getElementById("userName").textContent = user.name;

const API = "/TMS/nysc-task-management-system/backend/routes/api.php";

async function loadUsers() {
    // 🔥 since backend not changing → fake UI or fetch from users table if exists

    const users = [
        { id: 4, name: "Kumari Sarah" },
        { id: 5, name: "John Bello" },
        { id: 6, name: "Fatima Adamu" }
    ];

    const container = document.getElementById("usersList");

    container.innerHTML = users.map(u => `
        <label class="user-item">
            <input type="checkbox" value="${u.id}">
            ${u.name}
        </label>
    `).join("");
}

document.getElementById("taskForm").addEventListener("submit", async function(e) {
    e.preventDefault();

    const title = document.getElementById("title").value;
    const description = document.getElementById("description").value;
    const priority = document.getElementById("priority").value;
    const deadline = document.getElementById("deadline").value;

    const checked = [...document.querySelectorAll("#usersList input:checked")]
        .map(el => parseInt(el.value));

    // CREATE TASK
    const res = await fetch(API + "/tasks", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "user_id": user.user_id,
            "role_id": user.role_id
        },
        body: JSON.stringify({
            title, description, priority, deadline
        })
    });

    const data = await res.json();

    if (data.status !== "success") {
        alert("Error creating task");
        return;
    }

    const taskId = data.task_id;

    // ASSIGN USERS
    await fetch(API + "/tasks/assign", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "user_id": user.user_id,
            "role_id": user.role_id
        },
        body: JSON.stringify({
            task_id: taskId,
            user_ids: checked
        })
    });

    alert("Task created and assigned!");
    window.location.href = "tasks.html";
});

/* NAV */
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