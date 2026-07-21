export function formatCurrency(
    amount: number,
    currency = 'USD',
    maximumFractionDigits = 4,
): string {
    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
        currencyDisplay: 'code',
        minimumFractionDigits: 2,
        maximumFractionDigits,
    }).format(amount);
}
