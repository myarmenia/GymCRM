const MONEY_PRECISION = 2;
const PERCENT_PRECISION = 6;

const roundTo = (value, precision) => {
    const factor = 10 ** precision;

    return Math.round((Number(value) + Number.EPSILON) * factor) / factor;
};

const nonNegativeNumber = (value) => {
    const number = Number(value);

    return Number.isFinite(number) ? Math.max(number, 0) : 0;
};

export const normalizePercentage = (value) => {
    return roundTo(Math.min(nonNegativeNumber(value), 100), PERCENT_PRECISION);
};

export const normalizeMoney = (value) => {
    return roundTo(nonNegativeNumber(value), MONEY_PRECISION);
};

export const salaryAmountFromPercentage = (price, percentage) => {
    return normalizeMoney(
        (nonNegativeNumber(price) * normalizePercentage(percentage)) / 100,
    );
};

export const salaryPercentageFromAmount = (price, amount) => {
    const normalizedPrice = nonNegativeNumber(price);

    if (normalizedPrice === 0) {
        return 0;
    }

    return normalizePercentage(
        (normalizeMoney(amount) / normalizedPrice) * 100,
    );
};

export const fixedSalaryForPrice = (price, amount) => {
    return Math.min(normalizeMoney(amount), nonNegativeNumber(price));
};

export const existingSalaryPercentage = (price, type, value) => {
    if (type === "percent") {
        return normalizePercentage(value);
    }

    return salaryPercentageFromAmount(price, value);
};
