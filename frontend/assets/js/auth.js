function getUser() {
    try {
        const raw = localStorage.getItem("user");
        return raw ? JSON.parse(raw) : null;
    } catch (error) {
        return null;
    }
}

function requireAuth() {
    if (!getUser()) {
        window.location.href = "index.html";
    }
}

function redirectIfLoggedIn() {
    if (getUser()) {
        window.location.href = "dashboard.html";
    }
}

function logout() {
    localStorage.removeItem("user");
    window.location.href = "index.html";
}

window.getUser = getUser;
window.requireAuth = requireAuth;
window.logout = logout;
window.redirectIfLoggedIn = redirectIfLoggedIn;
