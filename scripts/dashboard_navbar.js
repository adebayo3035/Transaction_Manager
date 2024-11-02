document.addEventListener('DOMContentLoaded', function() {
    fetchMenu();
});

function fetchMenu() {
    fetch('backend/admin_menu.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                // Redirect to login if not authenticated
                window.location.href = '../index.php';
                return;
            }

            const navLinks = document.getElementById('navLinks');
            data.menuItems.forEach(item => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = item.link;
                a.id = item.id;
                a.textContent = item.name;
                li.appendChild(a);
                navLinks.appendChild(li);
            });
        })
        .catch(error => {
            console.error('Error fetching menu:', error);
            // window.location.href = 'index.php';
        });
}

function toggleNav() {
    const nav = document.getElementById("navLinks");
    nav.classList.toggle("responsive");
}
