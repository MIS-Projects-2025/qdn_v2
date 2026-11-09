import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import BackLink from '@/Components/BackLink';

/**
 * Legacy QDN Record List -- read-only browser for the OLD QDN system's
 * full record set (qdn_db.qdn_record, via the tspi_qa connection). Not
 * the same as this app's own "QDN Records" page, which reads the
 * current `qdns` table -- see LegacyQdnRecordController vs.
 * QdnController::records().
 *
 * Search is SERVER-SIDE -- every keystroke-triggered search re-queries
 * the database via GET /qdn/legacy-records?q=..., it does not filter an
 * already-loaded list in the browser. Matched against qdn_no and
 * lot_id_no -- see LegacyQdnRecordController::SEARCHABLE_COLUMNS.
 *
 * Props (from LegacyQdnRecordController@index): records (paginator), filters
 */
export default function LegacyRecordsIndex({ records, filters }) {
    const [term, setTerm] = useState(filters?.q ?? '');

    function handleSearch(e) {
        e.preventDefault();
        router.get(
            '/qdn/legacy-records',
            term ? { q: term } : {},
            { preserveState: true, replace: true },
        );
    }

    return (
        <div className="qdn-page">
            <Head title="Legacy QDN Records" />
            <BackLink href="/qdn/dashboard" label="Back to QDN Dashboard" />
            <h1>Legacy QDN Records</h1>
            <p className="qdn-page-subtitle">
                Read-only records carried over from the previous QDN system
                (qdn_db.qdn_record).
            </p>

            <form onSubmit={handleSearch} className="qdn-legacy-search-row">
                <input
                    type="text"
                    value={term}
                    onChange={(e) => setTerm(e.target.value)}
                    placeholder="Search by QDN No or Lot ID…"
                />
                <button type="submit" title="Search" aria-label="Search">
                    <Search size={16} />
                </button>
            </form>

            <table className="qdn-table">
                <thead>
                    <tr>
                        <th>QDN No</th>
                        <th>Customer</th>
                        <th>Device</th>
                        <th>Lot ID</th>
                        <th>Issued By</th>
                        <th>Issued To</th>
                        <th>Status</th>
                        <th>Date Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {records.data.length === 0 && (
                        <tr>
                            <td colSpan={9}>No legacy records found.</td>
                        </tr>
                    )}
                    {records.data.map((r) => (
                        <tr key={r.id}>
                            <td>{r.qdn_no}</td>
                            <td>{r.customer}</td>
                            <td>{r.device_name}</td>
                            <td>{r.lot_id_no}</td>
                            <td>{r.issued_by_name}</td>
                            <td>{r.issued_to_name}</td>
                            <td>{r.qdn_status_label}</td>
                            <td>
                                {r.date_created
                                    ? new Date(r.date_created).toLocaleString()
                                    : '—'}
                            </td>
                            <td>
                                <Link
                                    href={`/qdn/legacy-records/${r.id}`}
                                    className="qdn-icon-link"
                                    title="View"
                                    aria-label="View"
                                >
                                    <Eye size={16} />
                                </Link>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {records.links && records.links.length > 3 && (
                <nav className="qdn-legacy-pagination">
                    {records.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url || '#'}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                            className={`qdn-legacy-page-link${
                                link.active ? ' is-active' : ''
                            }${!link.url ? ' is-disabled' : ''}`}
                            preserveState
                        />
                    ))}
                </nav>
            )}
        </div>
    );
}
