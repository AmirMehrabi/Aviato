import assert from 'node:assert/strict';
import { test } from 'node:test';
import { customerFormSubmission } from '../../resources/js/form-submission.js';
import { walletTopUp } from '../../resources/js/wallet-top-up.js';

function submissionEvent() {
    return {
        defaultPrevented: false,
        preventDefault() { this.defaultPrevented = true; },
    };
}

test('the first submission proceeds and rapid repeat submissions are blocked before rendering', () => {
    const form = customerFormSubmission();
    const first = submissionEvent();
    assert.equal(form.beginSubmission(first), true);
    assert.equal(first.defaultPrevented, false);
    for (let attempt = 0; attempt < 5; attempt++) {
        const repeated = submissionEvent();
        assert.equal(form.beginSubmission(repeated), false);
        assert.equal(repeated.defaultPrevented, true);
    }
    assert.equal(form.submitting, true);
});

test('a validation failure leaves the form unlocked so corrected input can be submitted', () => {
    const form = customerFormSubmission();
    const invalid = submissionEvent();
    assert.equal(form.beginSubmission(invalid, false), false);
    assert.equal(invalid.defaultPrevented, true);
    assert.equal(form.submitting, false);
    assert.equal(form.beginSubmission(submissionEvent(), true), true);
});

test('submission canceled by the ticket editor does not lock the form', () => {
    const form = customerFormSubmission();
    const invalidBody = submissionEvent();
    invalidBody.preventDefault();
    assert.equal(form.beginSubmission(invalidBody), false);
    assert.equal(form.submitting, false);
    assert.equal(form.beginSubmission(submissionEvent()), true);
});

test('restoring the page unlocks its form without clearing input', () => {
    const form = { ...customerFormSubmission(), subject: 'Keep this ticket subject' };
    form.beginSubmission(submissionEvent());
    form.resetSubmission();
    assert.equal(form.submitting, false);
    assert.equal(form.subject, 'Keep this ticket subject');
    assert.equal(form.beginSubmission(submissionEvent()), true);
});

test('independent forms do not share a submission lock', () => {
    const first = customerFormSubmission();
    const second = customerFormSubmission();
    first.beginSubmission(submissionEvent());
    assert.equal(second.beginSubmission(submissionEvent()), true);
});

test('wallet top-up preserves its validation and allows only one payment submission', () => {
    const wallet = walletTopUp({ initialGateway: 'mellat' });
    const invalidAmount = submissionEvent();
    wallet.submitPayment(invalidAmount);
    assert.equal(invalidAmount.defaultPrevented, true);
    assert.equal(wallet.submitting, false);
    wallet.amount = '250000';
    const payment = submissionEvent();
    wallet.submitPayment(payment);
    assert.equal(payment.defaultPrevented, false);
    assert.equal(wallet.submitting, true);
    assert.equal(wallet.canSubmit, false);
    assert.equal(wallet.submitLabel, 'در حال انتقال به درگاه…');
    const repeated = submissionEvent();
    wallet.submitPayment(repeated);
    assert.equal(repeated.defaultPrevented, true);
    wallet.resetSubmission();
    assert.equal(wallet.canSubmit, true);
    assert.equal(wallet.amount, '250000');
});

test('gift redemption shares the wallet submission lock and can recover after page restoration', () => {
    const wallet = walletTopUp({});
    wallet.code = 'GIFT';
    wallet.checkedCode = 'GIFT';
    wallet.promotion = { requires_payment: false };
    const gift = submissionEvent();
    wallet.submitGift(gift);
    assert.equal(gift.defaultPrevented, false);
    assert.equal(wallet.canRedeem, false);
    const repeated = submissionEvent();
    wallet.submitGift(repeated);
    assert.equal(repeated.defaultPrevented, true);
    wallet.resetSubmission();
    assert.equal(wallet.canRedeem, true);
});
