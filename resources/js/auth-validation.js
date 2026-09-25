const generalPhonePattern = /^\+?[0-9][0-9\s().-]{6,29}$/;
const smsPhonePattern = /^(\+98|98|0)?9[0-9]{9}$/;
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const showError = (input, message) => {
    const errorId = `${input.name}-client-error`;
    let error = input.parentElement.querySelector(`[id="${errorId}"]`);

    if (! error) {
        error = document.createElement('span');
        error.id = errorId;
        error.className = 'mt-1 text-xs font-semibold text-rose-600';
        error.setAttribute('role', 'alert');
        input.insertAdjacentElement('afterend', error);
        input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), errorId].filter(Boolean).join(' '));
    }

    error.textContent = message;
    error.style.display = message ? 'block' : 'none';
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
};

const phoneError = (value, mode) => {
    if (! value) return '';
    if (! /^[\x00-\x7F]*$/.test(value)) return 'شماره موبایل را با ارقام لاتین وارد کنید.';
    if (! (mode === 'sms' ? smsPhonePattern : generalPhonePattern).test(value)) {
        return mode === 'sms'
            ? 'شماره موبایل معتبر وارد کنید؛ مانند 09123456789.'
            : 'شماره تماس معتبر وارد کنید؛ مانند 09123456789.';
    }
    return '';
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-auth-validation]').forEach((form) => {
        const register = form.dataset.authValidation === 'register';
        const fields = Object.fromEntries([...form.querySelectorAll('input[name]')].map((input) => [input.name, input]));

        const validate = (name, submitting = false) => {
            const input = fields[name];
            if (! input) return true;

            const value = input.value.trim();
            let message = '';

            if (name === 'login') {
                if (! value) message = 'ایمیل یا شماره موبایل را وارد کنید.';
                else if (value.includes('@')) message = emailPattern.test(value) ? '' : 'ایمیل معتبر وارد کنید.';
                else message = phoneError(value, form.dataset.phoneMode);
            } else if (name === 'phone') {
                if (register && form.dataset.phoneMode === 'sms' && ! value) message = 'شماره موبایل ضروری است.';
                else if (register && submitting && ! value && ! fields.email.value.trim()) message = 'ایمیل یا شماره موبایل را وارد کنید.';
                else message = phoneError(value, form.dataset.phoneMode);
            } else if (name === 'password') {
                if (! input.value) message = 'رمز عبور را وارد کنید.';
                else if (register && Array.from(input.value).length < 8) message = 'رمز عبور باید حداقل ۸ کاراکتر داشته باشد.';
            } else if (name === 'password_confirmation') {
                if (! input.value) message = 'تکرار رمز عبور را وارد کنید.';
                else if (input.value !== fields.password.value) message = 'تکرار رمز عبور با رمز عبور یکسان نیست.';
            }

            showError(input, message);
            return ! message;
        };

        const names = register ? ['phone', 'password', 'password_confirmation'] : ['login', 'password'];
        names.forEach((name) => {
            const input = fields[name];
            input.addEventListener('blur', () => {
                input.dataset.authTouched = 'true';
                validate(name);
            });
            input.addEventListener('input', () => {
                if (input.dataset.authTouched) validate(name);
                if (name === 'password' && fields.password_confirmation?.dataset.authTouched) validate('password_confirmation');
            });
        });

        if (register) {
            fields.email.addEventListener('input', () => {
                if (fields.phone.dataset.authTouched) validate('phone');
            });
        }

        form.addEventListener('submit', (event) => {
            const invalid = names.filter((name) => ! validate(name, true));
            if (invalid.length) {
                event.preventDefault();
                fields[invalid[0]].focus();
            }
        }, { capture: true });
    });
});
