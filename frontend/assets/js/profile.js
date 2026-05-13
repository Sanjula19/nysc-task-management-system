(function initProfilePage() {
    if (!requireAuth()) {
        return;
    }

    const user = getUser();
    if (!user) {
        window.location.href = "./login.html";
        return;
    }

    const roleLabel = user.role_name || getRoleDescription(user.role_id).title;
    const nameField = document.getElementById("name");
    const editNameField = document.getElementById("editName");
    const emailField = document.getElementById("email");
    const roleField = document.getElementById("role");
    const roleTextField = document.getElementById("roleText");
    const userNameField = document.getElementById("userName");
    const avatarLetterField = document.getElementById("avatarLetter");
    const userIdField = document.getElementById("userId");

    if (userNameField) userNameField.textContent = `${user.name} • ${user.email}`;
    if (nameField) nameField.textContent = user.name;
    if (editNameField) editNameField.value = user.name;
    if (avatarLetterField) avatarLetterField.textContent = user.name.charAt(0);
    if (userIdField) userIdField.textContent = `#${user.user_id}`;
    if (roleField) roleField.textContent = roleLabel;
    if (roleTextField) roleTextField.value = roleLabel;
    if (emailField) emailField.value = user.email || "N/A";

    window.updateProfile = async function updateProfile() {
        const name = editNameField ? editNameField.value.trim() : "";

        const res = await fetch("/TMS/nysc-task-management-system/backend/routes/api.php/profile/update", {
            method: "PUT",
            headers: buildAuthHeaders({
                "Content-Type": "application/json"
            }),
            body: JSON.stringify({ name })
        });

        const data = await res.json();

        if (data.status === "success") {
            const roles = getAuthorizedRoles();
            const selectedRole = getSelectedRole() || roles[0] || null;

            setAuthState({
                user: {
                    ...user,
                    name
                },
                roles,
                selectedRole,
                authenticatedAt: new Date().toISOString(),
            });

            alert("Profile updated!");
            location.reload();
        } else {
            alert(data.message || "Error updating");
        }
    };

    window.goDashboard = function goDashboard() {
        window.location.href = "./dashboard.html";
    };

    window.goTasks = function goTasks() {
        window.location.href = "./tasks.html";
    };

    window.goCreate = function goCreate() {
        window.location.href = "./create-task.html";
    };
})();
