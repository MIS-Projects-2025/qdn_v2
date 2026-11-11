import { useState } from "react";
import { Head, router } from "@inertiajs/react";
import { CheckCircle2, Clock } from "lucide-react";
import QdnDetailCard from "@/Components/QdnDetailCard";
import BackLink from "@/Components/BackLink";

/**
 * One QDN's PE / EE / QA / Production approval. Shows each department's status; the
 * first Supervisor / Section Head / Manager of a department to approve
 * counts, and the card then shows who approved and when.
 * Props (QdnCrossApprovalController@show): qdn, approvals, myDepartmentKey, canApprove
 */
export default function CrossApprovalShow({ qdn, approvals, myDepartmentKey, canApprove, viewer }) {
    const [remarks, setRemarks] = useState("");
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(null);
    const mine = myDepartmentKey ? approvals[myDepartmentKey] : null;

    function approve(e) {
        e.preventDefault();
        setProcessing(true);
        setError(null);
        router.post(`/qdn/cross-approval/${qdn.id}`, { remarks }, {
            onError: (errs) => setError(errs.remarks || "Something went wrong."),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <div className="qdn-page">
            <Head title={`PE / EE / QA / Production Approval — ${qdn.qdn_no}`} />
            <BackLink href="/qdn/cross-approval" label="Back to PE / EE / QA / Production Approval" />
            <h1>PE / EE / QA / Production Approval — {qdn.qdn_no}</h1>

            <div className="qdn-cross-grid">
                {Object.entries(approvals).map(([key, a]) => (
                    <div key={key} className={`qdn-cross-card ${a.approved ? "is-approved" : "is-pending"}`}>
                        <div className="qdn-cross-card-head">
                            {a.approved ? <CheckCircle2 size={18} /> : <Clock size={18} />}
                            <strong>{a.label}</strong>
                            {/* Only while your department still has to approve. */}
                            {key === myDepartmentKey && !a.approved && <span className="qdn-cross-you">Your department</span>}
                        </div>
                        {a.approved ? (
                            <>
                                <div className="qdn-cross-status">Approved</div>
                                <div className="qdn-cross-meta">
                                    {a.approved_by}
                                    {a.approved_by_title ? `, ${a.approved_by_title}` : ""}
                                </div>
                                <div className="qdn-cross-meta">{a.approved_at}</div>
                                {a.remarks && <div className="qdn-cross-remarks">“{a.remarks}”</div>}
                            </>
                        ) : (
                            <div className="qdn-cross-status">Waiting for approval</div>
                        )}
                    </div>
                ))}
            </div>

            <QdnDetailCard qdn={qdn} />

            <form onSubmit={approve} className="qdn-dashboard-section">
                <h2>Your approval</h2>

                {!myDepartmentKey && (
                    <p className="qdn-restricted-notice">
                        Only a Supervisor, Section Head or Manager of Process Engineering, Equipment
                        Engineering, QA, Production (or, for Customer: Others, the issuing department) can approve. You can view this QDN.
                        <br />
                        Signed in as: {viewer?.department || "no department"}
                        {" / "}
                        {viewer?.titles?.length ? viewer.titles.join(", ") : "no job title"}
                    </p>
                )}
                {mine?.approved && (
                    <p className="qdn-restricted-notice">
                        {mine.label} already approved this QDN ({mine.approved_by}, {mine.approved_at}).
                        No further approval is needed from your department.
                    </p>
                )}

                {canApprove && (
                    <>
                        <div className="qdn-field qdn-field-wide">
                            <label htmlFor="remarks">Remarks (optional)</label>
                            <textarea id="remarks" rows={3} value={remarks} onChange={(e) => setRemarks(e.target.value)} />
                        </div>
                        {error && <p className="qdn-field-error">{error}</p>}
                        <button type="submit" className="qdn-btn" disabled={processing}>
                            Approve for {mine?.label}
                        </button>
                    </>
                )}
            </form>
        </div>
    );
}
