const year = document.getElementById("year");
if (year) {
    year.textContent = new Date().getFullYear();
}

const profileStatus = document.getElementById("profileStatus");
fetch("PHP Files/current_user.php", { credentials: "same-origin" })
    .then(async (response) => {
        const result = await response.json();
        if (response.status === 401) {
            window.location.replace("LoginHome.html");
            return;
        }
        if (!response.ok) {
            throw new Error(result.error || "Unable to load your profile.");
        }

        const student = result.student;
        document.querySelectorAll("[data-profile-field]").forEach((field) => {
            const key = field.dataset.profileField;
            field.textContent = student[key] || "";
        });

        const avatar = document.getElementById("profileAvatar");
        if (avatar && student.fullname) {
            avatar.textContent = student.fullname
                .trim()
                .split(/\s+/)
                .map((part) => part.charAt(0))
                .join("")
                .slice(0, 2)
                .toUpperCase();
        }
        if (profileStatus) {
            profileStatus.textContent = "";
        }
    })
    .catch((error) => {
        if (profileStatus) {
            profileStatus.textContent = error.message || "Unable to load your profile.";
        }
    });

const menuItems = document.querySelectorAll(".menu-item");
const currentPage = window.location.pathname.split("/").pop().toLowerCase();
menuItems.forEach(item => {
    const link = item.getAttribute("href");
    if (!link) {
        return;
    }
    const linkPage = link.split("/").pop().toLowerCase();
    if (linkPage === currentPage) {
        menuItems.forEach(menu => {
            menu.classList.remove("active");
        });
        item.classList.add("active");
    }
});