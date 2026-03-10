/**
 * Dashboard JavaScript - validace, toasty, modal, motiv stranky
 */

function showToast(message, type = 'success') {
    const icons = {
        success: '<i class="fas fa-check-circle"></i>',
        error: '<i class="fas fa-times-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>',
        info: '<i class="fas fa-info-circle"></i>',
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || icons.info}</span>
        <div class="toast-content">
            <div class="toast-message">${message}</div>
        </div>
        <div class="toast-progress"></div>
    `;

    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

function setButtonLoading(button, loading) {
    if (loading) {
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = '<span class="spinner"></span> ' + (button.dataset.loadingText || 'Načítám...');
        button.classList.add('btn-loading');
        button.disabled = true;
    } else {
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
            delete button.dataset.originalText;
        }
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
}

function showTableLoading(targetSelector) {
    if (!targetSelector) return;
    const tableWrap = document.querySelector(targetSelector);
    if (!tableWrap) return;
    tableWrap.classList.add('is-loading');
}

function setupFormLoading() {
    const forms = document.querySelectorAll('form:not([data-no-loading])');
    forms.forEach((form) => {
        form.addEventListener('submit', function () {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn && !submitBtn.classList.contains('btn-loading')) {
                setTimeout(() => {
                    setButtonLoading(submitBtn, true);
                }, 50);
            }

            const tableTarget = form.dataset.tableTarget;
            if (tableTarget) {
                showTableLoading(tableTarget);
            }
        });
    });
}

function setupTableLinkLoading() {
    const tableLinks = document.querySelectorAll('.table th a[href], .pagination a[href]');
    if (!tableLinks.length) return;

    tableLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const href = link.getAttribute('href') || '';
            const hasModifier = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

            if (event.button !== 0 || hasModifier || link.target === '_blank') return;
            if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

            const tableWrap =
                link.closest('.table-responsive') ||
                link.closest('.card')?.querySelector('.table-responsive');

            if (tableWrap) {
                tableWrap.classList.add('is-loading');
            }

            if (link.classList.contains('btn')) {
                link.classList.add('btn-loading');
            }
        });
    });
}

/* ============================================
   ADVANCED EXPORT OPTIONS
   ============================================ */

function setupAdvancedExport() {
    const openBtn = document.getElementById('openExportModalBtn');
    const modal = document.getElementById('exportModal');
    const confirmBtn = document.getElementById('confirmExportBtn');
    const exportForm = document.getElementById('exportForm');

    if (!openBtn || !modal || !confirmBtn || !exportForm) {
        return;
    }

    openBtn.addEventListener('click', () => {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    });

    modal.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        });
    });

    const formatRadios = exportForm.querySelectorAll('input[name="format"]');
    formatRadios.forEach((radio) => {
        radio.addEventListener('change', () => {
            const formatInput = document.getElementById('exportFormat');
            if (formatInput) {
                formatInput.value = radio.value;
            }
        });
    });

    confirmBtn.addEventListener('click', () => {
        const checkedColumns = exportForm.querySelectorAll('input[name="columns[]"]:checked');
        if (checkedColumns.length === 0) {
            showToast('Vyberte alespoň jeden sloupec pro export', 'warning');
            return;
        }

        setButtonLoading(confirmBtn, true);
        showTableLoading('#employeeTableView');

        const selectedFormat = exportForm.querySelector('input[name="format"]:checked');
        const formData = new FormData(exportForm);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            params.append(key, value);
        }

        params.set('export', selectedFormat ? selectedFormat.value : 'csv');
        window.location.href = 'export.php?' + params.toString();
    });
}

function applyTheme(theme) {
    const aliases = {
        light: 'corporate',
        dark: 'ocean',
    };
    const resolvedTheme = aliases[theme] || theme;
    document.documentElement.setAttribute('data-theme', resolvedTheme);
    localStorage.setItem('app-theme', resolvedTheme);
}

function setupThemeToggle() {
    const current = localStorage.getItem('app-theme') || 'corporate';
    applyTheme(current);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'corporate';
            const next = currentTheme === 'corporate' ? 'ocean' : 'corporate';
            applyTheme(next);
            showToast(next === 'ocean' ? 'Zapnut motiv Ocean' : 'Zapnut motiv Corporate', 'info');
        });
    });

    document.querySelectorAll('[data-set-theme]').forEach((button) => {
        button.addEventListener('click', () => {
            const theme = button.dataset.setTheme;
            if (!theme) {
                return;
            }

            applyTheme(theme);
            showToast(`Zapnut motiv ${theme === 'ocean' ? 'Ocean' : 'Corporate'}`, 'info');
            document.querySelectorAll('[data-set-theme]').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
        });
    });
}

function setupDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (!modal) {
        return;
    }

    const text = document.getElementById('deleteModalText');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const endpoint = document.body.dataset.deleteEndpoint || 'delete.php';
    let currentId = null;

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        currentId = null;
    }

    document.querySelectorAll('.js-open-delete').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const name = btn.dataset.name;
            currentId = id;
            text.textContent = `Opravdu chcete smazat zaměstnance ${name}? Tato akce je nevrtná.`;
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    modal.querySelectorAll('[data-close-modal]').forEach((close) => {
        close.addEventListener('click', closeModal);
    });

    confirmBtn.addEventListener('click', () => {
        if (!currentId) {
            return;
        }

        setButtonLoading(confirmBtn, true);
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id: currentId }).toString(),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(data.message || 'Mazání se nezdařilo', 'error');
                }
            })
            .catch(() => {
                showToast('Došlo k chybě spojení', 'error');
            })
            .finally(() => {
                setButtonLoading(confirmBtn, false);
                closeModal();
            });
    });
}

function validateEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function validatePhone(phone) {
    const cleaned = phone.replace(/[\s\-\(\)]/g, '');
    const regex = /^\+?420?\d{9}$/;
    return regex.test(cleaned);
}

function validateSalary(salary) {
    return !Number.isNaN(Number(salary)) && Number(salary) >= 0;
}

function setupFormValidation() {
    const emailInputs = document.querySelectorAll('input[type="email"]');
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    const salaryInputs = document.querySelectorAll('input[name="salary"]');

    emailInputs.forEach((input) => {
        input.addEventListener('blur', function () {
            if (this.value && !validateEmail(this.value)) {
                this.classList.add('input-error');
            } else {
                this.classList.remove('input-error');
            }
        });
    });

    phoneInputs.forEach((input) => {
        input.addEventListener('blur', function () {
            if (this.value && !validatePhone(this.value)) {
                this.classList.add('input-error');
            } else {
                this.classList.remove('input-error');
            }
        });
    });

    salaryInputs.forEach((input) => {
        input.addEventListener('blur', function () {
            if (this.value && !validateSalary(this.value)) {
                this.classList.add('input-error');
            } else {
                this.classList.remove('input-error');
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    setupThemeToggle();
    setupDeleteModal();
    setupFormValidation();
    setupFormLoading();
    setupTableLinkLoading();
    setupMobileMenu();
    setupBulkOperations();
    setupAnimatedCounters();
    setupCounterBounce();
    setupDragAndDrop();
    setupSavedFilters();
    setupEmployeeViewToggle();
    setupRevealAnimations();
    setupSectionNav();
    setupDemoScenario();
    setupPageTransitions();
    setupVisibilityAnimations();
    setupInlineEditing();
    setupAdvancedExport();
});

/* ============================================
   MOBILE MENU TOGGLE
   ============================================ */

function setupMobileMenu() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (!toggle || !sidebar) return;

    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        if (overlay) overlay.classList.remove('active');
    }

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('mobile-open');
        if (overlay) overlay.classList.toggle('active');
    });

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Zavřít sidebar při kliknutí na odkaz v menu
    document.querySelectorAll('.sidebar .menu a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });
}

/* ============================================
   BULK OPERATIONS
   ============================================ */

function setupBulkOperations() {
    const checkboxAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    const bulkBar = document.querySelector('.bulk-actions-bar');
    const bulkCount = document.querySelector('.bulk-count');
    const bulkDeleteBtn = document.getElementById('bulkDelete');
    const bulkExportBtn = document.getElementById('bulkExport');

    if (!checkboxes.length) return;

    function updateBulkBar() {
        const selected = Array.from(checkboxes).filter((cb) => cb.checked);
        if (selected.length > 0) {
            bulkBar?.classList.add('active');
            if (bulkCount) bulkCount.textContent = `Vybráno: ${selected.length}`;
        } else {
            bulkBar?.classList.remove('active');
        }
    }

    if (checkboxAll) {
        checkboxAll.addEventListener('change', function () {
            checkboxes.forEach((cb) => (cb.checked = this.checked));
            updateBulkBar();
        });
    }

    checkboxes.forEach((cb) => {
        cb.addEventListener('change', () => {
            updateBulkBar();
            if (checkboxAll) {
                checkboxAll.checked = Array.from(checkboxes).every((c) => c.checked);
            }
        });
    });

    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', () => {
            const selected = Array.from(checkboxes)
                .filter((cb) => cb.checked)
                .map((cb) => cb.value);

            if (!confirm(`Opravdu chcete smazat ${selected.length} zaměstnanců?`)) return;

            bulkDeleteBtn.classList.add('btn-loading');
            bulkDeleteBtn.disabled = true;

            fetch('bulk-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: selected }),
            })
                .then((r) => r.json())
                .then((data) => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        showToast(data.message || 'Chyba při mazání', 'error');
                    }
                })
                .catch(() => showToast('Chyba spojení', 'error'))
                .finally(() => {
                    bulkDeleteBtn.classList.remove('btn-loading');
                    bulkDeleteBtn.disabled = false;
                });
        });
    }

    if (bulkExportBtn) {
        bulkExportBtn.addEventListener('click', () => {
            const selected = Array.from(checkboxes)
                .filter((cb) => cb.checked)
                .map((cb) => cb.value);

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export.php?export=csv';
            form.innerHTML = `<input type="hidden" name="ids" value="${selected.join(',')}">`;
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            showToast(`Export ${selected.length} záznamů zahájen`, 'success');
        });
    }
}

/* ============================================
   ANIMATED COUNTERS
   ============================================ */

function setupAnimatedCounters() {
    const counters = document.querySelectorAll('.stat-value[data-count]');

    counters.forEach((counter) => {
        const target = parseInt(counter.dataset.count, 10);
        const duration = 1200;
        const step = Math.ceil(target / (duration / 16));
        let current = 0;

        counter.classList.add('counting');

        const interval = setInterval(() => {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(interval);
                counter.classList.remove('counting');
            }
            counter.textContent = current.toLocaleString('cs-CZ');
        }, 16);
    });
}

/* ============================================
   DRAG & DROP FILE UPLOAD
   ============================================ */

function setupDragAndDrop() {
    const uploadZone = document.querySelector('.upload-zone');
    const fileInput = document.getElementById('csvFile');

    if (!uploadZone || !fileInput) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((evt) => {
        uploadZone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    ['dragenter', 'dragover'].forEach((evt) => {
        uploadZone.addEventListener(evt, () => uploadZone.classList.add('dragover'));
    });

    ['dragleave', 'drop'].forEach((evt) => {
        uploadZone.addEventListener(evt, () => uploadZone.classList.remove('dragover'));
    });

    uploadZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length) {
            fileInput.files = files;
            handleFileUpload(files[0]);
        }
    });

    uploadZone.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) {
            handleFileUpload(e.target.files[0]);
        }
    });
}

function handleFileUpload(file) {
    if (!file.name.endsWith('.csv')) {
        showToast('Prosím nahrajte CSV soubor', 'error');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const preview = document.getElementById('csvPreview');
        if (preview) {
            const lines = e.target.result.split('\n').slice(0, 5);
            preview.innerHTML = '<h4>Náhled prvních 5 řádků:</h4><pre>' + lines.join('\n') + '</pre>';
            document.getElementById('importSubmit')?.removeAttribute('disabled');
        }
    };
    reader.readAsText(file);
    showToast(`Soubor ${file.name} načten`, 'success');
}

/* ============================================
   SPARKLINES (Mini Charts)
   ============================================ */

function createSparkline(canvasId, data, color = '#334155') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    canvas.width = canvas.offsetWidth * 2;
    canvas.height = canvas.offsetHeight * 2;
    ctx.scale(2, 2);

    const width = canvas.offsetWidth;
    const height = canvas.offsetHeight;
    const max = Math.max(...data);
    const min = Math.min(...data);
    const range = max - min || 1;
    const step = width / (data.length - 1);

    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    ctx.beginPath();
    data.forEach((value, i) => {
        const x = i * step;
        const y = height - ((value - min) / range) * height * 0.8 - height * 0.1;
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
    });
    ctx.stroke();
}

/* ============================================
   NOTIFICATION SYSTEM
   ============================================ */

function checkBirthdays() {
    fetch('api/check-birthdays.php')
        .then((r) => r.json())
        .then((data) => {
            if (data.count > 0) {
                const menuItem = document.querySelector('.menu a[href*="notifications"]');
                if (menuItem) {
                    menuItem.classList.add('menu-item-badge');
                    menuItem.setAttribute('data-count', data.count);
                }
            }
        })
        .catch(() => {});
}

// Spustit kontrolu narozenin při načtení stránky
if (document.querySelector('.sidebar')) {
    checkBirthdays();
}

/* ============================================
   FILTER PRESETS
   ============================================ */

function setupSavedFilters() {
    const form = document.querySelector('.filter-form[data-filter-key]');
    const saveBtn = document.getElementById('saveFilterPreset');
    const presetsSelect = document.getElementById('savedFilterPresets');

    if (!form || !saveBtn || !presetsSelect) return;

    const storageKey = `filter-presets-${form.dataset.filterKey}`;

    function readPresets() {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || '[]');
        } catch {
            return [];
        }
    }

    function writePresets(presets) {
        localStorage.setItem(storageKey, JSON.stringify(presets));
    }

    function renderOptions() {
        const presets = readPresets();
        presetsSelect.innerHTML = '<option value="">Načíst uložený filtr</option>';
        presets.forEach((preset, idx) => {
            const option = document.createElement('option');
            option.value = String(idx);
            option.textContent = preset.name;
            presetsSelect.appendChild(option);
        });
    }

    saveBtn.addEventListener('click', () => {
        const name = window.prompt('Název filtru:');
        if (!name) return;

        const formData = new FormData(form);
        const values = {};
        formData.forEach((value, key) => {
            values[key] = value;
        });

        const presets = readPresets();
        presets.push({ name, values });
        writePresets(presets);
        renderOptions();
        showToast(`Filtr "${name}" uložen`, 'success');
    });

    presetsSelect.addEventListener('change', () => {
        const idx = parseInt(presetsSelect.value, 10);
        if (Number.isNaN(idx)) return;
        const presets = readPresets();
        const preset = presets[idx];
        if (!preset) return;

        Object.entries(preset.values).forEach(([key, value]) => {
            const input = form.elements[key];
            if (input) {
                input.value = value;
            }
        });

        form.submit();
    });

    renderOptions();
}

/* ============================================
   EMPLOYEE VIEW TOGGLE
   ============================================ */

function setupEmployeeViewToggle() {
    const wrapper = document.querySelector('[data-employee-view]');
    if (!wrapper) return;

    const tableView = document.getElementById('employeeTableView');
    const cardView = document.getElementById('employeeCardView');
    const tableBtn = document.getElementById('viewTableBtn');
    const cardsBtn = document.getElementById('viewCardsBtn');
    const storageKey = 'employee-list-view';

    function applyView(view) {
        const isCards = view === 'cards';
        tableView?.classList.toggle('view-hidden', isCards);
        cardView?.classList.toggle('view-hidden', !isCards);
        tableBtn?.classList.toggle('active', !isCards);
        cardsBtn?.classList.toggle('active', isCards);
        localStorage.setItem(storageKey, isCards ? 'cards' : 'table');
    }

    tableBtn?.addEventListener('click', () => applyView('table'));
    cardsBtn?.addEventListener('click', () => applyView('cards'));

    const current = localStorage.getItem(storageKey) || 'table';
    applyView(current);
}

/* ============================================
   REVEAL ANIMATIONS
   ============================================ */

function setupRevealAnimations() {
    const items = document.querySelectorAll('.card, .stat-card, .employee-card');
    if (!items.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('reveal-in');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    items.forEach((item, index) => {
        item.classList.add('reveal-item');
        item.style.transitionDelay = `${Math.min(index * 35, 220)}ms`;
        observer.observe(item);
    });
}

/* ============================================
   DETAIL SECTION NAV
   ============================================ */

function setupSectionNav() {
    const links = document.querySelectorAll('.section-nav a[href^="#"]');
    if (!links.length) return;

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const sections = Array.from(links)
        .map((link) => document.querySelector(link.getAttribute('href')))
        .filter(Boolean);

    const setActive = (id) => {
        links.forEach((item) => {
            const isActive = item.getAttribute('href') === `#${id}`;
            item.classList.toggle('active', isActive);
            item.setAttribute('aria-current', isActive ? 'true' : 'false');
        });
    };

    const highlightSection = (section) => {
        if (!section || prefersReducedMotion) return;
        section.classList.add('section-highlight');
        setTimeout(() => section.classList.remove('section-highlight'), 360);
    };

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const target = document.querySelector(link.getAttribute('href'));
            if (!target) return;

            target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
            setActive(target.id);
            highlightSection(target);
        });
    });

    const observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

        if (visible?.target?.id) {
            setActive(visible.target.id);
        }
    }, {
        threshold: [0.3, 0.55, 0.75],
        rootMargin: '-20% 0px -45% 0px',
    });

    sections.forEach((section) => observer.observe(section));

    const initialHash = window.location.hash.replace('#', '');
    if (initialHash) {
        setActive(initialHash);
    }
}

/* ============================================
   DEMO SCENARIO ACTION
   ============================================ */

function setupDemoScenario() {
    const button = document.getElementById('runDemoScenario');
    if (!button) return;

    button.addEventListener('click', () => {
        button.classList.add('btn-loading');
        button.disabled = true;
        window.location.href = 'demo-scenario.php';
    });
}

/* ============================================
   COUNTER BOUNCE ANIMATION
   ============================================ */

function setupCounterBounce() {
    const counters = document.querySelectorAll('.stat-value[data-count]');
    
    counters.forEach((counter) => {
        // Add bounce class after counting animation finishes
        const originalCounter = counter.textContent;
        setTimeout(() => {
            counter.classList.add('bounce');
            // Remove class after animation completes
            setTimeout(() => {
                counter.classList.remove('bounce');
            }, 600);
        }, 1200);
    });
}

/* ============================================
   PAGE TRANSITION EFFECTS
   ============================================ */

function setupPageTransitions() {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let overlay = document.querySelector('.page-transition-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'page-transition-overlay';
        document.body.appendChild(overlay);
    }

    if (!prefersReducedMotion) {
        document.body.classList.add('page-enter');
        requestAnimationFrame(() => {
            document.body.classList.add('page-enter-active');
        });

        // Ensure overlay starts hidden on page load.
        requestAnimationFrame(() => {
            overlay.classList.remove('is-visible');
        });
    }

    document.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const href = link.getAttribute('href') || '';
            const isInternal = link.origin === window.location.origin;
            const isNavigable = href.includes('.php') || href.startsWith('/') || href.startsWith('../');
            const hasModifier = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

            if (!isNavigable || !isInternal || hasModifier || event.button !== 0) return;
            if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
            if (link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-no-transition')) return;
            if (prefersReducedMotion) return;

            event.preventDefault();
            overlay.classList.add('is-visible');
            document.body.classList.add('page-leave');

            setTimeout(() => {
                window.location.href = link.href;
            }, 90);
        });
    });
}

/* ============================================
   ENHANCED PAGE VISIBILITY ANIMATIONS
   ============================================ */

function setupVisibilityAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });

    // Observe all cards and stat cards for fade-in effect
    document.querySelectorAll('.card, .stat-card, .activity-item').forEach((el) => {
        observer.observe(el);
    });
}

/* ============================================
   INLINE EDITING
   ============================================ */

function setupInlineEditing() {
    const editableCells = document.querySelectorAll('.inline-editable');
    
    editableCells.forEach(cell => {
        cell.addEventListener('click', function() {
            // Pokud už je v edit módu, ignoruj
            if (cell.classList.contains('inline-editing')) {
                return;
            }
            
            const field = cell.dataset.field;
            const employeeId = cell.dataset.employeeId;
            const currentValue = cell.dataset.value;
            const currentDisplay = cell.textContent.trim();
            
            // Přepnout do edit módu
            cell.classList.add('inline-editing');
            
            let inputHtml = '';
            
            if (field === 'salary') {
                inputHtml = `
                    <input type="number" class="inline-input" value="${currentValue}" min="0" step="1000">
                    <div class="inline-actions">
                        <button type="button" class="btn-inline-save" title="Uložit"><i class="fas fa-check"></i></button>
                        <button type="button" class="btn-inline-cancel" title="Zrušit"><i class="fas fa-times"></i></button>
                    </div>
                `;
            } else if (field === 'department') {
                const departments = {
                    'IT': 'IT',
                    'HR': 'HR',
                    'Sales': 'Prodej',
                    'Marketing': 'Marketing',
                    'Finance': 'Finance'
                };
                
                let options = '';
                for (const [value, label] of Object.entries(departments)) {
                    const selected = value === currentValue ? 'selected' : '';
                    options += `<option value="${value}" ${selected}>${label}</option>`;
                }
                
                inputHtml = `
                    <select class="inline-input">${options}</select>
                    <div class="inline-actions">
                        <button type="button" class="btn-inline-save" title="Uložit"><i class="fas fa-check"></i></button>
                        <button type="button" class="btn-inline-cancel" title="Zrušit"><i class="fas fa-times"></i></button>
                    </div>
                `;
            } else if (field === 'status') {
                const statuses = {
                    'active': 'Aktivní',
                    'on_leave': 'Na dovolené',
                    'terminated': 'Ukončen'
                };
                
                let options = '';
                for (const [value, label] of Object.entries(statuses)) {
                    const selected = value === currentValue ? 'selected' : '';
                    options += `<option value="${value}" ${selected}>${label}</option>`;
                }
                
                inputHtml = `
                    <select class="inline-input">${options}</select>
                    <div class="inline-actions">
                        <button type="button" class="btn-inline-save" title="Uložit"><i class="fas fa-check"></i></button>
                        <button type="button" class="btn-inline-cancel" title="Zrušit"><i class="fas fa-times"></i></button>
                    </div>
                `;
            }
            
            cell.innerHTML = inputHtml;
            
            const input = cell.querySelector('.inline-input');
            const saveBtn = cell.querySelector('.btn-inline-save');
            const cancelBtn = cell.querySelector('.btn-inline-cancel');
            
            // Focus na input
            input.focus();
            if (input.type === 'number') {
                input.select();
            }
            
            // Cancel handler
            function cancelEdit() {
                cell.classList.remove('inline-editing');
                cell.innerHTML = currentDisplay;
            }
            
            // Save handler
            function saveEdit() {
                const newValue = input.value;
                
                // Pokud se hodnota nezměnila, jen zrušit edit mód
                if (newValue == currentValue) {
                    cancelEdit();
                    return;
                }
                
                // Disable buttons během ukládání
                saveBtn.disabled = true;
                cancelBtn.disabled = true;
                input.disabled = true;
                
                // Odeslat na server
                fetch('inline-update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        id: employeeId,
                        field: field,
                        value: newValue
                    }).toString()
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Aktualizovat UI
                        cell.classList.remove('inline-editing');
                        cell.dataset.value = data.value;
                        cell.innerHTML = data.display_value;
                        
                        // Toast notification
                        showToast(data.message, 'success');
                    } else {
                        showToast(data.message || 'Chyba při ukládání', 'error');
                        cancelEdit();
                    }
                })
                .catch(error => {
                    console.error('Inline edit error:', error);
                    showToast('Chyba spojení se serverem', 'error');
                    cancelEdit();
                });
            }
            
            // Event listeners
            cancelBtn.addEventListener('click', cancelEdit);
            saveBtn.addEventListener('click', saveEdit);
            
            // Enter key = save, Escape = cancel
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveEdit();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelEdit();
                }
            });
        });
    });
}
