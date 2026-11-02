export function corporateApplication(stage, role) {
    return {
        stage,
        role,
        review: [],

        next() {
            const form = this.$refs.application;
            if (!form.reportValidity()) return;

            this.review = Array.from(form.querySelectorAll('[data-review]')).map((field) => ({
                label: field.dataset.review,
                value: field.tagName === 'SELECT'
                    ? field.options[field.selectedIndex].text
                    : (field.value || '—'),
            }));

            for (const field of form.querySelectorAll('input[type=file]:not(:disabled)')) {
                const container = field.closest('[data-document]');
                this.review.push({
                    label: container.dataset.document,
                    value: field.files[0]?.name || container.querySelector('a')?.textContent || '—',
                });
            }

            this.stage = 3;
            this.$nextTick(() => this.$refs.reviewHeading.focus());
        },
    };
}
