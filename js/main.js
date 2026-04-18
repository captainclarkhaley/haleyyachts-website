// ===== Drawer menu toggle =====
const mobileToggle = document.getElementById('mobileToggle');
const mainNav = document.getElementById('mainNav');

// Create backdrop element once
const navBackdrop = document.createElement('div');
navBackdrop.className = 'nav-backdrop';
document.body.appendChild(navBackdrop);

function closeDrawer() {
    if (mobileToggle) mobileToggle.classList.remove('open');
    if (mainNav) mainNav.classList.remove('open');
    navBackdrop.classList.remove('open');
    document.body.style.overflow = '';
}

function openDrawer() {
    if (mobileToggle) mobileToggle.classList.add('open');
    if (mainNav) mainNav.classList.add('open');
    navBackdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
}

if (mobileToggle && mainNav) {
    mobileToggle.addEventListener('click', () => {
        if (mainNav.classList.contains('open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });
}

// Close drawer on backdrop click
navBackdrop.addEventListener('click', closeDrawer);

// Close drawer on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mainNav && mainNav.classList.contains('open')) {
        closeDrawer();
    }
});

// ===== Sticky header shadow on scroll =====
window.addEventListener('scroll', () => {
    const header = document.getElementById('header');
    if (header) header.classList.toggle('scrolled', window.scrollY > 20);
});

// ===== Sub-tab switching =====
document.querySelectorAll('.sub-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const section = tab.closest('.section') || tab.closest('section') || tab.closest('.container');
        const target = tab.dataset.target;

        // Update active tab within this group
        const tabGroup = tab.closest('.sub-tabs');
        if (tabGroup) {
            tabGroup.querySelectorAll('.sub-tab').forEach(t => t.classList.remove('active'));
        }
        tab.classList.add('active');

        // Show target content — search the parent container
        const parent = tab.closest('.section') || tab.closest('section') || document;
        parent.querySelectorAll('.sub-content').forEach(c => c.classList.remove('active'));
        const content = parent.querySelector('#' + target);
        if (content) content.classList.add('active');
    });
});

// ===== Close drawer on link click =====
document.querySelectorAll('.main-nav a').forEach(link => {
    link.addEventListener('click', closeDrawer);
});

// ===== Email validation helper =====
function isValidEmail(email) {
    // Checks for: something@something.something (at least 2 char TLD)
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
}

// Add live validation to all email fields
document.querySelectorAll('input[type="email"]').forEach(input => {
    // Create error message element
    const errorMsg = document.createElement('div');
    errorMsg.className = 'error-msg';
    errorMsg.textContent = 'Please enter a valid email address';
    input.parentNode.appendChild(errorMsg);

    input.addEventListener('blur', function() {
        if (this.value && !isValidEmail(this.value)) {
            this.classList.add('field-error');
            errorMsg.classList.add('visible');
        } else {
            this.classList.remove('field-error');
            errorMsg.classList.remove('visible');
        }
    });

    input.addEventListener('input', function() {
        if (isValidEmail(this.value)) {
            this.classList.remove('field-error');
            errorMsg.classList.remove('visible');
        }
    });
});

// ===== Phone number validation with country selector =====
const phoneCountries = [
    { code: 'US', name: 'United States', dial: '+1', format: '###-###-####', digits: 10 },
    { code: 'CA', name: 'Canada', dial: '+1', format: '###-###-####', digits: 10 },
    { code: 'GB', name: 'United Kingdom', dial: '+44', format: '#### ######', digits: 10 },
    { code: 'AU', name: 'Australia', dial: '+61', format: '### ### ###', digits: 9 },
    { code: 'FR', name: 'France', dial: '+33', format: '# ## ## ## ##', digits: 9 },
    { code: 'DE', name: 'Germany', dial: '+49', format: '### #######', digits: 10 },
    { code: 'IT', name: 'Italy', dial: '+39', format: '### ### ####', digits: 10 },
    { code: 'ES', name: 'Spain', dial: '+34', format: '### ### ###', digits: 9 },
    { code: 'NL', name: 'Netherlands', dial: '+31', format: '# ########', digits: 9 },
    { code: 'MC', name: 'Monaco', dial: '+377', format: '## ## ## ##', digits: 8 },
    { code: 'GR', name: 'Greece', dial: '+30', format: '### ### ####', digits: 10 },
    { code: 'HR', name: 'Croatia', dial: '+385', format: '## ### ####', digits: 9 },
    { code: 'TR', name: 'Turkey', dial: '+90', format: '### ### ####', digits: 10 },
    { code: 'BS', name: 'Bahamas', dial: '+1', format: '###-###-####', digits: 10 },
    { code: 'MX', name: 'Mexico', dial: '+52', format: '## #### ####', digits: 10 },
    { code: 'BZ', name: 'Belize', dial: '+501', format: '###-####', digits: 7 },
    { code: 'PA', name: 'Panama', dial: '+507', format: '####-####', digits: 8 },
    { code: 'AW', name: 'Aruba', dial: '+297', format: '###-####', digits: 7 },
    { code: 'VI', name: 'US Virgin Islands', dial: '+1', format: '###-###-####', digits: 10 },
    { code: 'KY', name: 'Cayman Islands', dial: '+1', format: '###-###-####', digits: 10 },
];

function formatPhoneForCountry(rawDigits, country) {
    let formatted = '';
    let digitIndex = 0;
    for (let i = 0; i < country.format.length && digitIndex < rawDigits.length; i++) {
        if (country.format[i] === '#') {
            formatted += rawDigits[digitIndex];
            digitIndex++;
        } else {
            formatted += country.format[i];
        }
    }
    return formatted;
}

function setupPhoneValidation(phoneInput) {
    const wrapper = phoneInput.parentNode;
    const isDark = !!(phoneInput.closest('.section-navy') || phoneInput.closest('.contact-form'));

    // Build country dropdown
    const countrySelect = document.createElement('select');
    const selectStyle = isDark
        ? 'width:140px; padding:12px 10px; font-family:Open Sans,Arial,sans-serif; font-size:0.9rem; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.2); border-radius:3px; color:#fff; flex-shrink:0;'
        : 'width:140px; padding:10px 10px; font-family:Open Sans,Arial,sans-serif; font-size:0.9rem; border:1px solid #d0d0d0; border-radius:3px; color:#333; background:#fff; flex-shrink:0;';
    countrySelect.style.cssText = selectStyle;

    phoneCountries.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.code;
        opt.textContent = `${c.code} ${c.dial}`;
        if (c.code === 'US') opt.selected = true;
        countrySelect.appendChild(opt);
    });

    // Style the phone input for flex layout
    const inputStyle = isDark
        ? 'flex:1; padding:12px 16px; font-family:Open Sans,Arial,sans-serif; font-size:0.9rem; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.2); border-radius:3px; color:#fff; margin-bottom:0;'
        : 'flex:1; padding:10px 14px; font-family:Open Sans,Arial,sans-serif; font-size:0.9rem; border:1px solid #d0d0d0; border-radius:3px; color:#333; background:#fff; margin-bottom:0;';
    phoneInput.style.cssText = inputStyle;

    // Create row container and insert it WHERE the phone input currently sits
    const phoneRow = document.createElement('div');
    phoneRow.style.cssText = 'display:flex; gap:8px;';
    phoneRow.appendChild(countrySelect);

    // Insert the row right where the input is, then move input into the row
    wrapper.insertBefore(phoneRow, phoneInput);
    phoneRow.appendChild(phoneInput);

    // Match the standard field margin-bottom
    phoneInput.style.marginBottom = '0';
    countrySelect.style.marginBottom = '0';

    // Create error message right after the row
    const errorMsg = document.createElement('div');
    errorMsg.className = 'error-msg';
    wrapper.insertBefore(errorMsg, phoneRow.nextSibling);

    function getSelectedCountry() {
        return phoneCountries.find(c => c.code === countrySelect.value);
    }

    function updatePlaceholder() {
        const country = getSelectedCountry();
        // Show format WITHOUT country code (user can't type it — it's in the dropdown)
        phoneInput.placeholder = country.format.replace(/#/g, 'X');
    }

    updatePlaceholder();
    countrySelect.addEventListener('change', () => {
        updatePlaceholder();
        if (phoneInput.value) {
            const digits = phoneInput.value.replace(/\D/g, '');
            const country = getSelectedCountry();
            phoneInput.value = formatPhoneForCountry(digits.substring(0, country.digits), country);
            validatePhone();
        }
    });

    // Auto-format as user types
    phoneInput.addEventListener('input', function() {
        const digits = this.value.replace(/\D/g, '');
        const country = getSelectedCountry();
        const trimmed = digits.substring(0, country.digits);
        this.value = formatPhoneForCountry(trimmed, country);

        if (digits.length <= country.digits) {
            this.classList.remove('field-error');
            errorMsg.classList.remove('visible');
        }
    });

    function validatePhone() {
        const digits = phoneInput.value.replace(/\D/g, '');
        const country = getSelectedCountry();
        if (digits.length > 0 && digits.length !== country.digits) {
            phoneInput.classList.add('field-error');
            errorMsg.textContent = `${country.name} numbers require ${country.digits} digits: ${country.format.replace(/#/g, 'X')}`;
            errorMsg.classList.add('visible');
            return false;
        } else {
            phoneInput.classList.remove('field-error');
            errorMsg.classList.remove('visible');
            return true;
        }
    }

    phoneInput.addEventListener('blur', validatePhone);

    // Expose helpers for form submit scripts
    phoneInput._validatePhone = validatePhone;
    phoneInput._getFormattedValue = function() {
        const digits = phoneInput.value.replace(/\D/g, '');
        const country = getSelectedCountry();
        if (digits.length === 0) return '';
        return country.dial + ' ' + formatPhoneForCountry(digits, country);
    };
}

// Auto-setup all phone fields
document.querySelectorAll('input[type="tel"]').forEach(setupPhoneValidation);

// ===== Activate sub-tab from URL hash (e.g., sell.html#valuation) =====
(function() {
    const hash = window.location.hash.replace('#', '');
    if (hash) {
        const targetTab = document.querySelector(`.sub-tab[data-target="${hash}"]`);
        if (targetTab) targetTab.click();
    }
})();

// ===== Scroll guard: prevent YachtSite embed from auto-scrolling the page =====
(function() {
    if (!document.querySelector('.yachtsite-embed')) return;

    let userInteracted = false;
    const markInteracted = () => { userInteracted = true; };
    ['wheel', 'keydown', 'touchstart', 'mousedown', 'pointerdown'].forEach(evt => {
        window.addEventListener(evt, markInteracted, { passive: true, once: true });
    });

    let lockedScrollY = window.scrollY;
    const guardEndsAt = Date.now() + 20000; // 20-second guard window after load
    let restoring = false;

    window.addEventListener('scroll', () => {
        if (restoring) return;
        if (userInteracted) { lockedScrollY = window.scrollY; return; }
        if (Date.now() > guardEndsAt) return;

        const delta = window.scrollY - lockedScrollY;
        if (Math.abs(delta) > 80) {
            restoring = true;
            window.scrollTo({ top: lockedScrollY, left: 0, behavior: 'instant' });
            setTimeout(() => { restoring = false; }, 30);
        } else {
            lockedScrollY = window.scrollY;
        }
    }, { passive: true });
})();

// ===== Fixed breadcrumbs bar (article pages) =====
(function() {
    const breadcrumbs = document.querySelector('.breadcrumbs');
    const header = document.getElementById('header');
    if (breadcrumbs && header) {
        function positionBreadcrumbs() {
            var headerHeight = header.offsetHeight;
            breadcrumbs.style.top = headerHeight + 'px';
        }
        positionBreadcrumbs();
        window.addEventListener('resize', positionBreadcrumbs);
    }
})();

// ===== Highlight active nav link based on current page =====
(function() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.main-nav > li > a').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === 'index.html' && href === 'index.html')) {
            link.classList.add('active');
        }
    });
})();
