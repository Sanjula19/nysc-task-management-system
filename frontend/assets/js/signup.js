function getField(id) {
    return document.getElementById(id);
}

function setFieldState(field, errorEl, message) {
    if (field) {
        field.classList.toggle("is-invalid", Boolean(message));
    }

    if (errorEl) {
        errorEl.textContent = message || "";
    }
}

function setBanner(message, tone) {
    const banner = getField("signupStatus");

    if (!banner) {
        return;
    }

    banner.textContent = message;
    banner.dataset.tone = tone || "neutral";
}

function validateEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || "").trim());
}

function getFormValues() {
    return {
        name: getField("name"),
        email: getField("email"),
        role: getField("role"),
        password: getField("password"),
        confirmPassword: getField("confirmPassword"),
        togglePassword: getField("togglePassword"),
        signupBtn: getField("signupBtn"),
    };
}

function clearErrors(fields) {
    setFieldState(fields.name, getField("nameError"), "");
    setFieldState(fields.email, getField("emailError"), "");
    setFieldState(fields.role, getField("roleError"), "");
    setFieldState(fields.password, getField("passwordError"), "");
    setFieldState(fields.confirmPassword, getField("confirmPasswordError"), "");
}

function validateForm(fields) {
    clearErrors(fields);

    let valid = true;
    const nameValue = fields.name.value.trim();
    const emailValue = fields.email.value.trim();
    const roleValue = fields.role.value.trim();
    const passwordValue = fields.password.value;
    const confirmValue = fields.confirmPassword.value;

    if (!nameValue) {
        setFieldState(fields.name, getField("nameError"), "Official full name is required.");
        valid = false;
    }

    if (!emailValue) {
        setFieldState(fields.email, getField("emailError"), "Official email is required.");
        valid = false;
    } else if (!validateEmail(emailValue)) {
        setFieldState(fields.email, getField("emailError"), "Enter a valid official email address.");
        valid = false;
    }

    if (!roleValue) {
        setFieldState(fields.role, getField("roleError"), "Select an official role.");
        valid = false;
    }

    if (!passwordValue) {
        setFieldState(fields.password, getField("passwordError"), "Password is required.");
        valid = false;
    } else if (passwordValue.length < 6) {
        setFieldState(fields.password, getField("passwordError"), "Password must be at least 6 characters.");
        valid = false;
    }

    if (!confirmValue) {
        setFieldState(fields.confirmPassword, getField("confirmPasswordError"), "Please confirm your password.");
        valid = false;
    } else if (confirmValue !== passwordValue) {
        setFieldState(fields.confirmPassword, getField("confirmPasswordError"), "Passwords do not match.");
        valid = false;
    }

    return valid;
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

function togglePasswordVisibility() {
    const passwordField = getField("password");
    const toggleButton = getField("togglePassword");

    if (!passwordField || !toggleButton) {
        return;
    }

    const isPassword = passwordField.type === "password";
    passwordField.type = isPassword ? "text" : "password";
    toggleButton.setAttribute("aria-pressed", String(isPassword));
    toggleButton.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
    toggleButton.innerHTML = isPassword ? eyeClosedIcon() : eyeOpenIcon();
}

async function handleSignup(event) {
    event.preventDefault();

    const fields = getFormValues();
    if (!validateForm(fields)) {
        setBanner("Please correct the highlighted fields.", "error");
        return;
    }

    const name = fields.name.value.trim();
    const email = fields.email.value.trim();
    const role_id = Number(fields.role.value);
    const password = fields.password.value;
    const messageBox = getField("signupStatus");
    const submitBtn = fields.signupBtn;

    submitBtn.disabled = true;
    submitBtn.textContent = "Creating Account...";
    setBanner("Creating official account...", "neutral");

    try {
        const response = await fetch("/TMS/nysc-task-management-system/backend/routes/api.php/register", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                name,
                email,
                role_id,
                password
            })
        });

        const data = await response.json();
        console.log(data);

        if (response.ok && data.status === "success") {
            messageBox.className = "status-banner";
            messageBox.dataset.tone = "success";
            messageBox.textContent = data.message;

            setTimeout(() => {
                window.location.href = "./login.html";
            }, 1500);
        } else {
            messageBox.className = "status-banner";
            messageBox.dataset.tone = "error";
            messageBox.textContent = data.message || "Registration failed.";
        }
    } catch (error) {
        console.error(error);
        messageBox.className = "status-banner";
        messageBox.dataset.tone = "error";
        messageBox.textContent = "Unable to reach the registration service.";
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = "Create Official Account";
    }
}

function initSignupPage() {
    const form = getField("signupForm");
    const toggleButton = getField("togglePassword");
    const fields = getFormValues();

    if (toggleButton) {
        toggleButton.innerHTML = eyeClosedIcon();
        toggleButton.addEventListener("click", togglePasswordVisibility);
    }

    if (form) {
        form.addEventListener("submit", handleSignup);
    }

    [fields.name, fields.email, fields.role, fields.password, fields.confirmPassword].forEach((field) => {
        if (!field) {
            return;
        }

        field.addEventListener("input", () => {
            setBanner("Complete the form to create a verified account.", "neutral");
            clearErrors(fields);
        });

        field.addEventListener("change", () => {
            setBanner("Complete the form to create a verified account.", "neutral");
            clearErrors(fields);
        });
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSignupPage);
} else {
    initSignupPage();
}
