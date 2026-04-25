requireAuth();

const user = getUser();
document.getElementById("userName").textContent = user.name;

const API = "/TMS/nysc-task-management-system/backend/routes/api.php";

let url = user.role_id == 4
    ? API + "/tasks/my"
    : API + "/tasks/all";

async function loadTasks() {
    const res = await fetch(url, {
        headers: {
            "Content-Type": "application/json",
            "user_id": user.user_id,
            "role_id": user.role_id
        }
    });

    const data = await res.json();
    console.log(data);

    const tasks = data.tasks || [];

    document.getElementById("total").textContent = tasks.length;

    let pending = 0, progress = 0, completed = 0;

    tasks.forEach(t => {
        if (t.status === "PENDING") pending++;
        if (t.status === "IN_PROGRESS") progress++;
        if (t.status === "COMPLETED") completed++;
    });

    document.getElementById("pending").textContent = pending;
    document.getElementById("progress").textContent = progress;
    document.getElementById("completed").textContent = completed;

    const body = document.getElementById("taskBody");

    body.innerHTML = tasks.slice(0,5).map(t => `
        <tr onclick="openTask(${t.task_id})">
            <td>${t.title}</td>
            <td>${t.status}</td>
            <td>${t.deadline}</td>
        </tr>
    `).join("");
}

function openTask(id) {
    window.location.href = `task-detail.html?task_id=${id}`;
}

function goTasks() {
    window.location.href = "tasks.html";
}

function goCreate() {
    window.location.href = "create-task.html";
}

function goProfile() {
    window.location.href = "profile.html";
}

loadTasks();