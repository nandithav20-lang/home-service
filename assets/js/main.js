// ========================================================
// Home Service App - Client JavaScript
// ========================================================

document.addEventListener('DOMContentLoaded', () => {
    // 1. Minimum date constraint for booking (Cannot select past date)
    const bookingDateInput = document.getElementById('booking_date');
    if (bookingDateInput) {
        const today = new Date().toISOString().split('T')[0];
        bookingDateInput.min = today;
    }

    // 2. Real-time Service Search & Category Filter
    const searchInput = document.getElementById('service-search');
    const categoryFilter = document.getElementById('category-filter');
    const serviceCards = document.querySelectorAll('.service-item');

    function filterServices() {
        if (!serviceCards.length) return;
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const selectedCat = categoryFilter ? categoryFilter.value : 'all';

        let visibleCount = 0;
        serviceCards.forEach(card => {
            const title = card.getAttribute('data-name')?.toLowerCase() || '';
            const desc = card.getAttribute('data-desc')?.toLowerCase() || '';
            const catId = card.getAttribute('data-category') || '';

            const matchesQuery = title.includes(query) || desc.includes(query);
            const matchesCat = (selectedCat === 'all' || catId === selectedCat);

            if (matchesQuery && matchesCat) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const noResults = document.getElementById('no-services-found');
        if (noResults) {
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterServices);
    }
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterServices);
    }

    // 3. Form Validation for Phone (10 digits) and Pincode (6 digits)
    const formsWithValidation = document.querySelectorAll('form[data-validate="true"]');
    formsWithValidation.forEach(form => {
        form.addEventListener('submit', (e) => {
            const phoneInput = form.querySelector('input[name="phone"]');
            const pincodeInput = form.querySelector('input[name="pincode"]');

            if (phoneInput && !/^\d{10}$/.test(phoneInput.value.trim())) {
                alert('Please enter a valid 10-digit Indian mobile number.');
                phoneInput.focus();
                e.preventDefault();
                return;
            }

            if (pincodeInput && !/^\d{6}$/.test(pincodeInput.value.trim())) {
                alert('Please enter a valid 6-digit postal pincode.');
                pincodeInput.focus();
                e.preventDefault();
                return;
            }
        });
    });

    // 4. Interactive Star Rating Selector
    const starInputs = document.querySelectorAll('.star-picker input[type="radio"]');
    if (starInputs.length) {
        const starLabels = document.querySelectorAll('.star-picker label');
        starLabels.forEach((label, idx) => {
            label.addEventListener('mouseover', () => {
                highlightStars(idx + 1);
            });
            label.addEventListener('mouseout', () => {
                const checked = document.querySelector('.star-picker input[type="radio"]:checked');
                highlightStars(checked ? parseInt(checked.value) : 0);
            });
        });

        function highlightStars(count) {
            starLabels.forEach((lbl, i) => {
                if (i < count) {
                    lbl.style.color = '#f59e0b';
                } else {
                    lbl.style.color = '#cbd5e1';
                }
            });
        }
    }
});

// Confirmation helper
function confirmAction(message) {
    return confirm(message || 'Are you sure you want to proceed with this action?');
}
