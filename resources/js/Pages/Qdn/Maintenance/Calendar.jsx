import { useRef, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Check, Download, Pencil, Plus, Trash2, Upload, X } from "lucide-react";
import BackLink from "@/Components/BackLink";

const MONTHS = ["", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
// Work weeks are stored as numbers (e.g. 644) and shown as WW644.
const ww = (n) => (n === null || n === undefined || n === "" ? "—" : `WW${n}`);
const EMPTY = { cal_date: "", cal_year: "", cal_quarter: "", cal_month: "", cal_workweek: "" };

function nextDay(ymd) {
    if (!ymd) return "";
    const d = new Date(`${ymd}T00:00:00`);
    d.setDate(d.getDate() + 1);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Maintenance > TELFORD Calendar (telford_calendar): one row per day with
 * its year / quarter / month / work week. Filter by year, month or work
 * week; add from the form at the top; edit a row in place; delete a row.
 * After adding a date, the form moves to the next day and keeps the
 * year / quarter / month / work week, so a week can be entered quickly.
 *
 * Props from TelfordCalendarController@index:
 *   rows (paginator), filters { year, month, workweek }, years, lists (tabs)
 */
export default function MaintenanceCalendar({ rows, filters, years, lists }) {
    const { errors } = usePage().props;
    const [form, setForm] = useState(EMPTY);
    const [editing, setEditing] = useState(null);
    const [busy, setBusy] = useState(false);
    const [upload, setUpload] = useState(null);
    const fileRef = useRef(null);
    const [filter, setFilter] = useState({
        year: filters.year ?? "",
        month: filters.month ?? "",
        workweek: filters.workweek ?? "",
    });

    const options = { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) };

    function applyFilter(next = filter) {
        const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== ""));
        router.get(route("qdn.maintenance.calendar.index"), params, { preserveState: true, preserveScroll: true, replace: true });
    }

    // Export uses the filters already applied (filters prop), all pages.
    function exportUrl(format) {
        const params = Object.fromEntries(Object.entries({ ...filters, format }).filter(([, v]) => v !== "" && v != null));
        return route("qdn.maintenance.calendar.export", params);
    }

    // Upload .xlsx / .csv: adds new dates and updates existing ones (matched by cal_date).
    function importFile(e) {
        e.preventDefault();
        if (!upload) return;
        router.post(route("qdn.maintenance.calendar.import"), { file: upload }, {
            ...options,
            forceFormData: true,
            onSuccess: (page) => {
                if (!page.props.errors?.file) {
                    setUpload(null);
                    if (fileRef.current) fileRef.current.value = "";
                }
            },
        });
    }

    function add(e) {
        e.preventDefault();
        router.post(route("qdn.maintenance.calendar.store"), form, {
            ...options,
            onSuccess: (page) => {
                if (!Object.keys(page.props.errors ?? {}).length) {
                    setForm({ ...form, cal_date: nextDay(form.cal_date) });
                }
            },
        });
    }

    function save() {
        router.put(route("qdn.maintenance.calendar.update", editing.id), editing, {
            ...options,
            onSuccess: (page) => {
                if (!Object.keys(page.props.errors ?? {}).length) setEditing(null);
            },
        });
    }

    function remove(row) {
        if (!window.confirm(`Delete ${row.cal_date} from the TELFORD calendar? This can't be undone.`)) return;
        router.delete(route("qdn.maintenance.calendar.destroy", row.id), options);
    }

    const fields = (value, onChange, showErrors) => (
        <>
            <div className="qdn-field">
                <label>Date</label>
                <input type="date" value={value.cal_date} onChange={(e) => onChange({ ...value, cal_date: e.target.value })} />
                {showErrors && errors?.cal_date && <p className="qdn-field-error">{errors.cal_date}</p>}
            </div>
            <div className="qdn-field">
                <label>Year</label>
                <input type="text" maxLength={45} placeholder="e.g. 2027" value={value.cal_year} onChange={(e) => onChange({ ...value, cal_year: e.target.value })} />
                {showErrors && errors?.cal_year && <p className="qdn-field-error">{errors.cal_year}</p>}
            </div>
            <div className="qdn-field">
                <label>Quarter</label>
                <select value={value.cal_quarter} onChange={(e) => onChange({ ...value, cal_quarter: e.target.value })}>
                    <option value="">Select</option>
                    {[1, 2, 3, 4].map((q) => <option key={q} value={q}>Q{q}</option>)}
                </select>
                {showErrors && errors?.cal_quarter && <p className="qdn-field-error">{errors.cal_quarter}</p>}
            </div>
            <div className="qdn-field">
                <label>Month</label>
                <select value={value.cal_month} onChange={(e) => onChange({ ...value, cal_month: e.target.value })}>
                    <option value="">Select</option>
                    {MONTHS.slice(1).map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
                </select>
                {showErrors && errors?.cal_month && <p className="qdn-field-error">{errors.cal_month}</p>}
            </div>
            <div className="qdn-field">
                <label>Work Week</label>
                <input type="text" inputMode="numeric" placeholder="e.g. 644 or WW644" value={value.cal_workweek} onChange={(e) => onChange({ ...value, cal_workweek: e.target.value })} />
                {showErrors && errors?.cal_workweek && <p className="qdn-field-error">{errors.cal_workweek}</p>}
            </div>
        </>
    );

    return (
        <div className="qdn-page" style={{ maxWidth: 1200 }}>
            <Head title="Maintenance: TELFORD Calendar" />
            <BackLink href="/qdn/dashboard" label="Back to QDN Dashboard" />
            <h1>Maintenance</h1>

            <div className="qdn-checkbox-row qdn-checkbox-row-wrap qdn-dashboard-section">
                {lists.map((l) => (
                    <Link key={l.key} href={l.href} className={`qdn-btn qdn-tab${l.key === "calendar" ? " is-active" : ""}`} preserveScroll>
                        {l.label}
                    </Link>
                ))}
            </div>

            <h2 style={{ marginTop: 8 }}>TELFORD Calendar</h2>
            <p className="qdn-hint">One row per day. Each date can only be added once.</p>

            <form onSubmit={importFile} className="qdn-records-filters" style={{ alignItems: "start" }}>
                <div className="qdn-field qdn-field-wide">
                    <label htmlFor="calendar-file">Upload Excel or CSV to add / update dates</label>
                    <input
                        id="calendar-file"
                        ref={fileRef}
                        type="file"
                        accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                        onChange={(e) => setUpload(e.target.files?.[0] ?? null)}
                    />
                    <p className="qdn-field-hint" style={{ margin: 0 }}>
                        Row 1 must have the headings cal_date, cal_year, cal_quarter, cal_month, cal_workweek.
                        A date already in the calendar is updated; a new date is added. If any row has a problem,
                        nothing is saved. An exported file can be uploaded back as it is.{" "}
                        Template: <a href={route("qdn.maintenance.calendar.template")}>Excel</a>
                        {" · "}
                        <a href={route("qdn.maintenance.calendar.template", { format: "csv" })}>CSV</a>
                    </p>
                    {errors?.file && <p className="qdn-field-error">{errors.file}</p>}
                    {errors?.import_rows && (
                        <p className="qdn-field-error" style={{ whiteSpace: "pre-line", maxHeight: 220, overflowY: "auto" }}>
                            {errors.import_rows}
                        </p>
                    )}
                </div>
                <div className="qdn-field">
                    <button type="submit" className="qdn-btn" disabled={busy || !upload}>
                        <Upload size={16} style={{ display: "inline", marginRight: 6, verticalAlign: "-3px" }} />
                        {busy && upload ? "Uploading..." : "Upload"}
                    </button>
                </div>
            </form>

            <form onSubmit={add} className="qdn-records-filters">
                {fields(form, setForm, !editing)}
                <div className="qdn-field">
                    <button type="submit" className="qdn-btn" disabled={busy || !form.cal_date}>
                        <Plus size={16} style={{ display: "inline", marginRight: 6, verticalAlign: "-3px" }} />
                        Add
                    </button>
                </div>
            </form>

            <form onSubmit={(e) => { e.preventDefault(); applyFilter(); }} className="qdn-records-filters">
                <div className="qdn-field">
                    <label>Filter: Year</label>
                    <select value={filter.year} onChange={(e) => setFilter({ ...filter, year: e.target.value })}>
                        <option value="">All years</option>
                        {years.map((y) => <option key={y} value={y}>{y}</option>)}
                    </select>
                </div>
                <div className="qdn-field">
                    <label>Month</label>
                    <select value={filter.month} onChange={(e) => setFilter({ ...filter, month: e.target.value })}>
                        <option value="">All months</option>
                        {MONTHS.slice(1).map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
                    </select>
                </div>
                <div className="qdn-field">
                    <label>Work Week</label>
                    <input type="text" inputMode="numeric" placeholder="e.g. WW644" value={filter.workweek} onChange={(e) => setFilter({ ...filter, workweek: e.target.value })} />
                </div>
                <div className="qdn-field" style={{ flexDirection: "row", gap: 8, alignItems: "end" }}>
                    <button type="submit" className="qdn-btn">Filter</button>
                    <button
                        type="button"
                        className="qdn-btn qdn-tab"
                        onClick={() => { const cleared = { year: "", month: "", workweek: "" }; setFilter(cleared); applyFilter(cleared); }}
                    >
                        Clear
                    </button>
                </div>
            </form>

            <div className="qdn-table-toolbar">
                <span className="qdn-table-count">
                    {rows.total === 0 ? "No dates" : `Showing ${rows.from}–${rows.to} of ${rows.total} date(s)`}
                </span>
                {/* Plain <a> (not Inertia Link) so the browser downloads the file. */}
                <span style={{ display: "inline-flex", gap: 8 }}>
                    <a className="qdn-btn qdn-tab" href={exportUrl("csv")} aria-disabled={rows.total === 0}>
                        <Download size={14} style={{ display: "inline", marginRight: 6, verticalAlign: "-2px" }} />
                        Export CSV
                    </a>
                    <a className="qdn-btn qdn-tab" href={exportUrl("xlsx")} aria-disabled={rows.total === 0}>
                        <Download size={14} style={{ display: "inline", marginRight: 6, verticalAlign: "-2px" }} />
                        Export Excel
                    </a>
                </span>
            </div>

            <div className="qdn-table-scroll">
                <table className="qdn-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Year</th>
                            <th>Quarter</th>
                            <th>Month</th>
                            <th>Work Week</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th style={{ width: 100 }}>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.data.length === 0 && (
                            <tr><td colSpan={8}>No dates yet. Add one above.</td></tr>
                        )}
                        {rows.data.map((row) =>
                            editing?.id === row.id ? (
                                <tr key={row.id}>
                                    <td colSpan={5}>
                                        <div className="qdn-records-filters" style={{ margin: 0 }}>
                                            {fields(editing, setEditing, true)}
                                        </div>
                                    </td>
                                    <td colSpan={2} />
                                    <td style={{ whiteSpace: "nowrap" }}>
                                        <button type="button" className="qdn-icon-link" title="Save" aria-label="Save" disabled={busy} onClick={save}>
                                            <Check size={16} />
                                        </button>
                                        <button type="button" className="qdn-icon-link" title="Cancel" aria-label="Cancel" style={{ marginLeft: 8 }} onClick={() => setEditing(null)}>
                                            <X size={16} />
                                        </button>
                                    </td>
                                </tr>
                            ) : (
                                <tr key={row.id}>
                                    <td>{row.cal_date}</td>
                                    <td>{row.cal_year}</td>
                                    <td>{row.cal_quarter ? `Q${row.cal_quarter}` : "—"}</td>
                                    <td>{MONTHS[row.cal_month] ?? "—"}</td>
                                    <td>{ww(row.cal_workweek)}</td>
                                    <td>{row.created_by ? `${row.created_by}, ${row.date_created}` : row.date_created ?? "—"}</td>
                                    <td>{row.updated_by ? `${row.updated_by}, ${row.date_updated}` : "—"}</td>
                                    <td style={{ whiteSpace: "nowrap" }}>
                                        <button
                                            type="button"
                                            className="qdn-icon-link"
                                            title="Edit"
                                            aria-label="Edit"
                                            disabled={busy}
                                            onClick={() => setEditing({
                                                id: row.id,
                                                cal_date: row.cal_date ?? "",
                                                cal_year: row.cal_year ?? "",
                                                cal_quarter: row.cal_quarter ?? "",
                                                cal_month: row.cal_month ?? "",
                                                cal_workweek: row.cal_workweek ?? "",
                                            })}
                                        >
                                            <Pencil size={16} />
                                        </button>
                                        <button
                                            type="button"
                                            className="qdn-icon-link"
                                            title="Delete"
                                            aria-label="Delete"
                                            disabled={busy}
                                            style={{ marginLeft: 8, color: "#b91c1c" }}
                                            onClick={() => remove(row)}
                                        >
                                            <Trash2 size={16} />
                                        </button>
                                    </td>
                                </tr>
                            ),
                        )}
                    </tbody>
                </table>
            </div>

            {rows.last_page > 1 && (
                <div className="qdn-pagination">
                    <div className="qdn-pagination-links">
                        {rows.links.map((l, i) =>
                            l.url ? (
                                <Link
                                    key={i}
                                    href={l.url}
                                    preserveScroll
                                    className={`qdn-pagination-link${l.active ? " is-active" : ""}`}
                                    dangerouslySetInnerHTML={{ __html: l.label }}
                                />
                            ) : (
                                <span key={i} className="qdn-pagination-link is-disabled" dangerouslySetInnerHTML={{ __html: l.label }} />
                            ),
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
