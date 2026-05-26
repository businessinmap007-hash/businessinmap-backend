<script>
(function () {
    function findEndInput(startInput, endClassName) {
        const targetSelector = startInput.getAttribute('data-range-target');

        if (targetSelector) {
            const target = document.querySelector(targetSelector);

            if (target) {
                return target;
            }
        }

        const wrapper =
            startInput.closest('[data-a2-date-range]') ||
            startInput.closest('.a2-filterbar') ||
            startInput.closest('.a2-form-grid') ||
            startInput.closest('form') ||
            document;

        return wrapper.querySelector(endClassName);
    }

    function wireRange(startSelector, endSelector) {
        document.querySelectorAll(startSelector).forEach(function (startInput) {
            if (startInput.dataset.a2RangeWired === '1') {
                return;
            }

            const endInput = findEndInput(startInput, endSelector);

            if (!endInput || endInput === startInput) {
                return;
            }

            startInput.dataset.a2RangeWired = '1';

            function syncRange() {
                if (startInput.value) {
                    endInput.min = startInput.value;
                } else {
                    endInput.removeAttribute('min');
                }

                if (startInput.value && endInput.value && endInput.value < startInput.value) {
                    endInput.value = startInput.value;

                    endInput.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            }

            startInput.addEventListener('change', syncRange);
            startInput.addEventListener('input', syncRange);

            syncRange();
        });
    }

    function wireDateRanges() {
        wireRange('.js-a2-date-range-start', '.js-a2-date-range-end');
    }

    function wireDateTimeRanges() {
        wireRange('.js-a2-datetime-range-start', '.js-a2-datetime-range-end');
    }

    function init() {
        wireDateRanges();
        wireDateTimeRanges();
    }

    document.addEventListener('DOMContentLoaded', init);

    document.addEventListener('a2:content-updated', init);
})();
</script>