export const YEREVAN_TIMEZONE = "Asia/Yerevan";

const yerevanFormatter = new Intl.DateTimeFormat("en-CA", {
    timeZone: YEREVAN_TIMEZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
});

const yerevanDateTimeFormatter = new Intl.DateTimeFormat("en-CA", {
    timeZone: YEREVAN_TIMEZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
});

const partsToObject = (parts) =>
    parts.reduce((result, part) => {
        if (part.type !== "literal") {
            result[part.type] = part.value;
        }

        return result;
    }, {});

export const todayInYerevan = () => {
    const parts = partsToObject(yerevanFormatter.formatToParts(new Date()));
    return `${parts.year}-${parts.month}-${parts.day}`;
};

export const nowDateTimeLocalInYerevan = () => {
    const parts = partsToObject(yerevanDateTimeFormatter.formatToParts(new Date()));
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
};

export const parseYmdAsUtcDate = (value) => {
    if (!value) {
        return new Date(Date.UTC(1970, 0, 1, 12, 0, 0));
    }

    const [year, month, day] = String(value)
        .slice(0, 10)
        .split("-")
        .map(Number);

    return new Date(Date.UTC(year, month - 1, day, 12, 0, 0));
};

export const formatUtcDateToYmd = (date) => {
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, "0");
    const day = String(date.getUTCDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
};

export const addDaysToYmd = (value, days) => {
    const date = parseYmdAsUtcDate(value);
    date.setUTCDate(date.getUTCDate() + days);
    return formatUtcDateToYmd(date);
};

export const addMonthsToYmd = (value, months) => {
    const date = parseYmdAsUtcDate(value);
    date.setUTCMonth(date.getUTCMonth() + months);
    return formatUtcDateToYmd(date);
};

export const formatDateTimeInYerevan = (value) => {
    if (!value) {
        return "-";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value).replace("T", " ").slice(0, 19);
    }

    const parts = partsToObject(yerevanDateTimeFormatter.formatToParts(date));
    return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}`;
};
