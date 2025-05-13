document.addEventListener('DOMContentLoaded', function () {
    fetchMenuWithCache();
});

function toggleNavNew() {
    const navLinks = document.getElementById('navLinks');
    navLinks.classList.toggle('active');
}

function fetchMenuWithCache() {
    const cachedMenu = sessionStorage.getItem('admin_menu_items');
    const cacheTime = sessionStorage.getItem('admin_menu_time');
    const now = Date.now();

    // Use cached menu if it's less than 5 minutes old
    if (cachedMenu && cacheTime && (now - cacheTime < 5 * 60 * 1000)) {
        renderMenu(JSON.parse(cachedMenu));
        return;
    }

    // Fetch fresh menu from server
    fetch('backend/admin_menu.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                window.location.href = '../index.php';
                return;
            }

            // Cache the result
            sessionStorage.setItem('admin_menu_items', JSON.stringify(data.menuItems));
            sessionStorage.setItem('admin_menu_time', now);

            renderMenu(data.menuItems);
        })
        .catch(error => {
            console.error('Error fetching menu:', error);
        });
}

function renderMenu(menuItems) {
    const navLinks = document.getElementById('navLinks');
    navLinks.innerHTML = ''; // Clear existing links before re-rendering

    menuItems.forEach(item => {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = item.link;
        a.id = item.id;
        a.textContent = item.name;
        li.appendChild(a);
        navLinks.appendChild(li);
    });
}