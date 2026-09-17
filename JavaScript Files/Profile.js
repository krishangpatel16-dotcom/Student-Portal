const year = document.getElementById("year");
if (year) {
    year.textContent = new Date().getFullYear();
}
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