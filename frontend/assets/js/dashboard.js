(function initDashboardPage() {
    if (!requireAuth()) {
        return;
    }

    const user = getUser();
    if (!user) {
        window.location.href = "./login.html";
        return;
    }

    const userNameEl = document.getElementById("userName");
    if (userNameEl) {
        const roleLabel = user.role_name || getRoleDescription(user.role_id).title;
        userNameEl.textContent = `${user.name} • ${user.email} • ${roleLabel}`;
    }

    const API = "/TMS/nysc-task-management-system/backend/routes/api.php";
    const url = user.role_id == 4 ? `${API}/tasks/my` : `${API}/tasks/all`;

    async function loadTasks() {
        const authHeaders = buildAuthHeaders();

        const res = await fetch(url, {
            headers: authHeaders
        });

        const data = await res.json();
        console.log(data);

        const tasks = data.tasks || [];

        document.getElementById("total").textContent = tasks.length;

        let pending = 0;
        let progress = 0;
        let completed = 0;

        tasks.forEach((task) => {
            if (task.status === "PENDING") pending++;
            if (task.status === "IN_PROGRESS") progress++;
            if (task.status === "COMPLETED") completed++;
        });

        document.getElementById("pending").textContent = pending;
        document.getElementById("progress").textContent = progress;
        document.getElementById("completed").textContent = completed;

        const body = document.getElementById("taskBody");
        body.innerHTML = tasks.slice(0, 5).map((task) => `
            <tr onclick="openTask(${task.task_id})">
                <td>${task.title}</td>
                <td>${task.status}</td>
                <td>${task.deadline}</td>
            </tr>
        `).join("");
    }

    window.openTask = function openTask(id) {
        window.location.href = `task-detail.html?task_id=${id}`;
    };

    window.goTasks = function goTasks() {
        window.location.href = "./tasks.html";
    };

    window.goCreate = function goCreate() {
        window.location.href = "./create-task.html";
    };

    window.goProfile = function goProfile() {
        window.location.href = "./profile.html";
    };

    loadTasks().catch((error) => {
        console.error(error);
    });
})();
