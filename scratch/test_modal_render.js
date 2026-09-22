function extractNumericFromMixed(raw) {
    if (!raw) return 0;
    const str = String(raw);
    const parts = str.split('|');
    for (let part of parts) {
        part = part.trim().replace(/,/g, '');
        if (!isNaN(part) && parseFloat(part) > 0) {
            return parseFloat(part);
        }
    }
    return 0;
}

function formatDisplayText(text) {
    if (!text || text === 'N/A') return text;
    let formatted = String(text)
        .replace(/_/g, ' ')
        .split(' ')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
    formatted = formatted.replace(/(\d+)(months?)/gi, '$1 $2');
    formatted = formatted.replace(/\s(months?)/gi, ' Months');
    return formatted;
}

function renderPaymentInfo(sale, paymentMethodFilter = null) {
    let paymentData = {};
    try {
        paymentData = sale.payment_data ? JSON.parse(sale.payment_data) : {};
    } catch (e) {
        console.error('Error parsing payment data:', e);
    }

    let paymentMethod = paymentData.payment_type || 'N/A';
    if (paymentData['E-Wallet-Text'] && paymentMethod.includes('E-Wallet')) {
        paymentMethod = paymentMethod.replace('E-Wallet', paymentData['E-Wallet-Text']);
    }
    if (paymentData['Bank-Text'] && paymentMethod.includes('Online Banking')) {
        paymentMethod = paymentMethod.replace('Online Banking', paymentData['Bank-Text']);
    }

    // Standardize display: replace ' + ' with ' & '
    const pmParts = paymentMethod.split(/\s*[+&]\s*/).map(s => s.trim()).filter(s => s);
    let displayMethod = paymentMethod;
    if (pmParts.length === 2) {
        displayMethod = pmParts[0] + ' & ' + pmParts[1];
    } else if (pmParts.length > 2) {
        displayMethod = pmParts.slice(0, -1).join(', ') + ', & ' + pmParts[pmParts.length - 1];
    }

    let paymentInfoHTML = '';
    const hasMultiplePayments = paymentMethod.includes('+') || paymentMethod.includes('&') || displayMethod.includes('&') || (paymentData.payment_type && paymentData.payment_type.includes('+'));

    if (hasMultiplePayments && (!paymentMethodFilter || paymentMethodFilter === displayMethod || paymentMethodFilter.includes('+') || paymentMethodFilter.includes('&'))) {
        paymentInfoHTML = `<tr><td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">Payment Method:</td><td style="padding:5px 0;">${displayMethod}</td></tr>`;
        
        let totalPayment = 0;
        const paymentTypes = pmParts;
        
        let loanAlreadyProcessed = false;
        paymentTypes.forEach((payType, typeIndex) => {
            // PAYMENT PARTNERS
            if ((payType.includes('STO') || payType.includes('Home Credit')) && !loanAlreadyProcessed) {
                const loanType = formatDisplayText(paymentData['Loan Type']) || 'N/A';
                const loanTerms = formatDisplayText(paymentData['Loan Terms']) || 'N/A';
                const customerName = paymentData["Customer's Name"] || paymentData["Customers Name"] || 'N/A';
                const loanNumber = paymentData['Loan Number'] || 'N/A';
                const loanBalance = extractNumericFromMixed(paymentData['Loan Balance'] || 0);
                
                if (loanBalance > 0) {
                    loanAlreadyProcessed = true;
                    paymentInfoHTML += `
                        <tr><td colspan="2" style="padding:10px 10px 5px 0; font-weight:600; font-size:15px; color:#0e7725; border-top:1px solid #ddd;">${payType}:</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Loan Type:</td><td style="padding:5px 0;">${loanType}</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Loan Terms:</td><td style="padding:5px 0;">${loanTerms}</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Customer's Name:</td><td style="padding:5px 0;">${customerName}</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Loan Number:</td><td style="padding:5px 0;">${loanNumber}</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Loan Balance:</td><td style="padding:5px 0;">₱${loanBalance.toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                    `;
                    totalPayment += loanBalance;
                }
            }
            // E-WALLET (GCash, Maya, PayMaya, etc)
            else if (payType.includes('GCash') || payType.includes('Maya') || payType.includes('PayMaya') || payType.includes('E-Wallet')) {
                const customerName = paymentData["Customer's Name"] || paymentData["Customers Name"] || 'N/A';
                const referenceNo = paymentData['Reference No'] || 'N/A';
                
                let ewalletAmount = 0;
                if (paymentData['Amount']) {
                    const amountStr = String(paymentData['Amount']);
                    const amounts = amountStr.split('|').map(a => a.trim().replace(/,/g, ''));
                    if (amounts.length > typeIndex && !isNaN(amounts[typeIndex])) {
                        ewalletAmount = parseFloat(amounts[typeIndex]);
                    } else if (amounts.length > 0 && !isNaN(amounts[0])) {
                        ewalletAmount = parseFloat(amounts[0]);
                    }
                }
                if (ewalletAmount === 0) {
                    ewalletAmount = extractNumericFromMixed(paymentData['gcash_amount'] || paymentData['maya_amount'] || paymentData['Total'] || 0);
                }
                
                if (ewalletAmount > 0) {
                    paymentInfoHTML += `
                        <tr><td colspan="2" style="padding:10px 10px 5px 0; font-weight:600; font-size:15px; color:#0e7725; border-top:1px solid #ddd;">${payType}:</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Customer's Name:</td><td style="padding:5px 0;">${customerName}</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Reference No:</td><td style="padding:5px 0;">${referenceNo}</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Amount:</td><td style="padding:5px 0;">₱${ewalletAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                    `;
                    totalPayment += ewalletAmount;
                }
            }
            // ONLINE BANKING
            else if (payType.includes('BDO') || payType.includes('Metrobank') || payType.includes('PNB') || 
                     payType.includes('E-West') || payType.includes('RCBC') || payType.includes('Online Banking')) {
                const referenceNo = paymentData['Reference No'] || 'N/A';
                let obAmount = 0;
                if (paymentData['Amount']) {
                    const amountStr = String(paymentData['Amount']);
                    const amounts = amountStr.split('|').map(a => a.trim().replace(/,/g, ''));
                    if (amounts.length > typeIndex && !isNaN(amounts[typeIndex])) {
                        obAmount = parseFloat(amounts[typeIndex]);
                    } else if (amounts.length > 0 && !isNaN(amounts[0])) {
                        obAmount = parseFloat(amounts[0]);
                    }
                }
                if (obAmount === 0) {
                    obAmount = extractNumericFromMixed(paymentData['bank_amount'] || paymentData['Amount'] || 0);
                }
                
                if (obAmount > 0) {
                    paymentInfoHTML += `
                        <tr><td colspan="2" style="padding:10px 10px 5px 0; font-weight:600; font-size:15px; color:#0e7725; border-top:1px solid #ddd;">${payType}:</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Reference No:</td><td style="padding:5px 0;">${referenceNo}</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Amount:</td><td style="padding:5px 0;">₱${obAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                    `;
                    totalPayment += obAmount;
                }
            }
            // CASH
            else if (payType.includes('Cash')) {
                let cashAmount = 0;
                if (paymentData['cash_amount']) {
                    cashAmount = extractNumericFromMixed(paymentData['cash_amount']);
                } else if (paymentData['Cash Amount']) {
                    cashAmount = extractNumericFromMixed(paymentData['Cash Amount']);
                } else if (paymentData['Amount']) {
                    const amountStr = String(paymentData['Amount']);
                    const amounts = amountStr.split('|').map(a => a.trim().replace(/,/g, ''));
                    if (amounts.length > 1 && !isNaN(amounts[amounts.length - 1])) {
                        cashAmount = parseFloat(amounts[amounts.length - 1]);
                    } else if (amounts.length === 1 && !isNaN(amounts[0])) {
                        cashAmount = parseFloat(amounts[0]);
                    }
                }
                if (cashAmount === 0 && paymentData['Total']) {
                    const total = extractNumericFromMixed(paymentData['Total']);
                    cashAmount = total - totalPayment;
                }
                
                if (cashAmount > 0) {
                    paymentInfoHTML += `
                        <tr><td colspan="2" style="padding:10px 10px 5px 0; font-weight:600; font-size:15px; color:#0e7725; border-top:1px solid #ddd;">Cash:</td></tr>
                        <tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Amount:</td><td style="padding:5px 0;">₱${cashAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                    `;
                    totalPayment += cashAmount;
                }
            }
        });

        paymentInfoHTML += `<tr style="border-top:2px solid #acacacff;"><td style="padding:10px 10px 5px 0; font-weight:700; font-size:16px;">Total Payment:</td><td style="padding:10px 0 5px 0; font-weight:700; font-size:16px; color:#0e7725;">₱${totalPayment.toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>`;
    }

    return paymentInfoHTML;
}

const invoice0175 = {
    payment_data: '{"payment_type":"E-Wallet + Cash","Unit":"HONDA CLICK 160 WHITE (2D4124D124D1), HONDA CLICK 160 WHITE (2D4124D124D1)","E-Wallet":"gcash","E-Wallet-Text":"GCash","Customer\'s Name":"Test2","Reference No":"123456789","Amount":"16,900.00 | 100,000.00","total-input":"","Total":"116,900.00","unit_payment_map":{"HONDA CLICK 160 WHITE (2D4124D124D1)":"Cash"}}'
};

const invoice0176 = {
    payment_data: '{"payment_type":"Online Banking + Cash","Unit":"HONDA CLICK 160 WHITE (24D124D1241D24D1), HONDA CLICK 160 WHITE (24D124D1241D24D1)","Bank":"BDO","Bank-Text":"BDO","Reference No":"222222222","Amount":"100,000 | 16,900","total-input":"","Total":"116,900.00","unit_payment_map":{"HONDA CLICK 160 WHITE (24D124D1241D24D1)":"Cash"}}'
};

console.log("=== INVOICE 0175 ===");
console.log(renderPaymentInfo(invoice0175));

console.log("\n=== INVOICE 0176 ===");
console.log(renderPaymentInfo(invoice0176));
