(function () {
    requireAuth();

    const user = getUser();
    const params = new URLSearchParams(window.location.search);
    const taskId = params.get("task_id");

    const state = {
        user: normalizeUser(user),
        taskId: taskId ? String(taskId) : "",
        task: null,
        comments: []
    };

    document.addEventListener("DOMContentLoaded", initTaskDetail);

    function normalizeUser(rawUser) {
        const normalized = rawUser ? { ...rawUser } : {};
        normalized.user_id = Number(normalized.user_id ?? normalized.id ?? 0);
        normalized.role_id = Number(normalized.role_id ?? 0);
        normalized.name = normalized.name || "";
        return normalized;
    }

    function initTaskDetail() {
        bindEvents();

        if (!state.taskId) {
            renderTaskError("Task ID is missing.");
            return;
        }

        loadTask();
        loadComments();
    }

    function bindEvents() {
        const logoutButton = document.getElementById("logoutBtn");
        const addCommentBtn = document.getElementById("addCommentBtn");
        const commentsList = document.getElementById("commentsList");

        if (logoutButton) {
            logoutButton.addEventListener("click", logout);
        }

        if (addCommentBtn) {
            addCommentBtn.addEventListener("click", handleAddComment);
        }

        if (commentsList) {
            commentsList.addEventListener("click", handleCommentAction);
        }
    }

    function getApiBase() {
        return "../backend/routes/api.php";
    }

    async function loadTask() {
        try {
            const response = await fetch(`${getApiBase()}/tasks/all`, {
                method: "GET",
                headers: buildAuthHeaders()
            });

            const data = await response.json();

            if (!response.ok || data.status !== "success") {
                renderTaskError(data.message || "Unable to load task details.");
                return;
            }

            const tasks = Array.isArray(data.tasks) ? data.tasks : [];
            const match = tasks.find((task) => String(task.task_id) === state.taskId);

            if (!match) {
                renderTaskError("Task not found.");
                return;
            }

            state.task = match;
            renderTaskDetails(match);
        } catch (error) {
            console.error("Failed to load task:", error);
            renderTaskError("Unable to connect to the server.");
        }
    }

    function renderTaskDetails(task) {
        const titleEl = document.getElementById("taskTitle");
        const descEl = document.getElementById("taskDescription");
        const statusEl = document.getElementById("taskStatus");
        const priorityEl = document.getElementById("taskPriority");
        const deadlineEl = document.getElementById("taskDeadline");

        if (titleEl) {
            titleEl.textContent = task.title || "Untitled Task";
        }

        if (descEl) {
            descEl.textContent = task.description || "No description provided.";
        }

        if (statusEl) {
            statusEl.textContent = task.status || "-";
        }

        if (priorityEl) {
            priorityEl.textContent = task.priority || "-";
        }

        if (deadlineEl) {
            deadlineEl.textContent = task.deadline || "-";
        }
    }

    function renderTaskError(message) {
        const titleEl = document.getElementById("taskTitle");
        const descEl = document.getElementById("taskDescription");
        const statusEl = document.getElementById("taskStatus");
        const priorityEl = document.getElementById("taskPriority");
        const deadlineEl = document.getElementById("taskDeadline");

        if (titleEl) {
            titleEl.textContent = "Task Details";
        }

        if (descEl) {
            descEl.textContent = message;
        }

        if (statusEl) {
            statusEl.textContent = "-";
        }

        if (priorityEl) {
            priorityEl.textContent = "-";
        }

        if (deadlineEl) {
            deadlineEl.textContent = "-";
        }
    }

    async function loadComments() {
        const commentsList = document.getElementById("commentsList");

        if (!commentsList) {
            return;
        }

        commentsList.innerHTML = renderCommentsLoading();

        try {
            const response = await fetch(
                `${getApiBase()}/tasks/comments?task_id=${encodeURIComponent(state.taskId)}`,
                {
                    method: "GET",
                    headers: buildAuthHeaders()
                }
            );

            const data = await response.json();

            if (!response.ok || data.status !== "success") {
                commentsList.innerHTML = renderCommentsMessage(data.message || "Unable to load comments.");
                return;
            }

            state.comments = Array.isArray(data.comments) ? data.comments : [];

            if (state.comments.length === 0) {
                commentsList.innerHTML = renderCommentsMessage("No comments yet.");
                return;
            }

            commentsList.innerHTML = state.comments.map(renderCommentItem).join("");
        } catch (error) {
            console.error("Failed to load comments:", error);
            commentsList.innerHTML = renderCommentsMessage("Unable to connect to the server.");
        }
    }

    function renderCommentsLoading() {
        return "<li class=\"comment-card\">Loading comments...</li>";
    }

    function renderCommentsMessage(message) {
        return `<li class=\"comment-card\">${escapeHtml(message)}</li>`;
    }

    function renderCommentItem(comment) {
        const isOwner = Number(comment.user_id) === Number(state.user.user_id);
        const actions = isOwner
            ? `
                <div class=\"comment-actions\">
                    <button class=\"comment-action\" data-action=\"edit\">Edit</button>
                    <button class=\"comment-action danger\" data-action=\"delete\">Delete</button>
                </div>
              `
            : "";

        return `
            <li class=\"comment-card\" data-comment-id=\"${escapeHtml(comment.comment_id)}\">
                <div class=\"comment-header\">
                    <span class=\"comment-author\">${escapeHtml(comment.name)}</span>
                    ${actions}
                </div>
                <p class=\"comment-body\">${escapeHtml(comment.content)}</p>
            </li>
        `;
    }

    function handleAddComment() {
        const input = document.getElementById("commentInput");

        if (!input) {
            return;
        }

        const content = input.value.trim();

        if (!content) {
            alert("Please enter a comment.");
            return;
        }

        addComment(content);
    }

    function handleCommentAction(event) {
        const button = event.target.closest("button[data-action]");

        if (!button) {
            return;
        }

        const action = button.getAttribute("data-action");
        const item = button.closest("li[data-comment-id]");

        if (!item) {
            return;
        }

        const commentId = item.getAttribute("data-comment-id");

        if (!commentId) {
            return;
        }

        const comment = state.comments.find((entry) => String(entry.comment_id) === String(commentId));

        if (!comment) {
            return;
        }

        if (action === "edit") {
            const nextContent = window.prompt("Edit comment:", comment.content || "");

            if (nextContent === null) {
                return;
            }

            const trimmed = nextContent.trim();

            if (!trimmed) {
                alert("Comment cannot be empty.");
                return;
            }

            updateComment(commentId, trimmed);
        }

        if (action === "delete") {
            const confirmed = window.confirm("Delete this comment?");

            if (!confirmed) {
                return;
            }

            deleteComment(commentId);
        }
    }

    async function addComment(content) {
        try {
            const response = await fetch(`${getApiBase()}/tasks/comments`, {
                method: "POST",
                headers: {
                    ...buildAuthHeaders(),
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    task_id: state.taskId,
                    content
                })
            });

            const data = await response.json();

            if (!response.ok || data.status !== "success") {
                alert(data.message || "Unable to add comment.");
                return;
            }

            const input = document.getElementById("commentInput");

            if (input) {
                input.value = "";
            }

            loadComments();
        } catch (error) {
            console.error("Failed to add comment:", error);
            alert("Unable to connect to the server.");
        }
    }

    async function updateComment(commentId, content) {
        try {
            const response = await fetch(`${getApiBase()}/tasks/comments`, {
                method: "PUT",
                headers: {
                    ...buildAuthHeaders(),
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    content
                })
            });

            const data = await response.json();

            if (!response.ok || data.status !== "success") {
                alert(data.message || "Unable to update comment.");
                return;
            }

            loadComments();
        } catch (error) {
            console.error("Failed to update comment:", error);
            alert("Unable to connect to the server.");
        }
    }

    async function deleteComment(commentId) {
        try {
            const response = await fetch(`${getApiBase()}/tasks/comments`, {
                method: "DELETE",
                headers: {
                    ...buildAuthHeaders(),
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    comment_id: commentId
                })
            });

            const data = await response.json();

            if (!response.ok || data.status !== "success") {
                alert(data.message || "Unable to delete comment.");
                return;
            }

            loadComments();
        } catch (error) {
            console.error("Failed to delete comment:", error);
            alert("Unable to connect to the server.");
        }
    }

    function buildAuthHeaders() {
        return {
            "user_id": String(state.user.user_id || ""),
            "role_id": String(state.user.role_id || "")
        };
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll("\"", "&quot;")
            .replaceAll("'", "&#39;");
    }
})();
