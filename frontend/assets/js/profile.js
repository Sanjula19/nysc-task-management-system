(function initProfilePage() {
    if (!requireAuth()) {
        return;
    }

    const API = "/TMS/nysc-task-management-system/backend/routes/api.php";
    const nameField = document.getElementById("name");
    const editNameField = document.getElementById("editName");
    const districtField = document.getElementById("district");
    const emailField = document.getElementById("email");
    const roleField = document.getElementById("role");
    const roleTextField = document.getElementById("roleText");
    const userNameField = document.getElementById("userName");
    const avatarLetterField = document.getElementById("avatarLetter");
    const userIdField = document.getElementById("userId");
    const profilePhotoInput = document.getElementById("profilePhoto");

    async function loadProfile() {
        const fallbackUser = getUser();

        try {
            const response = await fetch(`${API}/profile/me`, {
                method: "GET",
                headers: buildAuthHeaders()
            });

            const data = await response.json();

            if (!response.ok || data.status !== "success") {
                applyProfile(fallbackUser, null);
                return;
            }

            applyProfile(data.user || fallbackUser, data.profile || null);

            if (data.user) {
                setAuthState({
                    user: {
                        ...data.user,
                        district: data.profile && data.profile.district ? data.profile.district : "",
                        profile_photo: data.profile && data.profile.profile_photo ? data.profile.profile_photo : ""
                    },
                    roles: Array.isArray(data.roles) ? data.roles : getAuthorizedRoles(),
                    selectedRole: data.selectedRole || getSelectedRole() || null,
                    authenticatedAt: new Date().toISOString(),
                });
            }
        } catch (error) {
            console.error("Failed to load profile:", error);
            applyProfile(fallbackUser, null);
        }
    }

    function applyProfile(user, profile) {
        const safeUser = user || getUser();

        if (!safeUser) {
            window.location.href = "./login.html";
            return;
        }

        const roleLabel = safeUser.role_name || getRoleDescription(safeUser.role_id).title;
        const district = profile && typeof profile === "object"
            ? String(profile.district || "")
            : String(safeUser.district || "");
        const profilePhoto = profile && typeof profile === "object"
            ? String(profile.profile_photo || "")
            : String(safeUser.profile_photo || "");

        if (userNameField) userNameField.textContent = `${safeUser.name} - ${safeUser.email}`;
        if (nameField) nameField.textContent = safeUser.name;
        if (editNameField) editNameField.value = safeUser.name;
        if (districtField) districtField.value = district;
        if (avatarLetterField) avatarLetterField.textContent = safeUser.name ? safeUser.name.charAt(0) : "U";
        if (userIdField) userIdField.textContent = `#${safeUser.user_id}`;
        if (roleField) roleField.textContent = roleLabel;
        if (roleTextField) roleTextField.value = roleLabel;
        if (emailField) emailField.value = safeUser.email || "N/A";

        if (profilePhotoInput) {
            profilePhotoInput.dataset.currentPhoto = profilePhoto;
        }
    }

    window.updateProfile = async function updateProfile() {
        const currentUser = getUser();

        if (!currentUser) {
            window.location.href = "./login.html";
            return;
        }

        const name = editNameField ? editNameField.value.trim() : "";
        const district = districtField ? districtField.value.trim() : "";
        const formData = new FormData();

        formData.append("name", name);
        formData.append("district", district);

        if (profilePhotoInput && profilePhotoInput.files && profilePhotoInput.files[0]) {
            formData.append("profile_photo", profilePhotoInput.files[0]);
        }

        const response = await fetch(`${API}/profile/update`, {
            method: "POST",
            headers: buildAuthHeaders(),
            body: formData
        });

        const data = await response.json();

        if (data.status === "success") {
            const roles = Array.isArray(data.roles) ? data.roles : getAuthorizedRoles();
            const selectedRole = data.selectedRole || getSelectedRole() || roles[0] || null;
            const updatedProfile = data.profile || {
                district,
                profile_photo: profilePhotoInput && profilePhotoInput.dataset.currentPhoto ? profilePhotoInput.dataset.currentPhoto : ""
            };

            setAuthState({
                user: {
                    ...(data.user || currentUser),
                    district: updatedProfile.district || "",
                    profile_photo: updatedProfile.profile_photo || ""
                },
                roles,
                selectedRole,
                authenticatedAt: new Date().toISOString(),
            });

            alert("Profile updated!");
            await loadProfile();
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

    loadProfile();
})();
