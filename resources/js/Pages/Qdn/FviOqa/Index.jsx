import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { Camera, Eye, ImageOff, Search } from "lucide-react";
import BackLink from "@/Components/BackLink";
import { ClassificationBadge, StatusBadge } from "@/Components/Badges";

const TABS = [
    { value: "", label: "All", countKey: "all" },
    { value: "fvi", label: "Final Visual Inspection", countKey: "fvi" },
    { value: "oqa", label: "OQA", countKey: "oqa" },
];

/**
 * QDNs detected at Final Visual Inspection or OQA, with their photos.
 * Props from FviOqaController@index: qdns (paginator), counts, filters.
 */
export default function FviOqaIndex({ qdns, counts, filters }) {
    const [form, setForm] = useState({
        q: filters.q ?? "",
        date_from: filters.date_from ?? "",
        date_to: filters.date_to ?? "",
    });

    function apply(overrides = {}) {
        const params = Object.fromEntries(
            Object.entries({ ...form, area: filters.area, ...overrides }).filter(([, v]) => v),
        );
        router.get(route("qdn.fvi-oqa.index"), params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function reset() {
        setForm({ q: "", date_from: "", date_to: "" });
        router.get(route("qdn.fvi-oqa.index"), filters.area ? { area: filters.area } : {}, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }

    return (
        <div className="qdn-page" style={{ maxWidth: 1400 }}>
            <Head title="FVI / OQA QDNs" />
            <BackLink href="/qdn/dashboard" label="Back to QDN Dashboard" />
            <h1>FVI / OQA QDNs</h1>
            <p className="qdn-fvi-subtitle">QDNs detected at Final Visual Inspection or OQA, with their photos.</p>

            <div className="qdn-checkbox-row qdn-checkbox-row-wrap qdn-dashboard-section">
                {TABS.map((tab) => (
                    <button
                        key={tab.value || "all"}
                        type="button"
                        className={`qdn-btn qdn-tab${(filters.area ?? "") === tab.value ? " is-active" : ""}`}
                        onClick={() => apply({ area: tab.value })}
                    >
                        {tab.label} <span className="qdn-fvi-count">{counts[tab.countKey] ?? 0}</span>
                    </button>
                ))}
            </div>

            <form onSubmit={(e) => { e.preventDefault(); apply(); }} className="qdn-records-filters">
                <div className="qdn-field">
                    <label htmlFor="q">Search</label>
                    <div className="qdn-emp-search-input-wrap">
                        <Search size={14} className="qdn-emp-search-icon" />
                        <input id="q" type="text" placeholder="QDN No, customer, device, lot…"
                            value={form.q} onChange={(e) => setForm({ ...form, q: e.target.value })} />
                    </div>
                </div>
                <div className="qdn-field">
                    <label htmlFor="date_from">Date From</label>
                    <input id="date_from" type="date" value={form.date_from}
                        onChange={(e) => setForm({ ...form, date_from: e.target.value })} />
                </div>
                <div className="qdn-field">
                    <label htmlFor="date_to">Date To</label>
                    <input id="date_to" type="date" value={form.date_to}
                        onChange={(e) => setForm({ ...form, date_to: e.target.value })} />
                </div>
                <div className="qdn-field">
                    <label>&nbsp;</label>
                    <div style={{ display: "flex", gap: 8 }}>
                        <button type="submit" className="qdn-btn">Apply Filters</button>
                        <button type="button" className="qdn-btn qdn-btn-secondary" onClick={reset}>Reset</button>
                    </div>
                </div>
            </form>

            <div className="qdn-table-scroll">
                <table className="qdn-table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>QDN No</th>
                            <th>Detection Area</th>
                            <th>Customer</th>
                            <th>Device</th>
                            <th>Lot ID</th>
                            <th>Non-Conformity</th>
                            <th>Classification</th>
                            <th>Status</th>
                            <th>Date Issued</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {qdns.data.length === 0 && (
                            <tr><td colSpan={11}>No FVI or OQA QDNs match these filters.</td></tr>
                        )}
                        {qdns.data.map((q) => (
                            <tr key={q.id}>
                                <td>
                                    <Link href={route("qdn.records.show", q.id)} className="qdn-fvi-thumb" title="View photos">
                                        {q.thumbnail ? (
                                            <>
                                                <img src={q.thumbnail} alt="" loading="lazy" />
                                                {q.photos_count > 1 && (
                                                    <span className="qdn-fvi-thumb-count"><Camera size={11} /> {q.photos_count}</span>
                                                )}
                                            </>
                                        ) : (
                                            <span className="qdn-fvi-thumb-empty" title="No photos"><ImageOff size={16} /></span>
                                        )}
                                    </Link>
                                </td>
                                <td style={{ whiteSpace: "nowrap", fontWeight: 600 }}>{q.qdn_no}</td>
                                <td>
                                    <span className={`qdn-fvi-area qdn-fvi-area-${q.area ?? "other"}`}>
                                        {q.area === "oqa" ? "OQA" : q.area === "fvi" ? "FVI" : ""}
                                    </span>{" "}
                                    {q.detection_area}
                                </td>
                                <td>{q.customer_name || "—"}</td>
                                <td>{q.device_name || "—"}</td>
                                <td>{q.lot_id || "—"}</td>
                                <td>{q.nonconformity || "—"}</td>
                                <td><ClassificationBadge classification={q.classification} /></td>
                                <td><StatusBadge status={q.status} label={q.status_label} /></td>
                                <td style={{ whiteSpace: "nowrap" }}>{q.date_issued || "—"}</td>
                                <td>
                                    <Link href={route("qdn.records.show", q.id)} className="qdn-icon-link" title="View" aria-label="View">
                                        <Eye size={16} />
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {qdns.last_page > 1 && (
                <div className="qdn-pagination">
                    <span className="qdn-pagination-summary">Showing {qdns.from ?? 0}–{qdns.to ?? 0} of {qdns.total}</span>
                    <div className="qdn-pagination-links">
                        {qdns.links.map((link, i) =>
                            link.url ? (
                                <Link key={i} href={link.url} preserveState preserveScroll
                                    className={`qdn-pagination-link${link.active ? " is-active" : ""}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ) : (
                                <span key={i} className="qdn-pagination-link is-disabled" dangerouslySetInnerHTML={{ __html: link.label }} />
                            ),
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
