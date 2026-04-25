requireAuth();

const user = getUser();

document.getElementById("userName").textContent = user.name;
document.getElementById("name").textContent = user.name;
document.getElementById("editName").value = user.name;
document.getElementById("avatarLetter").textContent = user.name.charAt(0);

document.getElementById("userId").textContent = "#" + user.user_id;

/* ROLE */
let roleText = "";
if (user.role_id == 1) roleText = "Chairman";
if (user.role_id == 2) roleText = "Director";
if (user.role_id == 3) roleText = "Deputy Director";
if (user.role_id == 4) roleText = "Assistant Director";

document.getElementById("role").textContent = roleText;
document.getElementById("roleText").value = roleText;

/* EMAIL */
document.getElementById("email").value = user.email || "N/A";

/* UPDATE */
async function updateProfile() {
    const name = document.getElementById("editName").value;

    const res = await fetch("/TMS/nysc-task-management-system/backend/routes/api.php/profile/update", {
        method: "PUT",
        headers: {
            "Content-Type": "application/json",
            "user_id": user.user_id
        },
        body: JSON.stringify({ name })
    });

    const data = await res.json();

    if (data.status === "success") {
        alert("Profile updated!");

        user.name = name;
        localStorage.setItem("user", JSON.stringify(user));
        location.reload();
    } else {
        alert("Error updating");
    }
}

/* NAV */
function goDashboard() {
    window.location.href = "dashboard.html";
}

function goTasks() {
    window.location.href = "tasks.html";
}

function goCreate() {
    window.location.href = "create-task.html";
}