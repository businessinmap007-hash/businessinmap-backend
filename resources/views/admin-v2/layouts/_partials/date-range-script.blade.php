<script>
(function () {

    function wireDateRanges() {
        document.querySelectorAll('.js-a2-date-range-start').forEach(function (startInput) {

            const wrapper = startInput.closest('.a2-form-grid') || document;
            const endInput = wrapper.querySelector('.js-a2-date-range-end');
            if (!endInput) return;

            function syncDateRange() {

                if (startInput.value) {
                    endInput.min = startInput.value;
                } else {
                    endInput.removeAttribute('min');
                }

                if (startInput.value && endInput.value && endInput.value < startInput.value) {
                    endInput.value = startInput.value;
                }

            }

            startInput.addEventListener('change', syncDateRange);
            syncDateRange();

        });
    }

    function wireDateTimeRanges() {
        document.querySelectorAll('.js-a2-datetime-range-start').forEach(function (startInput) {

            const wrapper = startInput.closest('.a2-form-grid') || document;
            const endInput = wrapper.querySelector('.js-a2-datetime-range-end');
            if (!endInput) return;

            function syncDateTimeRange() {

                if (startInput.value) {
                    endInput.min = startInput.value;
                } else {
                    endInput.removeAttribute('min');
                }

                if (startInput.value && endInput.value && endInput.value < startInput.value) {
                    endInput.value = startInput.value;
                }

            }

            startInput.addEventListener('change', syncDateTimeRange);
            syncDateTimeRange();

        });
    }

    document.addEventListener('DOMContentLoaded', function () {

        wireDateRanges();
        wireDateTimeRanges();

    });

})();
</script>