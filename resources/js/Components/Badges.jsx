// Colour is keyed off the raw enum value (see qdn.css), not the label.
const STATUS_TONE = {
    closed: "success",
    issued: "success",
    invalid: "danger",
    invalid_disposition: "danger",
};

export function StatusBadge({ status, label }) {
    const tone = STATUS_TONE[status] ?? "pending";
    return (
        <span className={`qdn-status-badge qdn-status-badge-${tone}`}>
            {label ?? status ?? "—"}
        </span>
    );
}

export function ClassificationBadge({ classification }) {
    // Stored as "Minor"/"Major"/"Critical" or legacy "1-Minor" etc.
    const text = classification ? String(classification).replace(/^\d+\s*-\s*/, "") : null;
    const key = text ? text.toLowerCase() : "none";
    const known = ["minor", "major", "critical"].includes(key);
    return (
        <span className={`qdn-classification-badge qdn-classification-badge-${known ? key : "none"}`}>
            {text ?? "—"}
        </span>
    );
}
