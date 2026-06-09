/**
 * WhatsApp Phone Input Component
 * 
 * Usage:
 *   <input id="phone_number" name="phone_number" type="tel" class="wa-phone-input" required>
 *   <script src="/assets/js/wa-phone-input.js"></script>
 * 
 * Behaviour:
 *   - Prefixes field with "+60" on focus if empty
 *   - Allows only "+", digits, dashes, spaces
 *   - On submit, normalizes to 60XXXXXXXXX (strips +, spaces, dashes)
 *   - Validates Malaysian mobile: 601x-XXXXXXXX (10–11 digits total after 60)
 */
(function () {
    'use strict';

    const COUNTRY_CODE = '+60';

    function stripNonDigits(v) {
        return v.replace(/[^0-9]/g, '');
    }

    function normalize(raw) {
        let digits = stripNonDigits(raw);
        // Already full international: 60XXXXXXXXX
        if (digits.startsWith('60')) return digits;
        // Local 0x → 60x
        if (digits.startsWith('0')) return '6' + digits;
        // Bare digits (user removed country code)
        return '60' + digits;
    }

    function isValid(digits) {
        // Must be 60 + 8~11 digits = 10~13 total
        return /^60[0-9]{8,11}$/.test(digits);
    }

    function formatDisplay(digits) {
        // digits: 60XXXXXXXXX
        if (!digits.startsWith('60')) return '+' + digits;
        const local = digits.slice(2);            // strip 60
        if (local.length <= 9) {
            // 9 digits: XXX-XXX XXXX
            return '+60 ' + local.slice(0, 2) + '-' + local.slice(2, 5) + ' ' + local.slice(5);
        }
        // 10 digits: XXXX-XXX XXXX
        return '+60 ' + local.slice(0, 4) + '-' + local.slice(4, 7) + ' ' + local.slice(7);
    }

    function initField(input) {
        // Set placeholder
        input.placeholder = COUNTRY_CODE + ' 12-345 6789';

        // Show helper text
        const helper = document.createElement('div');
        helper.className = 'wa-phone-helper text-[11px] mt-1 hidden';
        input.parentNode.appendChild(helper);

        function showHelper(msg, isError) {
            helper.textContent = msg;
            helper.className = 'wa-phone-helper text-[11px] mt-1 ' + (isError ? 'text-red-500' : 'text-green-600');
        }
        function hideHelper() {
            helper.className = 'wa-phone-helper text-[11px] mt-1 hidden';
        }

        // On focus: prefill +60 if empty
        input.addEventListener('focus', function () {
            if (!this.value.trim()) {
                this.value = COUNTRY_CODE;
            }
            this.setSelectionRange(this.value.length, this.value.length);
        });

        // On input: restrict characters and format
        input.addEventListener('input', function () {
            let raw = this.value;

            // Allow only +, digits, dashes, spaces
            raw = raw.replace(/[^0-9+\- ]/g, '');

            // Ensure + only at start
            if (raw.indexOf('+') > 0) {
                raw = raw.replace(/\+/g, '');
                raw = '+' + raw;
            }

            this.value = raw;

            const digits = normalize(raw);
            if (digits.length < 10) {
                hideHelper();
            } else if (!isValid(digits)) {
                showHelper('⚠ Invalid Malaysian number format', true);
            } else {
                showHelper('✓ Valid: ' + formatDisplay(digits), false);
            }
        });

        // On blur: format display nicely
        input.addEventListener('blur', function () {
            if (!this.value || this.value === COUNTRY_CODE) {
                this.value = '';
                hideHelper();
                return;
            }
            const digits = normalize(this.value);
            if (isValid(digits)) {
                this.value = formatDisplay(digits);
            }
        });

        // Before form submit: normalize to clean digits (60XXXXXXXXX)
        const form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                const digits = normalize(input.value);
                if (!isValid(digits)) {
                    e.preventDefault();
                    showHelper('⚠ Please enter a valid Malaysian WhatsApp number', true);
                    input.focus();
                    return false;
                }
                // Store normalized digits for server processing
                input.value = digits;
            }, true); // capture phase so we run before other submit handlers
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Init all wa-phone-input fields
        document.querySelectorAll('.wa-phone-input, [data-wa-phone]').forEach(initField);

        // Auto-detect common phone fields by name
        document.querySelectorAll('input[name="phone_number"][type="tel"]').forEach(function (el) {
            if (!el.classList.contains('wa-phone-input')) {
                initField(el);
            }
        });
    });
})();
