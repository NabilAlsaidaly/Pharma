const shell = document.querySelector("[data-owner-shell]");
if (shell) {
    const sidebar = shell.querySelector("#owner-sidebar");
    const openButton = shell.querySelector("[data-open-sidebar]");
    const closeButton = shell.querySelector("[data-close-sidebar]");
    const overlay = shell.querySelector("[data-sidebar-overlay]");
    const desktop = window.matchMedia("(min-width: 1024px)");
    const content = shell.querySelector(":scope > div");
    let expanded = false;
    document.documentElement.dataset.ownerEnhanced = "true";
    openButton.classList.remove("hidden");
    closeButton.classList.remove("hidden");
    const setOpen = (open, restore = false) => {
        expanded = open && !desktop.matches;
        sidebar.hidden = !desktop.matches && !expanded;
        overlay.hidden = !expanded;
        openButton.setAttribute("aria-expanded", String(expanded));
        document.body.style.overflow = expanded ? "hidden" : "";
        content.inert = expanded;
        if (expanded) {
            sidebar.setAttribute("role", "dialog");
            sidebar.setAttribute("aria-modal", "true");
        } else {
            sidebar.removeAttribute("role");
            sidebar.removeAttribute("aria-modal");
        }
        if (expanded) closeButton.focus();
        else if (restore && !desktop.matches) openButton.focus();
    };
    openButton.addEventListener("click", () => setOpen(!expanded, true));
    closeButton.addEventListener("click", () => setOpen(false, true));
    overlay.addEventListener("click", () => setOpen(false, true));
    desktop.addEventListener("change", () => setOpen(false));
    document.addEventListener("keydown", (event) => {
        if (!expanded) return;
        if (event.key === "Escape") {
            event.preventDefault();
            setOpen(false, true);
        }
        if (event.key === "Tab") {
            const controls = [
                ...sidebar.querySelectorAll("a[href],button:not([disabled])"),
            ];
            const first = controls[0];
            const last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    setOpen(false);
}
document.querySelectorAll("[data-selection]").forEach((table) => {
    const rows = [...table.querySelectorAll("[data-select-row]")];
    const all = table.querySelector("[data-select-page]");
    const clear = table.querySelector("[data-clear-selection]");
    const count = table.querySelector("[data-selected-count]");
    const bulk = document.getElementById("catalog-bulk") ?? document.querySelector("[data-review-bulk]");
    const scope = bulk?.querySelector('[name="scope"]');
    const refresh = () => {
        const selected = rows.filter((row) => row.checked).length;
        count.textContent = selected
            ? `تم تحديد ${selected} سجل من هذه الصفحة`
            : "لم يتم تحديد سجلات";
        clear.disabled = selected === 0;
        if (scope?.value === "matching") {
            count.textContent = `تم تحديد ${bulk.dataset.matchingCount} سجل مطابق عبر الصفحات`;
            clear.disabled = false;
        }
        if (all) {
            all.checked = selected > 0 && selected === rows.length;
            all.indeterminate = selected > 0 && selected < rows.length;
        }
    };
    scope?.addEventListener("change", refresh);
    rows.forEach((row) => row.addEventListener("change", () => {
        if (scope) scope.value = "ids";
        refresh();
    }));
    all?.addEventListener("change", () => {
        if (scope) scope.value = "ids";
        rows.forEach((row) => {
            row.checked = all.checked;
        });
        refresh();
    });
    clear.addEventListener("click", () => {
        if (scope) scope.value = "ids";
        rows.forEach((row) => {
            row.checked = false;
        });
        refresh();
    });
    // Never retain selection across pages, history restoration or browser storage.
    window.addEventListener("pageshow", () => {
        if (scope) scope.value = "ids";
        rows.forEach((row) => {
            row.checked = false;
        });
        refresh();
    });
    refresh();
});
document.querySelectorAll("[data-date-filter]").forEach((form) => {
    const period = form.querySelector("[data-period]");
    const refresh = () =>
        form.querySelectorAll("[data-custom-date]").forEach((input) => {
            input.disabled = period.value !== "custom";
            input.required = period.value === "custom";
        });
    period.addEventListener("change", refresh);
    refresh();
});
document.querySelectorAll("[data-search-form]").forEach((form) => {
    const progress = form.querySelector("[data-search-progress]");
    form.addEventListener("submit", () => {
        form.setAttribute("aria-busy", "true");
        if (progress) progress.textContent = "جارٍ البحث…";
    });
    window.addEventListener("pageshow", () => {
        form.removeAttribute("aria-busy");
        if (progress) progress.textContent = "";
    });
});
