const data = {
    sale: {
        id: 300,
        invoice_no: '0122',
        original_invoice_no: '0121',
        total_amount: '2695.00',
        discount: '1295.00',
        old_unit_amount: '1295.00',
        upgrade: 'UPGD',
        page_type: 'upgradeunit',
        payment_data: JSON.stringify({
            payment_type: "Cash",
            Unit: "HIFUTURE FLEXCLIP OPEN EARPHONE GOLD (TESTTTTT1111111)",
            Amount: "1,400.00",
            Total: "1,400.00"
        })
    },
    items: [
        {
            item_code: 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD',
            item_description: 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD',
            imei: 'TESTTTTT1111111',
            quantity: 1,
            price: 2695.00
        }
    ]
};

function formatPaymentMethodName(paymentData) {
    if (!paymentData || typeof paymentData !== 'object') return 'N/A';
    return paymentData.payment_type || 'N/A';
}

function extractNumericFromMixed(raw) {
    if (raw === null || raw === undefined || raw === '') return 0;
    if (typeof raw === 'number') return raw;
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

function extractPaymentAmount(p, defaultVal = 0) {
    if (!p || typeof p !== 'object') return defaultVal;
    const candidates = [
        p.amount, p['Amount'], p['Amount '], p.cash_amount, p['Cash Amount'],
        p.credit_card_amount, p.card_amount, p.debit_card_amount,
        p.gcash_amount, p.maya_amount, p.bank_amount,
        p['Total'], p.total
    ];
    for (let c of candidates) {
        const val = extractNumericFromMixed(c);
        if (val > 0) return val;
    }
    return defaultVal;
}

function renderPaymentDetails(payType, pData, overrideAmount = null, includeToken = false, includeVoucher = false, unitInfo = null) {
    const normType = String(payType || '').toLowerCase().trim();
    let html = '';
    let amt = (overrideAmount !== null && overrideAmount > 0)
        ? overrideAmount
        : extractPaymentAmount(pData, 0);

    let unitArray = [];
    if (Array.isArray(unitInfo)) {
        unitArray = unitInfo.filter(Boolean);
    } else if (typeof unitInfo === 'string' && unitInfo.trim()) {
        unitArray = unitInfo.split(',').map(s => s.trim()).filter(Boolean);
    }

    let unitRow = '';
    if (unitArray.length === 1) {
        unitRow = `Unit: ${unitArray[0]}`;
    } else if (unitArray.length > 1) {
        unitRow = unitArray.map((u, idx) => `Unit ${idx + 1}: ${u}`).join('\n');
    }

    html += `${unitRow}\nAmount: ₱${amt.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    return { html, amount: amt };
}

const paymentData = JSON.parse(data.sale.payment_data);
const isUpgradeSale = (data.sale.upgrade === 'UPGD' && data.sale.original_invoice_no && data.sale.original_invoice_no.trim() !== '') || (data.sale.page_type === 'upgradeunit') || (data.sale.old_unit_amount && parseFloat(data.sale.old_unit_amount) > 0);
const oldUnitAmount = parseFloat(data.sale.old_unit_amount || (isUpgradeSale ? data.sale.discount : 0) || 0);

const singleEntry = paymentData;
const fallbackAmt = isUpgradeSale ? Math.max(0, parseFloat(data.sale.total_amount || 0) - oldUnitAmount) : parseFloat(data.sale.actual_total_amount || data.sale.total_amount || 0);
let unitList = data.items.map(item => {
    const desc = item.item_description || item.item_code || '';
    return item.imei ? `${desc} (${item.imei})` : desc;
}).filter(Boolean);

let paymentInfoHTML = '';
let totalPayment = 0;

paymentInfoHTML += `Payment Method: ${formatPaymentMethodName(paymentData)}\n`;

const res = renderPaymentDetails(formatPaymentMethodName(paymentData), singleEntry, fallbackAmt, false, false, unitList);
paymentInfoHTML += res.html + '\n';
totalPayment = res.amount > 0 ? res.amount : fallbackAmt;

if (isUpgradeSale && oldUnitAmount > 0) {
    paymentInfoHTML += `Old Unit Price: ₱${oldUnitAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}\n`;
}

const discount = parseFloat(data.sale.discount || 0);
if (discount > 0 && !isUpgradeSale) {
    paymentInfoHTML += `Discount: - ₱${discount.toLocaleString('en-US', { minimumFractionDigits: 2 })}\n`;
}

paymentInfoHTML += `Total Payment: ₱${totalPayment.toLocaleString('en-US', { minimumFractionDigits: 2 })}\n`;

console.log(paymentInfoHTML);
