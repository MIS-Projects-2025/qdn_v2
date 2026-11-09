import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, ShieldCheck } from 'lucide-react';
import BackLink from '@/Components/BackLink';

// Matches each row's `stage` (set by PeQueueController@index) to the icon
// used for that step elsewhere in the app -- ClipboardCheck for the
// original PE Validation step (same icon as the old standalone PE
// Validation Queue page), ShieldCheck for PE Validation of RCA (same icon
// Qdn/PeRca/Index.jsx used).
const STAGE_ICONS = {
    validation: ClipboardCheck,
    rca_validation: ShieldCheck,
};

/**
 * Department: PE's combined queue -- everything currently waiting on PE,
 * across both the original "PE Validation and Assigning" step and "PE
 * Validation of RCA" step, in one sorted table instead of two separate
 * pages/nav links. Props (from PeQueueController@index): qdns (each row
 * tagged with stage/stage_label/action_label/action_href)
 */
export default function PeQueueIndex({ qdns }) {
    return (
        <div className="qdn-page">
            <Head title="PE Validation Queue" />
            <BackLink href="/qdn/dashboard" label="Back to QDN Dashboard" />
            <h1>PE Validation Queue</h1>

            <table className="qdn-table">
                <thead>
                    <tr>
                        <th>QDN No</th>
                        <th>Customer</th>
                        <th>Device</th>
                        <th>Classification</th>
                        <th>Stage</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {qdns.length === 0 && (
                        <tr>
                            <td colSpan={7}>Nothing currently waiting on PE.</td>
                        </tr>
                    )}
                    {qdns.map((q) => {
                        const ActionIcon = STAGE_ICONS[q.stage] ?? ClipboardCheck;
                        return (
                            <tr key={`${q.stage}-${q.id}`}>
                                <td>{q.qdn_no}</td>
                                <td>{q.customer_name}</td>
                                <td>{q.device_name}</td>
                                <td>{q.classification}</td>
                                <td>{q.stage_label}</td>
                                <td>
                                    {q.created_at
                                        ? new Date(q.created_at).toLocaleString()
                                        : '—'}
                                </td>
                                <td>
                                    <Link
                                        href={q.action_href}
                                        className="qdn-icon-link"
                                        title={q.action_label}
                                        aria-label={q.action_label}
                                    >
                                        <ActionIcon size={16} />
                                    </Link>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
