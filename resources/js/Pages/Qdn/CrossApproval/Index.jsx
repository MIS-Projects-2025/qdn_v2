import { Head, Link } from "@inertiajs/react";
import { CheckCircle2, Clock, Users } from "lucide-react";
import BackLink from "@/Components/BackLink";
import { ClassificationBadge } from "@/Components/Badges";

/**
 * PE / EE / QA / Production approval queue: QDNs that passed Dept Approval and now
 * need one approval each from Process Engineering, Equipment Engineering
 * and QA. Props (QdnCrossApprovalController@index): qdns, myDepartmentKey,
 * departments.
 */
export function ApprovalChips({ approvals }) {
    return (
        <div className="qdn-cross-chips">
            {Object.entries(approvals).map(([key, a]) => (
                <span
                    key={key}
                    className={`qdn-cross-chip ${a.approved ? "is-approved" : "is-pending"}`}
                    title={a.approved ? `Approved by ${a.approved_by} on ${a.approved_at}` : "Pending"}
                >
                    {a.approved ? <CheckCircle2 size={13} /> : <Clock size={13} />}
                    {a.label}
                </span>
            ))}
        </div>
    );
}

export default function CrossApprovalIndex({ qdns, myDepartmentKey, myIssuingDepartment, departments }) {
    const myLabel = myDepartmentKey ? departments[myDepartmentKey] : null;

    return (
        <div className="qdn-page">
            <Head title="PE / EE / QA / Production Approval" />
            <BackLink href="/qdn/dashboard" label="Back to QDN Dashboard" />
            <h1>PE / EE / QA / Production Approval</h1>
            <p className="qdn-page-subtitle">
                After Dept Approval, one Supervisor, Section Head or Manager from each of Process
                Engineering, Equipment Engineering, QA and Production approves. The first approver in each
                department counts for that department. For Customer: Others QDNs, the department that
                raised the QDN (Issuing Dept) must also approve.
                {myLabel
                    ? ` You approve for ${myLabel}.`
                    : myIssuingDepartment
                      ? ` You approve for ${myIssuingDepartment} on Customer: Others QDNs it raised.`
                      : " You can view this queue but not approve."}
            </p>

            <div className="qdn-table-scroll">
                <table className="qdn-table">
                    <thead>
                        <tr>
                            <th>QDN No</th>
                            <th>Issued Department</th>
                            <th>Classification</th>
                            <th>Approvals</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {qdns.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="qdn-empty">No QDNs waiting for PE / EE / QA / Production approval.</td>
                            </tr>
                        ) : (
                            qdns.map((q) => {
                                const mineDone = q.my_key && q.approvals[q.my_key]?.approved;
                                return (
                                    <tr key={q.id}>
                                        <td><strong>{q.qdn_no}</strong></td>
                                        <td>{q.issued_department ?? "—"}</td>
                                        <td><ClassificationBadge classification={q.classification} /></td>
                                        <td><ApprovalChips approvals={q.approvals} /></td>
                                        <td>
                                            <Link href={`/qdn/cross-approval/${q.id}`} className="qdn-icon-link" title="Open">
                                                <Users size={16} />
                                                <span style={{ marginLeft: 6 }}>
                                                    {q.my_key && !mineDone ? "Approve" : "View"}
                                                </span>
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
