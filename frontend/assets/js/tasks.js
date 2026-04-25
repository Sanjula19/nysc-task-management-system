requireAuth();

const user = getUser();
document.getElementById("userName").textContent = user.name;

const API = "/TMS/nysc-task-management-system/backend/routes/api.php";

let allTasks = [];

async function loadTasks() {
    let url = user.role_id == 4
        ? API + "/tasks/my"
        : API + "/tasks/all";

    const res = await fetch(url, {
        headers: {
            "Content-Type": "application/json",
            "user_id": user.user_id,
            "role_id": user.role_id
        }
    });

    const data = await res.json();
    allTasks = data.tasks || [];

    renderTasks(allTasks);
}

function renderTasks(tasks) {
    const body = document.getElementById("taskBody");

    body.innerHTML = tasks.map(t => {
        let statusClass = "";

        if (t.status === "PENDING") statusClass = "pending";
        if (t.status === "IN_PROGRESS") statusClass = "progress";
        if (t.status === "COMPLETED") statusClass = "completed";

        return `
        <tr onclick="openTask(${t.task_id})">
            <td>${t.title}</td>
            <td>${t.assigned_to || "-"}</td>
            <td>${t.priority}</td>
            <td>${t.deadline}</td>
            <td><span class="status ${statusClass}">${t.status}</span></td>
        </tr>
        `;
    }).join("");
}

/* FILTER */
document.getElementById("statusFilter").addEventListener("change", function () {
    const value = this.value;

    if (value === "ALL") {
        renderTasks(allTasks);
    } else {
        const filtered = allTasks.filter(t => t.status === value);
        renderTasks(filtered);
    }
});

/* SEARCH */
document.getElementById("search").addEventListener("input", function () {
    const text = this.value.toLowerCase();

    const filtered = allTasks.filter(t =>
        t.title.toLowerCase().includes(text)
    );

    renderTasks(filtered);
});

/* NAVIGATION */
function openTask(id) {
    window.location.href = `task-detail.html?task_id=${id}`;
}

function goDashboard() {
    window.location.href = "dashboard.html";
}

function goCreate() {
    window.location.href = "create-task.html";
}

function goProfile() {
    window.location.href = "profile.html";
}

loadTasks();