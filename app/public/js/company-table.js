document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('company-filters');
    const searchInput = document.getElementById('company-search');
    const perPageSelect = document.getElementById('per-page');

    if (!filterForm || !searchInput || !perPageSelect) {
        return;
    }

    const pageInput = filterForm.querySelector('input[name="page"]');

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (pageInput) {
                pageInput.value = '1';
            }
            filterForm.requestSubmit();
        }
    });

    perPageSelect.addEventListener('change', () => {
        if (pageInput) {
            pageInput.value = '1';
        }
        filterForm.submit();
    });
});
