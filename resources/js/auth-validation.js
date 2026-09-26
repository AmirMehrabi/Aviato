const generalPhonePattern = /^\+?[0-9][0-9\s().-]{6,29}$/;
const smsPhonePattern = /^(\+98|98|0)?9[0-9]{9}$/;
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const showError = (input, message) => {
    const errorId = `${input.name}-client-error`;
    const field = input.closest('[data-auth-field]') ?? input.parentElement;
    let error = field.querySelector(`[id="${errorId}"]`);

    if (! error && message) {
        error = document.createElement('span');
        error.id = errorId;
        error.className = 'mt-1 block text-xs font-semibold text-rose-600';
        error.setAttribute('role', 'alert');
        field.append(error);
        input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), errorId].filter(Boolean).join(' '));
    }

    if (error) {
        error.textContent = message;
        error.hidden = ! message;
        error.style.display = message ? '' : 'none';
    }
    if (message) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
};

const clearServerError = (input) => {
    const error = input.closest('[data-auth-field]')?.querySelector(`#${input.name}-server-error`);
    if (error) {
        error.hidden = true;
        error.style.display = 'none';
    }
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
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (! input) return;

        button.addEventListener('click', () => {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.textContent = visible ? 'پنهان' : 'نمایش';
            button.setAttribute('aria-label', `${visible ? 'پنهان کردن' : 'نمایش'} ${input.name === 'password_confirmation' ? 'تکرار رمز عبور' : 'رمز عبور'}`);
            button.setAttribute('aria-pressed', String(visible));
        });
    });

    document.querySelectorAll('form[data-auth-validation]').forEach((form) => {
        const register = form.dataset.authValidation === 'register';
        const fields = Object.fromEntries([...form.querySelectorAll('input[name]')].map((input) => [input.name, input]));

        const validate = (name, submitting = false) => {
            const input = fields[name];
            if (! input) return true;

            const value = input.value.trim();
            let message = '';

            if (name === 'first_name' || name === 'last_name') {
                if (! value) message = name === 'first_name' ? 'نام را وارد کنید.' : 'نام خانوادگی را وارد کنید.';
                else if (value.length > 255) message = 'این فیلد باید حداکثر ۲۵۵ کاراکتر داشته باشد.';
            } else if (name === 'email') {
                if (! value && input.required) message = 'ایمیل را وارد کنید.';
                else if (! value && submitting && ! fields.phone.value.trim() && ! fields.phone.required) message = 'ایمیل یا شماره موبایل را وارد کنید.';
                else if (value && ! emailPattern.test(value)) message = 'ایمیل معتبر وارد کنید.';
            } else if (name === 'login') {
                if (! value) message = 'ایمیل یا شماره موبایل را وارد کنید.';
                else if (value.includes('@')) message = emailPattern.test(value) ? '' : 'ایمیل معتبر وارد کنید.';
                else message = phoneError(value, form.dataset.phoneMode);
            } else if (name === 'phone') {
                if (register && input.required && ! value) message = 'شماره موبایل را وارد کنید.';
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

        const names = register
            ? ['first_name', 'last_name', 'email', 'phone', 'password', 'password_confirmation']
            : ['login', 'password'];
        names.forEach((name) => {
            const input = fields[name];
            input.addEventListener('blur', () => {
                input.dataset.authTouched = 'true';
                if (input.closest('[data-auth-field]')?.querySelector(`#${name}-server-error:not([hidden])`)) return;
                validate(name, form.dataset.authSubmitted === 'true');
            });
            input.addEventListener('input', () => {
                clearServerError(input);
                if (input.dataset.authTouched || form.dataset.authSubmitted) validate(name, form.dataset.authSubmitted === 'true');
                if (name === 'password' && (fields.password_confirmation?.dataset.authTouched || form.dataset.authSubmitted)) validate('password_confirmation');
                if (register && (name === 'email' || name === 'phone')) {
                    const other = name === 'email' ? fields.phone : fields.email;
                    if (other.dataset.authTouched || form.dataset.authSubmitted) validate(other.name, form.dataset.authSubmitted === 'true');
                }
            });
        });

        form.addEventListener('submit', (event) => {
            form.dataset.authSubmitted = 'true';
            names.forEach((name) => clearServerError(fields[name]));
            const invalid = names.filter((name) => ! validate(name, true));
            if (invalid.length) {
                event.preventDefault();
                fields[invalid[0]].focus();
            }
        }, { capture: true });

        form.querySelector('input[aria-invalid="true"]')?.focus({ preventScroll: true });
    });
});
