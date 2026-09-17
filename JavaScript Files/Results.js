function showDashboard() {
    document.getElementById("dashboardView")
        .classList.add("active-view");
    document.getElementById("circularView")
        .classList.remove("active-view");
    document.getElementById("dashboardBtn")
        .classList.add("active");
    document.getElementById("circularBtn")
        .classList.remove("active");
}
function showCircularView() {
    document.getElementById("dashboardView")
        .classList.remove("active-view");
    document.getElementById("circularView")
        .classList.add("active-view");
    document.getElementById("dashboardBtn")
        .classList.remove("active");
    document.getElementById("circularBtn")
        .classList.add("active");
}
function filterSubjects(type, button) {
    const subjects = document.querySelectorAll(".subject-row");
    const filters = document.querySelectorAll(".filter");
    filters.forEach(btn => btn.classList.remove("active"));
    button.classList.add("active");
    subjects.forEach(subject => {
        subject.style.display = type === "all" ||
            subject.classList.contains(type) ? "grid" : "none";
    });
}
function openSubject(
    subject,
    score,
    grade,
    internal,
    external,
    practical,
    attendance
) {
    document.getElementById("modalSubject").textContent = subject;
    document.getElementById("modalScore").textContent = score;
    document.getElementById("modalGrade").textContent = grade;
    document.getElementById("modalInternal").textContent = internal;
    document.getElementById("modalExternal").textContent = external;
    document.getElementById("modalPractical").textContent = practical;
    document.getElementById("modalAttendance").textContent = attendance;
    document.getElementById("subjectModal").classList.add("show");
}
function closeSubject() {
    document.getElementById("subjectModal").classList.remove("show");
}
document.getElementById("subjectModal").addEventListener("click", function(event) {
    if (event.target === this) closeSubject();
});
document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") closeSubject();
});
function downloadResult() {
    alert("Your result download will be generated here.");
}
document.getElementById("semesterSelect").addEventListener("change", function() {
    const semester = this.value;
    document.getElementById("cgpaValue").textContent =
        semester === "sem1" ? "8.21" : semester === "sem2" ? "8.72" : "-";
});