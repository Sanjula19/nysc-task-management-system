const AUTH_STORAGE_KEY = "nysc_tms_auth";
const AUTH_USER_KEY = "user";
const AUTH_ROLES_KEY = "roles";
const AUTH_SELECTED_ROLE_KEY = "selectedRole";
const AUTH_API_BASE = "/TMS/nysc-task-management-system/backend/routes/api.php";

const ROLE_DESCRIPTIONS = {
    1: {
        title: "Chairman",
        description: "Strategic oversight, executive approval, and final administrative authority.",
    },
    2: {
        title: "Director",
        description: "Departmental supervision, policy review, and high-level operational control.",
    },
    3: {
        title: "Deputy Director",
        description: "Delegated oversight, coordination, and execution support across units.",
    },
    4: {
        title: "Assistant Director",
        description: "Task execution, status updates, and delivery management for assigned work.",
    },
};

function safeParseJson(rawValue) {
    if (!rawValue) {
        return null;
    }

    if (typeof rawValue === "object") {
        return rawValue;
    }

    try {
        return JSON.parse(rawValue);
    } catch (error) {
        return null;
    }
}

function readStoredJson(key) {
    return safeParseJson(localStorage.getItem(key));
}

function normalizeRole(role) {
    if (!role || typeof role !== "object") {
        return null;
    }

    const roleId = Number(role.role_id ?? role.id ?? 0);

    if (!roleId) {
        return null;
    }

    const roleMeta = ROLE_DESCRIPTIONS[roleId] || {};

    return {
        role_id: roleId,
        role_name: String(role.role_name ?? role.name ?? roleMeta.title ?? "").trim(),
    };
}

function normalizeRoles(roles) {
    if (!Array.isArray(roles)) {
        return [];
    }

    const mapped = roles
        .map(normalizeRole)
        .filter((role) => role && role.role_id);

    const unique = new Map();

    mapped.forEach((role) => {
        unique.set(role.role_id, role);
    });

    return Array.from(unique.values());
}

function normalizeUser(user) {
    const source = user && typeof user === "object" ? user : {};
    const roleId = Number(source.role_id ?? source.selected_role_id ?? source.active_role_id ?? 0);
    const roleMeta = ROLE_DESCRIPTIONS[roleId] || {};

    return {
        user_id: Number(source.user_id ?? source.id ?? 0),
        name: String(source.name ?? "").trim(),
        email: String(source.email ?? "").trim(),
        role_id: roleId,
        role_name: String(source.role_name ?? source.selected_role_name ?? source.active_role_name ?? roleMeta.title ?? "").trim(),
        district: String(source.district ?? "").trim(),
        profile_photo: String(source.profile_photo ?? source.profilePhoto ?? "").trim(),
    };
}

function normalizeAuthState(rawState) {
    const source = safeParseJson(rawState);

    if (!source || typeof source !== "object") {
        return null;
    }

    const sourceUser = source.user && typeof source.user === "object" ? source.user : source;
    const user = normalizeUser(sourceUser);
    const roles = normalizeRoles(source.roles ?? sourceUser.roles ?? sourceUser.authorized_roles ?? []);
    const selectedRole = normalizeRole(
        source.selectedRole ??
        source.selected_role ??
        source.activeRole ??
        sourceUser.selectedRole ??
        sourceUser.activeRole ??
        sourceUser.selected_role ??
        (user.role_id ? { role_id: user.role_id, role_name: user.role_name } : null)
    );

    const normalizedUser = { ...user };

    if (selectedRole) {
        normalizedUser.role_id = selectedRole.role_id;
        normalizedUser.role_name = selectedRole.role_name;
    } else if (normalizedUser.role_id && !normalizedUser.role_name) {
        normalizedUser.role_name = ROLE_DESCRIPTIONS[normalizedUser.role_id]?.title || "";
    }

    const normalizedRoles = roles.length > 0
        ? roles
        : normalizedUser.role_id
            ? [{
                role_id: normalizedUser.role_id,
                role_name: normalizedUser.role_name || ROLE_DESCRIPTIONS[normalizedUser.role_id]?.title || "",
            }]
            : [];

    const resolvedSelectedRole = selectedRole
        ? normalizedRoles.find((role) => role.role_id === selectedRole.role_id) || selectedRole
        : normalizedRoles[0] || null;

    return {
        user: normalizedUser,
        roles: normalizedRoles,
        selectedRole: resolvedSelectedRole && resolvedSelectedRole.role_id ? resolvedSelectedRole : null,
        authenticatedAt: source.authenticatedAt ?? new Date().toISOString(),
    };
}

function getAuthState() {
    const combinedState = safeParseJson(localStorage.getItem(AUTH_STORAGE_KEY));

    if (combinedState && typeof combinedState === "object") {
        return normalizeAuthState(combinedState);
    }

    const user = readStoredJson(AUTH_USER_KEY);
    const roles = readStoredJson(AUTH_ROLES_KEY);
    const selectedRole = readStoredJson(AUTH_SELECTED_ROLE_KEY);

    if (!user && !roles && !selectedRole) {
        return null;
    }

    return normalizeAuthState({
        user: user || null,
        roles: Array.isArray(roles) ? roles : [],
        selectedRole: selectedRole || null,
        authenticatedAt: new Date().toISOString(),
    });
}

function setAuthState(state) {
    const normalized = normalizeAuthState(state);

    if (!normalized) {
        localStorage.removeItem(AUTH_STORAGE_KEY);
        localStorage.removeItem(AUTH_USER_KEY);
        localStorage.removeItem(AUTH_ROLES_KEY);
        localStorage.removeItem(AUTH_SELECTED_ROLE_KEY);
        return null;
    }

    localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(normalized));
    localStorage.setItem(AUTH_USER_KEY, JSON.stringify(normalized.user));
    localStorage.setItem(AUTH_ROLES_KEY, JSON.stringify(normalized.roles));

    if (normalized.selectedRole) {
        localStorage.setItem(AUTH_SELECTED_ROLE_KEY, JSON.stringify(normalized.selectedRole));
    } else {
        localStorage.removeItem(AUTH_SELECTED_ROLE_KEY);
    }

    return normalized;
}

function clearAuthState() {
    localStorage.removeItem(AUTH_STORAGE_KEY);
    localStorage.removeItem(AUTH_USER_KEY);
    localStorage.removeItem(AUTH_ROLES_KEY);
    localStorage.removeItem(AUTH_SELECTED_ROLE_KEY);
}

function hasPendingAuth() {
    const state = getAuthState();

    return Boolean(state && state.user && state.roles.length > 0 && !state.selectedRole);
}

function getSelectedRole() {
    const state = getAuthState();

    return state ? state.selectedRole : null;
}

function getAuthorizedRoles() {
    const state = getAuthState();

    return state ? state.roles : [];
}

function getActiveRoleId() {
    const selectedRole = getSelectedRole();

    return selectedRole ? Number(selectedRole.role_id || 0) : 0;
}

function getUser() {
    const state = getAuthState();

    if (!state || !state.user) {
        return null;
    }

    const activeRole = state.selectedRole || state.roles[0] || null;

    if (!activeRole || !activeRole.role_id) {
        return null;
    }

    return {
        ...state.user,
        role_id: activeRole.role_id,
        role_name: activeRole.role_name,
        selected_role_id: activeRole.role_id,
        selected_role_name: activeRole.role_name,
        active_role_id: activeRole.role_id,
        active_role_name: activeRole.role_name,
        roles: state.roles,
        authorized_roles: state.roles,
    };
}

function isAuthenticated() {
    const state = getAuthState();

    return Boolean(state && state.user && (state.selectedRole || state.roles.length > 0));
}

function buildAuthHeaders(extraHeaders = {}) {
    const user = getUser();

    if (!user) {
        return { ...extraHeaders };
    }

    return {
        ...extraHeaders,
        user_id: String(user.user_id || ""),
        role_id: String(user.role_id || ""),
        x_user_id: String(user.user_id || ""),
        x_role_id: String(user.role_id || ""),
    };
}

function requireAuth() {
    if (!isAuthenticated()) {
        window.location.href = "./login.html";
        return false;
    }

    return true;
}

function redirectIfLoggedIn() {
    if (isAuthenticated()) {
        window.location.href = "./dashboard.html";
    }
}

function logout() {
    try {
        fetch(`${AUTH_API_BASE}/logout`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            keepalive: true
        });
    } catch (error) {
        console.warn("Logout request failed:", error);
    }

    clearAuthState();
    window.location.href = "./login.html";
}

function getRoleDescription(roleId) {
    return ROLE_DESCRIPTIONS[Number(roleId)] || {
        title: "Authorized Role",
        description: "Approved access for official system use.",
    };
}

async function submitOfficialLogin(event) {
    event.preventDefault();

    const form = document.getElementById("loginForm");
    const emailField = document.getElementById("email");
    const passwordField = document.getElementById("password");
    const authenticateButton = document.getElementById("authenticateBtn");
    const statusMessage = document.getElementById("statusMessage");

    if (!form || !emailField || !passwordField || !authenticateButton) {
        return;
    }

    const email = emailField.value.trim();
    const password = passwordField.value;

    setFieldErrorState(emailField, false);
    setFieldErrorState(passwordField, false);
    setStatusMessage(statusMessage, "Authenticating official credentials...", "neutral");

    authenticateButton.disabled = true;
    authenticateButton.textContent = "Authenticating...";

    try {
        const response = await fetch(`${AUTH_API_BASE}/login`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                email,
                password,
            }),
        });

        const data = await response.json();

        if (!response.ok || data.status !== "success") {
            setFieldErrorState(emailField, true);
            setFieldErrorState(passwordField, true);
            clearAuthState();

            setStatusMessage(
                statusMessage,
                data.message || "Invalid official credentials.",
                "error"
            );
            renderAuthorizedRoles([]);
            hideRoleSelection();
            return;
        }

        const user = normalizeUser({
            ...(data.user || {}),
            ...(data.profile || {})
        });
        const roles = normalizeRoles(data.roles || []);

        if (roles.length === 0) {
            clearAuthState();
            setFieldErrorState(emailField, true);
            setFieldErrorState(passwordField, true);
            setStatusMessage(statusMessage, "No authorized role assigned", "error");
            renderAuthorizedRoles([]);
            hideRoleSelection();
            return;
        }

        setAuthState({
            user,
            roles,
            selectedRole: roles[0],
            authenticatedAt: new Date().toISOString(),
        });

        localStorage.setItem("user", JSON.stringify(user));
        localStorage.setItem("roles", JSON.stringify(roles));

        setStatusMessage(statusMessage, "Authentication successful. Redirecting to the dashboard...", "success");
        window.location.href = "./dashboard.html";
    } catch (error) {
        setStatusMessage(statusMessage, "Unable to reach the authentication service.", "error");
    } finally {
        authenticateButton.disabled = false;
        authenticateButton.textContent = "Authenticate Access";
    }
}

function renderAuthorizedRoles(roles) {
    const roleGrid = document.getElementById("roleGrid");

    if (!roleGrid) {
        return;
    }

    const normalizedRoles = normalizeRoles(roles);

    if (normalizedRoles.length === 0) {
        roleGrid.innerHTML = "";
        return;
    }

    const pendingState = getAuthState();
    const selectedRoleId = pendingState && pendingState.selectedRole ? pendingState.selectedRole.role_id : 0;

    roleGrid.innerHTML = normalizedRoles.map((role) => {
        const roleInfo = getRoleDescription(role.role_id);
        const selectedClass = selectedRoleId === role.role_id ? " is-selected" : "";

        return `
            <label class="role-card${selectedClass}">
                <input
                    class="role-radio"
                    type="radio"
                    name="authorizedRole"
                    value="${role.role_id}"
                    ${selectedRoleId === role.role_id ? "checked" : ""}
                >
                <span class="role-card__title">${escapeHtml(role.role_name || roleInfo.title)}</span>
                <span class="role-card__copy">${escapeHtml(roleInfo.description)}</span>
            </label>
        `;
    }).join("");
}

function showRoleSelection(user, roles) {
    const roleSection = document.getElementById("roleSection");
    const roleSummary = document.getElementById("authorizedUserSummary");

    if (roleSection) {
        roleSection.classList.add("is-visible");
        roleSection.setAttribute("aria-hidden", "false");
    }

    if (roleSummary && user) {
        roleSummary.textContent = `${user.name} ${user.email ? `(${user.email})` : ""}`.trim();
    }

    if (roles.length === 0) {
        hideRoleSelection();
    }
}

function hideRoleSelection() {
    const roleSection = document.getElementById("roleSection");
    const continueButton = document.getElementById("continueBtn");

    if (roleSection) {
        roleSection.classList.remove("is-visible");
        roleSection.setAttribute("aria-hidden", "true");
    }

    if (continueButton) {
        continueButton.disabled = true;
    }
}

function handleRoleSelectionChange(event) {
    const input = event.target.closest("input[name='authorizedRole']");

    if (!input) {
        return;
    }

    const roleId = Number(input.value || 0);
    selectAuthorizedRole(roleId);
}

function selectAuthorizedRole(roleId) {
    const state = getAuthState();

    if (!state) {
        return;
    }

    const selectedRole = state.roles.find((role) => Number(role.role_id) === Number(roleId));

    if (!selectedRole) {
        return;
    }

    state.selectedRole = selectedRole;
    state.user.role_id = selectedRole.role_id;
    state.user.role_name = selectedRole.role_name;

    setAuthState(state);
    syncRoleSelectionUI(selectedRole.role_id);
    updateContinueState();

    const statusMessage = document.getElementById("statusMessage");
    setStatusMessage(statusMessage, `${selectedRole.role_name} access selected. Continue to the dashboard.`, "success");
}

function syncRoleSelectionUI(selectedRoleId) {
    const cards = document.querySelectorAll(".role-card");

    cards.forEach((card) => {
        const input = card.querySelector("input[name='authorizedRole']");
        const isSelected = Number(input && input.value ? input.value : 0) === Number(selectedRoleId);

        card.classList.toggle("is-selected", isSelected);

        if (input) {
            input.checked = isSelected;
        }
    });
}

function updateContinueState() {
    const continueButton = document.getElementById("continueBtn");

    if (!continueButton) {
        return;
    }

    continueButton.disabled = !isAuthenticated();
}

function continueToDashboard() {
    if (!isAuthenticated()) {
        return;
    }

    window.location.href = "./dashboard.html";
}

function togglePasswordVisibility() {
    const passwordField = document.getElementById("password");
    const toggleButton = document.getElementById("togglePassword");

    if (!passwordField || !toggleButton) {
        return;
    }

    const isPassword = passwordField.type === "password";
    passwordField.type = isPassword ? "text" : "password";
    toggleButton.setAttribute("aria-pressed", String(isPassword));
    toggleButton.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
    toggleButton.innerHTML = isPassword
        ? eyeClosedIcon()
        : eyeOpenIcon();
}

function setFieldErrorState(field, isError) {
    if (!field) {
        return;
    }

    field.classList.toggle("is-invalid", Boolean(isError));
}

function setStatusMessage(target, message, tone) {
    if (!target) {
        return;
    }

    target.textContent = message;
    target.dataset.tone = tone || "neutral";
}

function eyeOpenIcon() {
    return `
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
            <circle cx="12" cy="12" r="3" />
        </svg>
    `;
}

function eyeClosedIcon() {
    return `
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M3 3l18 18" />
            <path d="M10.6 10.6A2 2 0 0 0 12 15a3 3 0 0 0 2.12-.88" />
            <path d="M6.4 6.6C4.1 8.1 2.5 10 2 12c1.4 4.9 6 8 10 8 1.3 0 2.6-.2 3.8-.7" />
            <path d="M17.6 17.4C19.9 15.9 21.5 14 22 12c-1.4-4.9-6-8-10-8-1.1 0-2.2.1-3.3.5" />
        </svg>
    `;
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll("\"", "&quot;")
        .replaceAll("'", "&#39;");
}

function initLoginPage() {
    const form = document.getElementById("loginForm");

    if (!form) {
        return;
    }

    const emailField = document.getElementById("email");
    const passwordField = document.getElementById("password");
    const toggleButton = document.getElementById("togglePassword");
    const roleGrid = document.getElementById("roleGrid");
    const continueButton = document.getElementById("continueBtn");
    const statusMessage = document.getElementById("statusMessage");

    const state = getAuthState();

    if (isAuthenticated()) {
        redirectIfLoggedIn();
        return;
    }

    if (toggleButton) {
        toggleButton.innerHTML = eyeClosedIcon();
        toggleButton.addEventListener("click", togglePasswordVisibility);
    }

    if (form) {
        form.addEventListener("submit", submitOfficialLogin);
    }

    if (roleGrid) {
        roleGrid.addEventListener("change", handleRoleSelectionChange);
    }

    if (continueButton) {
        continueButton.addEventListener("click", continueToDashboard);
    }

    if (state && state.user && state.roles.length > 0 && !state.selectedRole) {
        if (emailField && state.user.email) {
            emailField.value = state.user.email;
        }

        renderAuthorizedRoles(state.roles);
        showRoleSelection(state.user, state.roles);
        setStatusMessage(statusMessage, "Authentication verified. Select an authorized role to continue.", "success");
    } else {
        hideRoleSelection();
        setStatusMessage(statusMessage, "Authenticate your official credentials to view authorized roles.", "neutral");
    }

    if (emailField) {
        emailField.addEventListener("input", () => setFieldErrorState(emailField, false));
    }

    if (passwordField) {
        passwordField.addEventListener("input", () => setFieldErrorState(passwordField, false));
    }

    updateContinueState();
}

function bootstrapAuth() {
    initLoginPage();
}

window.getAuthState = getAuthState;
window.getAuthorizedRoles = getAuthorizedRoles;
window.getSelectedRole = getSelectedRole;
window.getActiveRoleId = getActiveRoleId;
window.getUser = getUser;
window.requireAuth = requireAuth;
window.logout = logout;
window.redirectIfLoggedIn = redirectIfLoggedIn;
window.buildAuthHeaders = buildAuthHeaders;
window.setAuthState = setAuthState;
window.clearAuthState = clearAuthState;
window.selectAuthorizedRole = selectAuthorizedRole;

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootstrapAuth);
} else {
    bootstrapAuth();
}
