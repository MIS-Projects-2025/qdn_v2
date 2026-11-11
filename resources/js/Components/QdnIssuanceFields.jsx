import QdnPhotos from "@/Components/QdnPhotos";

const CLASSIFICATIONS = ["Minor", "Major", "Critical"];

export function ReadOnly({ label, value }) {
    return (
        <div className="qdn-field">
            <label>{label}</label>
            <input type="text" value={value ?? ""} disabled readOnly />
        </div>
    );
}

/**
 * The QDN's "Details of Issuance", laid out exactly like the Create QDN
 * form (two columns of read-only fields, Photos under Detection Area,
 * Classification checkboxes, Details box). Used by PE Validation, RCA
 * and CAPA so each stage shows the QDN the way it was filed.
 */
export default function QdnIssuanceFields({ qdn, showIssuedDepartment = false }) {
    const detectedAt = qdn.detected_at ? new Date(qdn.detected_at).toLocaleString() : "";

    return (
        <div className="qdn-form qdn-form-readonly">
            <ReadOnly label="QDN No" value={qdn.qdn_no} />
            <ReadOnly label="Customer" value={qdn.customer_name} />
            <ReadOnly label="Lot ID" value={qdn.lot_id} />
            <ReadOnly label="Lot Qty" value={qdn.lot_qty} />
            <ReadOnly label="Device Name" value={qdn.device_name} />
            <ReadOnly label="Package Name" value={qdn.package_name} />
            <ReadOnly label="Machine No" value={qdn.machine_num} />
            <ReadOnly label="Detection Area" value={qdn.detection_area} />

            <QdnPhotos qdnId={qdn.id} asField />

            <ReadOnly label="Date & Time of Detection" value={detectedAt} />
            <ReadOnly label="Type of Non-Conformity / Failure Mode" value={qdn.nonconformity_name} />

            <div className="qdn-field">
                <label>Classification</label>
                <div className="qdn-checkbox-row">
                    {CLASSIFICATIONS.map((c) => (
                        <label key={c} className="qdn-checkbox">
                            <input type="checkbox" checked={qdn.classification === c} disabled readOnly />
                            {c}
                        </label>
                    ))}
                </div>
            </div>

            <div className="qdn-field qdn-field-wide">
                <label>Details of Non-Conformity / Failure Mode</label>
                <div
                    className="qdn-rte-content qdn-readonly-html"
                    dangerouslySetInnerHTML={{ __html: qdn.details ?? "" }}
                />
            </div>

            <ReadOnly label="Issued By" value={qdn.issued_by} />
            {showIssuedDepartment && <ReadOnly label="Issued Department" value={qdn.issued_department} />}
        </div>
    );
}

// Mirrors App\Models\Qdn::DISPOSITIONS / DISPOSITION_LABELS.
export const DISPOSITIONS = [
    { value: "rework", label: "Rework" },
    { value: "split_lot", label: "Split Lot" },
    { value: "shutdown", label: "Shutdown" },
    { value: "shipback", label: "Shipback" },
    { value: "use_as_is", label: "Use As Is" },
    { value: "invalid", label: "Invalid" },
];

/**
 * Customer: Others QDNs skip PE Validation (the issuer picks Issued To on
 * Create QDN), so they never get a PE disposition. Same case-insensitive,
 * trimmed match as QdnController::customerIsOthers().
 */
export function isOthersQdn(qdn) {
    return (qdn?.customer_name ?? "").trim().toLowerCase() === "others";
}

/**
 * PE Validation result in the same read-only form layout, with the
 * Disposition shown as the same checkbox row PE ticks on PE Validation.
 */
export function QdnValidationFields({ qdn }) {
    const v = qdn.validation ?? {};
    const by = v.validated_by ?? qdn.pe_disposition_by;
    const at = v.validated_at ?? qdn.pe_disposition_at;
    const others = isOthersQdn(qdn);
    return (
        <div className="qdn-form qdn-form-readonly">
            {!others && (
            <div className="qdn-field qdn-field-wide">
                <label>Disposition</label>
                <div className="qdn-checkbox-row qdn-checkbox-row-wrap">
                    {DISPOSITIONS.map((d) => (
                        <label key={d.value} className="qdn-checkbox">
                            <input type="checkbox" checked={qdn.pe_disposition === d.value} disabled readOnly />
                            {d.label}
                        </label>
                    ))}
                </div>
            </div>
            )}
            <ReadOnly label="Issued To" value={v.issued_to_name} />
            <ReadOnly label="Department" value={v.department ?? qdn.issued_department} />
            <ReadOnly label="Station" value={v.station} />
            <ReadOnly label="Product Line" value={v.prodline} />
            <ReadOnly label="Team Responsible" value={v.team} />
            <ReadOnly label={others ? "Issued By" : "Validated By"} value={by} />
            <ReadOnly label={others ? "Issued At" : "Validated At"} value={at ? new Date(at).toLocaleString() : ""} />
            <div className="qdn-field qdn-field-wide">
                <label>Remarks</label>
                <textarea value={v.remarks ?? qdn.pe_disposition_remarks ?? ""} disabled readOnly />
            </div>
        </div>
    );
}
