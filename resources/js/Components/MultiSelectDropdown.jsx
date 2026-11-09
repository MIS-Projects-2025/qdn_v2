import { useEffect, useMemo, useRef, useState } from "react";
import { ChevronDown, Search, X } from "lucide-react";

export default function MultiSelectDropdown({
    id,
    label,
    placeholder = "Any",
    options = [],
    selected = [],
    onChange,
}) {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState("");
    const rootRef = useRef(null);
    const values = Array.isArray(selected) ? selected : [];

    useEffect(() => {
        const onDocClick = (e) => {
            if (rootRef.current && !rootRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener("mousedown", onDocClick);
        return () => document.removeEventListener("mousedown", onDocClick);
    }, []);

    const filtered = useMemo(() => {
        const t = term.trim().toLowerCase();
        return t ? options.filter((o) => String(o.label).toLowerCase().includes(t)) : options;
    }, [options, term]);

    const isSelected = (v) => values.some((x) => String(x) === String(v));
    const toggle = (v) =>
        onChange?.(isSelected(v) ? values.filter((x) => String(x) !== String(v)) : [...values, v]);

    const summary =
        values.length === 0
            ? placeholder
            : values.length === 1
              ? options.find((o) => String(o.value) === String(values[0]))?.label ?? values[0]
              : `${values.length} selected`;

    return (
        <div className="qdn-multiselect" ref={rootRef}>
            <button
                type="button"
                id={id}
                aria-label={label}
                aria-expanded={open}
                className={`qdn-multiselect-toggle${values.length ? " has-value" : ""}${open ? " is-open" : ""}`}
                onClick={() => setOpen((o) => !o)}
            >
                <span className="qdn-multiselect-summary">{summary}</span>
                {values.length > 0 && (
                    <span
                        role="button"
                        tabIndex={0}
                        className="qdn-multiselect-clear"
                        aria-label={`Clear ${label ?? ""}`}
                        onClick={(e) => {
                            e.stopPropagation();
                            onChange?.([]);
                        }}
                    >
                        <X size={14} />
                    </span>
                )}
                <ChevronDown size={16} className="qdn-multiselect-chevron" />
            </button>
            {open && (
                <div className="qdn-multiselect-panel">
                    <div className="qdn-multiselect-search">
                        <Search size={14} />
                        <input
                            autoFocus
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder={`Search ${label ?? ""}`}
                        />
                    </div>
                    <div className="qdn-multiselect-options">
                        {filtered.length === 0 ? (
                            <div className="qdn-multiselect-empty">No matches</div>
                        ) : (
                            filtered.map((o) => (
                                <label key={o.value} className="qdn-multiselect-option">
                                    <input
                                        type="checkbox"
                                        checked={isSelected(o.value)}
                                        onChange={() => toggle(o.value)}
                                    />
                                    {o.label}
                                </label>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
