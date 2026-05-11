// Pindahkan notifikasi ke navbar jika di mode mobile (dashboard only)
function adjustNotifPosition() {
    const wrapper = document.getElementById('notif-wrapper');
    const desktopContainer = document.getElementById('desktop-notif-container');
    const mobileContainer = document.getElementById('mobile-notif-container');
    const navbarContainer = document.getElementById('navbar-notif-container');
    const notifBtn = document.getElementById('notif-bell-btn');
    
    if (!wrapper || !notifBtn) return;

    if (window.innerWidth <= 768) {
        if (mobileContainer && !mobileContainer.contains(wrapper)) {
            mobileContainer.appendChild(wrapper);
        }
    } else {
        if (desktopContainer && !desktopContainer.contains(wrapper)) {
            desktopContainer.appendChild(wrapper);
        } else if (navbarContainer && !navbarContainer.contains(wrapper)) {
            navbarContainer.appendChild(wrapper);
        }
    }
}

window.addEventListener('resize', adjustNotifPosition);
document.addEventListener('DOMContentLoaded', adjustNotifPosition);

// Mobile Sidebar toggle
function toggleMobileMenuV2() {
    const sidebar = document.getElementById('mobile-sidebar');
    const overlay = document.getElementById('mobile-overlay');
    if(sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
    }
}

// Profile dropdown toggle
function toggleProfileDropdown(e) {
    e.stopPropagation();
    document.getElementById('profile-wrapper').classList.toggle('active');
    
    // Tutup notif kalo lagi buka profile (opsional)
    const notif = document.getElementById('notif-dropdown');
    if(notif && notif.classList.contains('show')) {
        notif.classList.remove('show');
    }
}

// Tutup dropdown kalau klik di luar
document.addEventListener('click', function(e) {
    const profileWrap = document.getElementById('profile-wrapper');
    if (profileWrap && !profileWrap.contains(e.target)) {
        profileWrap.classList.remove('active');
    }
});

// Update icon theme saat di load & ketika di toggle
function updateThemeIcons() {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const iconDrop = document.getElementById('theme-icon-dropdown');
    const iconMobile = document.getElementById('theme-icon-mobile');
    if (iconDrop) iconDrop.textContent = isLight ? '☀️' : '🌙';
    if (iconMobile) iconMobile.textContent = isLight ? '☀️' : '🌙';
    
    // Update label text for theme switch
    const switchItem = document.querySelector('.theme-switch-item');
    if (switchItem) {
        const label = switchItem.querySelector('span[style*="flex"]');
        if (label) label.textContent = isLight ? 'Mode Terang' : 'Mode Gelap';
    }
}
document.addEventListener('DOMContentLoaded', updateThemeIcons);
const observer = new MutationObserver(updateThemeIcons);
observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
