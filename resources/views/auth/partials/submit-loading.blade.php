<script>
    (function () {
        const loadingText = @js($loadingText ?? 'در حال ارسال...');
        document.querySelectorAll('form[data-submit-loading]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.dataset.originalHtml = button.innerHTML;
                button.innerHTML = '<svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".3" stroke-width="3"></circle><path d="M22 12A10 10 0 0 0 12 2" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg><span>' + loadingText + '</span>';
            });
        });
    })();
</script>
