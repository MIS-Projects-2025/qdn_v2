import { useEffect, useRef, useState } from "react";
import axios from "axios";
import { Search, X } from "lucide-react";

/**
 * Type-ahead employee picker backed by EmployeeSearchController
 * (GET /employees/search?q=&department=&titles[]=&like[]=).
 * value / onChange use the masterlist shape: { EMPLOYID, EMPLOYNAME, DEPARTMENT, ... }.
 */
export default function EmployeeSearchSelect({
    value,
    onChange,
    placeholder = "Search by ID or name…",
    department,
    titles,
    like,
    compact = false,
    disabled = false,
}) {
    const [term, setTerm] = useState("");
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [highlight, setHighlight] = useState(0);
    const rootRef = useRef(null);
    const reqId = useRef(0);

    useEffect(() => {
        const onDocClick = (e) => {
            if (rootRef.current && !rootRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener("mousedown", onDocClick);
        return () => document.removeEventListener("mousedown", onDocClick);
    }, []);

    useEffect(() => {
        if (!open) return;
        const id = ++reqId.current;
        setLoading(true);
        const t = setTimeout(() => {
            axios
                .get(route("employees.search"), {
                    params: { q: term || undefined, department, titles, like },
                })
                .then((res) => {
                    if (id === reqId.current) {
                        setResults(res.data?.employees ?? []);
                        setHighlight(0);
                    }
                })
                .catch(() => id === reqId.current && setResults([]))
                .finally(() => id === reqId.current && setLoading(false));
        }, 250);
        return () => clearTimeout(t);
    }, [term, open, department, JSON.stringify(titles), JSON.stringify(like)]);

    const choose = (emp) => {
        onChange?.(emp);
        setTerm("");
        setOpen(false);
    };

    const onKeyDown = (e) => {
        if (!open) return;
        if (e.key === "ArrowDown") {
            e.preventDefault();
            setHighlight((h) => Math.min(h + 1, results.length - 1));
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            setHighlight((h) => Math.max(h - 1, 0));
        } else if (e.key === "Enter") {
            if (results[highlight]) {
                e.preventDefault();
                choose(results[highlight]);
            }
        } else if (e.key === "Escape") {
            setOpen(false);
        }
    };

    const cls = `qdn-emp-search${compact ? " qdn-emp-search-compact" : ""}`;

    if (value) {
        return (
            <div className={cls} ref={rootRef}>
                <div className="qdn-emp-search-selected">
                    <span title={value.EMPLOYNAME}>
                        {value.EMPLOYID} — {value.EMPLOYNAME ?? ""}
                    </span>
                    {!disabled && (
                        <button
                            type="button"
                            className="qdn-emp-search-clear"
                            aria-label="Clear selection"
                            onClick={() => onChange?.(null)}
                        >
                            <X size={14} />
                        </button>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className={cls} ref={rootRef}>
            <div className="qdn-emp-search-input-wrap">
                <Search size={14} className="qdn-emp-search-icon" />
                <input
                    type="text"
                    value={term}
                    disabled={disabled}
                    placeholder={placeholder}
                    onFocus={() => setOpen(true)}
                    onChange={(e) => {
                        setTerm(e.target.value);
                        setOpen(true);
                    }}
                    onKeyDown={onKeyDown}
                />
            </div>
            {open && (
                <div className="qdn-emp-search-dropdown">
                    {loading && results.length === 0 ? (
                        <div className="qdn-emp-search-empty">Searching…</div>
                    ) : results.length === 0 ? (
                        <div className="qdn-emp-search-empty">No employees found</div>
                    ) : (
                        results.map((emp, i) => (
                            <button
                                type="button"
                                key={emp.EMPLOYID}
                                className={`qdn-emp-search-option${i === highlight ? " is-highlighted" : ""}`}
                                onMouseEnter={() => setHighlight(i)}
                                onClick={() => choose(emp)}
                            >
                                <strong>{emp.EMPLOYID}</strong>
                                <span>{emp.EMPLOYNAME}</span>
                                {emp.DEPARTMENT && <em>{emp.DEPARTMENT}</em>}
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
