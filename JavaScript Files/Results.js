const dashboardView = document.getElementById("dashboardView");
const circularView = document.getElementById("circularView");
const semesterSelect = document.getElementById("semesterSelect");
const subjectModal = document.getElementById("subjectModal");
let studentResults = {};

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (character) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[character]);
}

function showDashboard() {
    dashboardView.classList.add("active-view");
    circularView.classList.remove("active-view");
    document.getElementById("dashboardBtn").classList.add("active");
    document.getElementById("circularBtn").classList.remove("active");
}

function showCircularView() {
    dashboardView.classList.remove("active-view");
    circularView.classList.add("active-view");
    document.getElementById("dashboardBtn").classList.remove("active");
    document.getElementById("circularBtn").classList.add("active");
}

function filterSubjects(type, button) {
    document.querySelectorAll(".filter").forEach((filter) => filter.classList.remove("active"));
    button.classList.add("active");
    document.querySelectorAll(".subject-row").forEach((subject) => {
        subject.style.display = type === "all" || subject.classList.contains(type) ? "grid" : "none";
    });
}

function openSubject(subject) {
    document.getElementById("modalSubject").textContent = subject.name || "";
    document.getElementById("modalScore").textContent = subject.score ?? "";
    document.getElementById("modalGrade").textContent = subject.grade || "";
    document.getElementById("modalInternal").textContent = subject.internal || "";
    document.getElementById("modalExternal").textContent = subject.external || "";
    document.getElementById("modalPractical").textContent = subject.practical || "";
    document.getElementById("modalAttendance").textContent = subject.attendance || "";
    subjectModal.querySelector(".teacher-feedback p").textContent = subject.feedback || "";
    subjectModal.classList.add("show");
}

function closeSubject() {
    subjectModal.classList.remove("show");
}

function getSubjects(semester) {
    return Array.isArray(semester?.subjects) ? semester.subjects.filter((subject) => subject && typeof subject === "object") : [];
}

function hasResultData(semester) {
    if (!semester) {
        return false;
    }
    return ["cgpa", "sgpa", "creditsEarned", "creditsTotal", "academicProgress"]
        .some((key) => String(semester[key] ?? "").trim() !== "") || getSubjects(semester).length > 0;
}

function renderNoResults(container) {
    container.innerHTML = '<div class="empty-results">No result information has been added to your account yet.</div>';
}

function renderSemester(semesterKey) {
    const semester = studentResults[semesterKey];
    const subjects = getSubjects(semester);

    if (!hasResultData(semester)) {
        renderNoResults(dashboardView);
        renderNoResults(circularView);
        return;
    }

    const summaryCards = [
        ["Current CGPA", semester.cgpa],
        ["Current SGPA", semester.sgpa],
        ["Credits Earned", semester.creditsEarned !== "" && semester.creditsTotal !== "" ? `${semester.creditsEarned}/${semester.creditsTotal}` : semester.creditsEarned],
        ["Academic Progress", semester.academicProgress]
    ];
    dashboardView.innerHTML = `
        <div class="stats-grid">
            ${summaryCards.map(([label, value]) => `
                <div class="stat-card blue-card">
                    <div class="stat-info"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>
                </div>
            `).join("")}
        </div>
        <div class="glass-card subjects-card">
            <div class="subjects-header">
                <div><span class="card-label">SUBJECT RESULTS</span><h2>Subject Performance</h2></div>
                <div class="filter-buttons">
                    <button class="filter active" type="button" onclick="filterSubjects('all', this)">All</button>
                    <button class="filter" type="button" onclick="filterSubjects('excellent', this)">A+</button>
                    <button class="filter" type="button" onclick="filterSubjects('good', this)">A</button>
                </div>
            </div>
            <div class="subjects-list">
                ${subjects.length ? subjects.map((subject, index) => {
                    const grade = String(subject.grade ?? "");
                    const category = grade === "A+" ? "excellent" : grade === "A" ? "good" : "";
                    const score = subject.score ?? "";
                    const percentage = Number(score);
                    const progress = Number.isFinite(percentage) ? Math.min(100, Math.max(0, percentage)) : 0;
                    return `
                        <div class="subject-row ${category}" data-subject-index="${index}" role="button" tabindex="0">
                            <div class="subject-info"><div><strong>${escapeHtml(subject.name)}</strong><span>${escapeHtml(subject.credits)}${subject.credits ? " Credits" : ""}</span></div></div>
                            <div class="grade">${escapeHtml(grade)}</div>
                            <div class="marks"><strong>${escapeHtml(score)}</strong></div>
                            <div class="progress-wrapper"><div class="progress"><span style="width:${progress}%"></span></div><small>${escapeHtml(score)}</small></div>
                            <div class="arrow"></div>
                        </div>
                    `;
                }).join("") : '<div class="empty-results">No subject results have been added for this semester.</div>'}
            </div>
        </div>
    `;

    const numericScores = subjects
        .map((subject) => subject.score === "" || subject.score == null ? NaN : Number(subject.score))
        .filter(Number.isFinite);
    const averageScore = numericScores.length
        ? numericScores.reduce((total, score) => total + score, 0) / numericScores.length
        : "";
    const plusGrades = subjects.filter((subject) => subject.grade === "A+").length;
    circularView.innerHTML = `
        <div class="visual-header">
            <div><span class="card-label">VISUAL PERFORMANCE</span><h2>Your Result at a Glance</h2></div>
            <div class="overall-score"><span>OVERALL CGPA</span><strong>${escapeHtml(semester.cgpa)}</strong></div>
        </div>
        <div class="circular-grid">
            ${subjects.map((subject, index) => {
                const percentage = Number(subject.score);
                const progress = Number.isFinite(percentage) ? Math.min(100, Math.max(0, percentage)) : 0;
                return `
                    <div class="circular-card" data-subject-index="${index}" role="button" tabindex="0">
                        <div class="circle" style="--percentage:${progress}"><div class="circle-inner"><strong>${escapeHtml(subject.score)}</strong><span>${escapeHtml(subject.grade)}</span></div></div>
                        <h3>${escapeHtml(subject.name)}</h3>
                        <p>${escapeHtml(subject.credits)}${subject.credits ? " Credits" : ""}</p>
                        <button type="button">View Details</button>
                    </div>
                `;
            }).join("") || '<div class="empty-results">No subject results have been added for this semester.</div>'}
        </div>
        <div class="circular-summary">
            <div class="summary-item"><span>Average Score</span><strong>${escapeHtml(averageScore === "" ? "" : `${averageScore.toFixed(1)}%`)}</strong></div>
            <div class="summary-item"><span>A+ Subjects</span><strong>${subjects.length ? plusGrades : ""}</strong></div>
            <div class="summary-item"><span>Credits Earned</span><strong>${escapeHtml(semester.creditsEarned)}</strong></div>
        </div>
    `;

    [dashboardView, circularView].forEach((container) => {
        container.querySelectorAll("[data-subject-index]").forEach((element) => {
            const showSubject = () => openSubject(subjects[Number(element.dataset.subjectIndex)]);
            element.addEventListener("click", showSubject);
            element.addEventListener("keydown", (event) => {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    showSubject();
                }
            });
        });
    });
}

dashboardView.innerHTML = '<div class="empty-results">Loading your results...</div>';
circularView.innerHTML = '<div class="empty-results">Loading your results...</div>';
dashboardView.hidden = false;
circularView.hidden = false;

fetch("PHP Files/current_user.php", { credentials: "same-origin" })
    .then(async (response) => {
        const result = await response.json();
        if (response.status === 401) {
            window.location.replace("LoginHome.html");
            return;
        }
        if (!response.ok) {
            throw new Error(result.error || "Unable to load your results.");
        }

        studentResults = result.student.results || {};
        const populatedSemesters = Array.from({ length: 8 }, (_, index) => `sem${index + 1}`)
            .filter((key) => hasResultData(studentResults[key]));
        if (populatedSemesters.length) {
            semesterSelect.value = populatedSemesters[populatedSemesters.length - 1];
        }
        renderSemester(semesterSelect.value);
    })
    .catch((error) => {
        const message = escapeHtml(error.message || "Unable to load your results.");
        dashboardView.innerHTML = `<div class="empty-results">${message}</div>`;
        circularView.innerHTML = `<div class="empty-results">${message}</div>`;
    });

semesterSelect.addEventListener("change", () => renderSemester(semesterSelect.value));
subjectModal.addEventListener("click", (event) => {
    if (event.target === subjectModal) {
        closeSubject();
    }
});
document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeSubject();
    }
});

function downloadResult() {
    alert("No result document is available to download yet.");
}
