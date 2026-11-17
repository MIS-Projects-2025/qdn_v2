import { useEffect, useMemo, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Check, Lock, Pencil, Plus, Search, Trash2, X } from "lucide-react";
import BackLink from "@/Components/BackLink";

/**
 * Maintenance (QA / QMS only): one page for every QDN lookup list, a tab
 * per list. Add from the form at the top, edit a row in place, delete
 * from the row. Rows that drive workflow logic (Customer "Others",
 * Source of Defect "Man") show a lock and can't be renamed or deleted.
 * A row still used by a QDN can't be deleted (the server says so).
 *
 * Props from LookupMaintenanceController@index:
 *   lists      [{ key, label }]
 *   current    { key, label, field, used_in, has_category, extras }
 *   categories [{ value, label }]   (Non-conformity only)
 *   rows       [{ id, name, category, category_label, locked_reason, photo_area, extras }]
 *
 * current.extras: extra columns edited next to the name, e.g. Root Cause
 * List's IR Code (text) and Root Cause Type (select) -- [{ key, label, type, options }].
 */

const blank = () => ({ name: "", category: "", extras: {} });

function ExtraInput({ extra, value, onChange, id }) {
    if (extra.type === "textarea") {
        return <textarea id={id} rows={2} value={value ?? ""} onChange={(e) => onChange(e.target.value)} />;
    }
    if (extra.type === "select") {
        const options = value && !extra.options.includes(value) ? [value, ...extra.options] : extra.options;
        return (
            <select id={id} value={value ?? ""} onChange={(e) => onChange(e.target.value)}>
                <option value="">Select {extra.label.toLowerCase()}</option>
                {options.map((o) => <option key={o} value={o}>{o}</option>)}
            </select>
        );
    }
    return <input id={id} type="text" maxLength={extra.max ?? 255} value={value ?? ""} onChange={(e) => onChange(e.target.value)} />;
}
export default function MaintenanceIndex({ lists, current, categories, rows }) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState("");
    const extras = current.extras ?? [];
    const [newRow, setNewRow] = useState(blank);
    const [editing, setEditing] = useState(null); // { id, name, category }
    const [busy, setBusy] = useState(false);

    // Reset the forms when switching tabs.
    useEffect(() => {
        setSearch("");
        setNewRow(blank());
        setEditing(null);
    }, [current.key]);

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return rows;
        // Search the name and the extra columns (e.g. IR Code).
        return rows.filter((r) =>
            [r.name, ...Object.values(r.extras ?? {})].some((v) => String(v ?? "").toLowerCase().includes(term)),
        );
    }, [rows, search]);

    const options = { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) };

    function add(e) {
        e.preventDefault();
        router.post(route("qdn.maintenance.store", current.key), newRow, {
            ...options,
            onSuccess: (page) => {
                if (!Object.keys(page.props.errors ?? {}).length) setNewRow(blank());
            },
        });
    }

    function save(e) {
        e?.preventDefault();
        router.put(route("qdn.maintenance.update", [current.key, editing.id]), editing, {
            ...options,
            onSuccess: (page) => {
                if (!Object.keys(page.props.errors ?? {}).length) setEditing(null);
            },
        });
    }

    function remove(row) {
        if (!window.confirm(`Delete ${current.field} "${row.name}"? This can't be undone.`)) return;
        router.delete(route("qdn.maintenance.destroy", [current.key, row.id]), options);
    }

    const cols = (current.has_category ? 4 : 3) + extras.length + (current.has_audit ? 2 : 0);

    return (
        <div className="qdn-page" style={{ maxWidth: 1100 }}>
            <Head title={`Maintenance: ${current.label}`} />
            <BackLink href="/qdn/dashboard" label="Back to QDN Dashboard" />
            <h1>Maintenance</h1>
            <p className="qdn-page-subtitle">
                Manage the lists used on QDN forms. Renaming a value only affects new QDNs;
                existing QDNs keep the name they were saved with.
            </p>

            <div className="qdn-checkbox-row qdn-checkbox-row-wrap qdn-dashboard-section">
                {lists.map((l) => (
                    <Link
                        key={l.key}
                        href={l.href}
                        className={`qdn-btn qdn-tab${l.key === current.key ? " is-active" : ""}`}
                        preserveScroll
                    >
                        {l.label}
                    </Link>
                ))}
            </div>

            <h2 style={{ marginTop: 8 }}>{current.label}</h2>
            {current.used_in && <p className="qdn-hint">Used in: {current.used_in}</p>}

            <form onSubmit={add} className="qdn-records-filters">
                <div className="qdn-field">
                    <label htmlFor="new-name">New {current.field}</label>
                    <input
                        id="new-name"
                        type="text"
                        maxLength={current.max ?? 255}
                        value={newRow.name}
                        onChange={(e) => setNewRow({ ...newRow, name: e.target.value })}
                        placeholder={`Enter ${current.field.toLowerCase()}`}
                    />
                    {errors?.name && !editing && <p className="qdn-field-error">{errors.name}</p>}
                </div>
                {extras.map((x) => (
                    <div className="qdn-field" key={x.key}>
                        <label htmlFor={`new-${x.key}`}>{x.label}</label>
                        <ExtraInput
                            extra={x}
                            id={`new-${x.key}`}
                            value={newRow.extras[x.key]}
                            onChange={(v) => setNewRow({ ...newRow, extras: { ...newRow.extras, [x.key]: v } })}
                        />
                        {errors?.[`extras.${x.key}`] && !editing && <p className="qdn-field-error">{errors[`extras.${x.key}`]}</p>}
                    </div>
                ))}
                {current.has_category && (
                    <div className="qdn-field">
                        <label htmlFor="new-category">Category</label>
                        <select
                            id="new-category"
                            value={newRow.category}
                            onChange={(e) => setNewRow({ ...newRow, category: e.target.value })}
                        >
                            <option value="">Select category</option>
                            {categories.map((c) => (
                                <option key={c.value} value={c.value}>{c.label}</option>
                            ))}
                        </select>
                        {errors?.category && !editing && <p className="qdn-field-error">{errors.category}</p>}
                    </div>
                )}
                <div className="qdn-field">
                    <button type="submit" className="qdn-btn" disabled={busy || !newRow.name.trim()}>
                        <Plus size={16} style={{ display: "inline", marginRight: 6, verticalAlign: "-3px" }} />
                        Add
                    </button>
                </div>
            </form>

            <div className="qdn-table-toolbar">
                <div className="qdn-emp-search-input-wrap">
                    <Search size={16} className="qdn-emp-search-icon" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={`Search ${current.label.toLowerCase()}`}
                        aria-label="Search"
                    />
                </div>
                <span className="qdn-table-count">
                    {filtered.length === rows.length
                        ? `${rows.length} item(s)`
                        : `${filtered.length} of ${rows.length} item(s)`}
                </span>
            </div>

            <div className="qdn-table-scroll">
                <table className="qdn-table">
                    <thead>
                        <tr>
                            <th style={{ width: 60 }}>#</th>
                            <th>{current.field}</th>
                            {extras.map((x) => <th key={x.key} style={{ width: 170 }}>{x.label}</th>)}
                            {current.has_category && <th style={{ width: 180 }}>Category</th>}
                            {current.has_audit && <th style={{ width: 170 }}>Created</th>}
                            {current.has_audit && <th style={{ width: 170 }}>Updated</th>}
                            <th style={{ width: 120 }}>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={cols}>
                                    {rows.length === 0 ? "No items yet. Add one above." : "No items match your search."}
                                </td>
                            </tr>
                        )}
                        {filtered.map((row, i) => {
                            const isEditing = editing?.id === row.id;
                            const locked = !!row.locked_reason;
                            return (
                                <tr key={row.id}>
                                    <td>{i + 1}</td>
                                    <td>
                                        {isEditing ? (
                                            <div className="qdn-field">
                                                <input
                                                    type="text"
                                                    maxLength={current.max ?? 255}
                                                    value={editing.name}
                                                    disabled={locked}
                                                    autoFocus
                                                    onChange={(e) => setEditing({ ...editing, name: e.target.value })}
                                                    onKeyDown={(e) => {
                                                        if (e.key === "Enter") save(e);
                                                        if (e.key === "Escape") setEditing(null);
                                                    }}
                                                />
                                                {errors?.name && <p className="qdn-field-error">{errors.name}</p>}
                                            </div>
                                        ) : (
                                            <>
                                                {row.name}
                                                {locked && (
                                                    <span title={row.locked_reason} className="qdn-hint" style={{ marginLeft: 8 }}>
                                                        <Lock size={13} style={{ display: "inline", verticalAlign: "-2px" }} /> {row.locked_reason}
                                                    </span>
                                                )}
                                                {row.photo_area && (
                                                    <span className="qdn-hint" style={{ marginLeft: 8 }}>
                                                        (photos required on Create QDN)
                                                    </span>
                                                )}
                                            </>
                                        )}
                                    </td>
                                    {extras.map((x) => (
                                        <td key={x.key}>
                                            {isEditing ? (
                                                <div className="qdn-field">
                                                    <ExtraInput
                                                        extra={x}
                                                        value={editing.extras?.[x.key]}
                                                        onChange={(v) => setEditing({ ...editing, extras: { ...editing.extras, [x.key]: v } })}
                                                    />
                                                    {errors?.[`extras.${x.key}`] && <p className="qdn-field-error">{errors[`extras.${x.key}`]}</p>}
                                                </div>
                                            ) : (
                                                row.extras?.[x.key] || "—"
                                            )}
                                        </td>
                                    ))}
                                    {current.has_category && (
                                        <td>
                                            {isEditing ? (
                                                <div className="qdn-field">
                                                    <select
                                                        value={editing.category ?? ""}
                                                        onChange={(e) => setEditing({ ...editing, category: e.target.value })}
                                                    >
                                                        <option value="">Select category</option>
                                                        {categories.map((c) => (
                                                            <option key={c.value} value={c.value}>{c.label}</option>
                                                        ))}
                                                    </select>
                                                    {errors?.category && <p className="qdn-field-error">{errors.category}</p>}
                                                </div>
                                            ) : (
                                                row.category_label ?? "—"
                                            )}
                                        </td>
                                    )}
                                    {current.has_audit && <td>{row.audit?.created ?? "—"}</td>}
                                    {current.has_audit && <td>{row.audit?.updated ?? "—"}</td>}
                                    <td style={{ whiteSpace: "nowrap" }}>
                                        {isEditing ? (
                                            <>
                                                <button type="button" className="qdn-icon-link" title="Save" aria-label="Save" disabled={busy} onClick={save}>
                                                    <Check size={16} />
                                                </button>
                                                <button type="button" className="qdn-icon-link" title="Cancel" aria-label="Cancel" onClick={() => setEditing(null)} style={{ marginLeft: 8 }}>
                                                    <X size={16} />
                                                </button>
                                            </>
                                        ) : (
                                            <>
                                                <button
                                                    type="button"
                                                    className="qdn-icon-link"
                                                    title={locked && !current.has_category ? row.locked_reason : "Edit"}
                                                    aria-label="Edit"
                                                    disabled={busy || (locked && !current.has_category)}
                                                    onClick={() => setEditing({ id: row.id, name: row.name, category: row.category ?? "", extras: { ...(row.extras ?? {}) } })}
                                                >
                                                    <Pencil size={16} />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="qdn-icon-link"
                                                    title={locked ? row.locked_reason : "Delete"}
                                                    aria-label="Delete"
                                                    disabled={busy || locked}
                                                    onClick={() => remove(row)}
                                                    style={{ marginLeft: 8, color: locked ? undefined : "#b91c1c" }}
                                                >
                                                    <Trash2 size={16} />
                                                </button>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
