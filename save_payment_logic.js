document.addEventListener('DOMContentLoaded', function () {
    const saveBtn = document.querySelector('.btn-save-modal');

    if (saveBtn) {
        saveBtn.addEventListener('click', savePaymentData);
    }

    function savePaymentData() {
        const data = {};
        let sectionName = '';
        let isValid = false;
        let hasValues = false; // Flag to track if any input has a value

        function collectData(sectionClass, type) {
            const section = document.querySelector(sectionClass);
            // Check if section is visible (style.display is set to 'block' by the toggle logic)
            if (section && section.style.display === 'block') {
                sectionName = type;
                data.payment_type = type;
                isValid = true;

                // Select all inputs and selects
                const inputs = section.querySelectorAll('input, select');

                inputs.forEach(input => {
                    // Skip hidden inputs
                    if (input.type === 'hidden') return;

                    let key = input.id;

                    // Map technical IDs to standard report keys
                    if (key === 'ccTerminalIssuer' || key === 'dcTerminalIssuer') key = 'Terminal Issuer';
                    else if (key === 'ccTerminalId' || key === 'dcTerminalId') key = 'Terminal ID';
                    else if (key === 'creditCardBankDropdown') key = 'Bank';
                    else if (key === 'creditCardTermsDropdown') key = 'Terms';

                    // Try to derive key from label
                    if (!key) {
                        const formGroup = input.closest('.hc-form-group');
                        if (formGroup) {
                            const label = formGroup.querySelector('label');
                            if (label) {
                                key = label.innerText.replace(':', '').trim();
                            }
                        }

                        // Fallback for specialized rows like Enter Amount
                        if (!key) {
                            const parentRow = input.closest('.enter-amount-row');
                            if (parentRow) {
                                const label = parentRow.querySelector('label');
                                if (label) key = label.innerText.replace(':', '').trim();
                            }
                        }
                    }

                    // Fallback to name or class
                    if (!key && input.name) key = input.name;
                    if (!key && input.className) key = input.className;

                    if (!key) return; // Skip if no key found

                    // Handle Checkboxes and Radios
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        if (input.checked) {
                            hasValues = true; // Checked item means user managed input
                            // Special handling for payment method radios which are structural
                            if (input.name === 'payment_method') return;

                            if (data[key]) {
                                data[key] += ', ' + input.value;
                            } else {
                                data[key] = input.value;
                            }
                        }
                    } else {
                        // Text, Number, Select
                        data[key] = input.value;
                        if (input.value && input.value.trim() !== '') {
                            // Ignore default select options like "Select Bank" if they have empty value
                            hasValues = true;
                        }
                    }
                });

                // Specifically capture Total as it might be outside the form groups loop depending on structure
                const totalInput = section.querySelector('.total-input');
                if (totalInput) {
                    data['Total'] = totalInput.value;
                    if (totalInput.value && totalInput.value.trim() !== '') hasValues = true;
                }

                return true;
            }
            return false;
        }

        // Check each section visibility
        const sections = [
            { class: '.home-credit-section', name: 'STO ninio de cebu' },
            { class: '.salmon-section', name: 'Salmon' },
            { class: '.samsung-finance-section', name: 'Samsung Finance' },
            { class: '.payjoy-section', name: 'PayJoy' },
            { class: '.billease-section', name: 'Billease' },
            { class: '.paymongo-section', name: 'PayMongo' },
            { class: '.skyro-section', name: 'Skyro' },
            { class: '.credit-card-section', name: 'Credit Card' },
            { class: '.debit-card-section', name: 'Debit Card' },
            { class: '.qr-ph-section', name: 'QR PH' },
            { class: '.starpay-qr-section', name: 'Starpay QR' },
            { class: '.ewallet-section', name: 'E-Wallet' },
            { class: '.online-banking-section', name: 'Online Banking' },
            { class: '.cash-section', name: 'Cash' }
        ];

        for (const sec of sections) {
            if (collectData(sec.class, sec.name)) {
                break; // Stop after finding the visible section
            }
        }

        if (isValid) {
            if (!hasValues) {
                alert('Please fill in the payment details before saving.');
                return;
            }

            console.log('Saving Payment Data:', data);
            const hiddenInput = document.getElementById('payment_data');
            if (hiddenInput) {
                hiddenInput.value = JSON.stringify(data);

                // Update Payment Button in Main Form
                const btnPayment = document.querySelector('.btn-payment');
                if (btnPayment) {
                    btnPayment.innerText = `Payment: ${sectionName}`;
                    btnPayment.style.backgroundColor = '#2E7D32'; // Success Green
                    btnPayment.style.color = 'white';
                }

                alert('Payment details saved successfully!');
                closePaymentModal();
            } else {
                console.error('Hidden input #payment_data not found!');
            }
        } else {
            alert('Please select a payment method.');
        }
    }
});
