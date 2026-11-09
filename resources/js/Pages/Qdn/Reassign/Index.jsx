import { Head, Link } from '@inertiajs/react';
import { UserCog } from 'lucide-react';
import BackLink from '@/Components/BackLink';

/**
 * Department: PE's queue of every QDN currently owned by a department
 * (or awaiting PE's RCA validation) -- i.e. every QDN whose "Issued To"
 * can still meaningfully be corrected. Not scoped to one department, since
 * PE oversees all of them (same authority as the original assignment).
 * Props (from QdnReassignController@index): qdns
 */
export default function ReassignIndex({ qdns }) {
    return (
        <div className="qdn-page">
            <Head title="Reassign QDN Issuance" />
            <BackLink href="/qdn/dashboard" label="Back to QDN Dashboard" />
            <h1>Reassign QDN Issuance</h1>
            <p className="qdn-page-subtitle">
                Correct which department or employee a QDN was issued to. Each
                correction is logged -- the original assignment is kept, not
                overwritten.
            </p>

            <table className="qdn-table">
                <thead>
                    <tr>
                        <th>QDN No</th>
                        <th>Customer</th>
                        <th>Device</th>
                        <th>Classification</th>
                        <th>Status</th>
                        <th>Issued Department</th>
                        <th>QDN Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {qdns.length === 0 && (
                        <tr>
                            <td colSpan={8}>No QDNs are currently in a reassignable state.</td>
                        </tr>
                    )}
                    {qdns.map((q) => (
                        <tr key={q.id}>
                            <td>{q.qdn_no}</td>
                            <td>{q.customer_name}</td>
                            <td>{q.device_name}</td>
                            <td>{q.classification}</td>
                            <td>{q.status_label}</td>
                            <td>{q.issued_department ?? '—'}</td>
                            <td>{new Date(q.created_at).toLocaleString()}</td>
                            <td>
                                <Link
                                    href={`/pe/reassign/${q.id}`}
                                    className="qdn-icon-link"
                                    title="Reassign"
                                    aria-label="Reassign"
                                >
                                    <UserCog size={16} />
                                </Link>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
