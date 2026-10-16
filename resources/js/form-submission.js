export function customerFormSubmission() {
    return {
        submitting: false,

        beginSubmission(event, allowed = true) {
            // Native validation and the ticket editor must accept the form first.
            if (event.defaultPrevented) return false;

            if (this.submitting || !allowed) {
                event.preventDefault();
                return false;
            }

            // Lock synchronously, before Alpine updates the disabled button.
            this.submitting = true;
            return true;
        },

        resetSubmission() {
            this.submitting = false;
        },
    };
}
