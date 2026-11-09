import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import BackLink from '@/Components/BackLink';

// Renders blank/null values consistently instead of letting a disabled
// input show a totally empty box, which on a dense form like this reads
// as a rendering bug rather than "this field was empty."
function val(v) {
    if (v === null || v === undefined || v === '') return '—';
    return v;
}

function dateVal(v, withTime = true) {
    if (!v) return '—';
    const d = new Date(v);
    return withTime ? d.toLocaleString() : d.toLocaleDateString();
}

// Same field shape as every input on Qdn/Create.jsx -- label above a
// boxed input, just disabled/readOnly instead of editable, so a legacy
// record reads as the same kind of form instead of a plain label/value
// list.
function Field({ label, value }) {
    return (
        <div className="qdn-field">
            <label>{label}</label>
            <input type="text" value={val(value)} disabled readOnly />
        </div>
    );
}

// failure_mode_*/disposition_* are 0/1 flag columns -- shown the same
// way Create.jsx shows Classification: a disabled checkbox row with
// whichever ones are set to 1 checked.
function FlagRow({ label, checked }) {
    return (
        <label className="qdn-checkbox">
            <input type="checkbox" checked={!!checked} disabled readOnly />
            {label}
        </label>
    );
}

/**
 * Every column on a legacy qdn_record row, laid out as a read-only form
 * in the same visual style as Qdn/Create.jsx (label above a boxed
 * input, grouped into labeled sections) rather than a plain label/value
 * list. Props (from LegacyQdnRecordController@show): record
 */
export default function LegacyRecordsShow({ record }) {
    return (
        <div className="qdn-page">
            <Head title={`Legacy QDN — ${record.qdn_no}`} />
            <div className="qdn-legacy-show-header">
                <BackLink href="/qdn/legacy-records" label="Back to Legacy QDN Records" />
                <button
                    type="button"
                    className="qdn-legacy-save-pdf"
                    onClick={() => window.print()}
                >
                    <Printer size={15} />
                    Save as PDF
                </button>
            </div>
            <h1>Legacy QDN Record — {record.qdn_no}</h1>

            <form className="qdn-form">
                <div className="qdn-field">
                    <label>QDN No</label>
                    <input type="text" value={val(record.qdn_no)} disabled readOnly />
                </div>
                <div className="qdn-field">
                    <label>Status</label>
                    <input type="text" value={val(record.qdn_status_label)} disabled readOnly />
                </div>
                <Field label="Calendar Year" value={record.calyear} />
                <Field label="Calendar Quarter" value={record.calquarter} />
                <Field label="Calendar Month" value={record.calmonth} />
                <Field label="Calendar Work Week" value={record.calwork_week} />
                <Field label="Customer" value={record.customer} />
                <Field label="Package Type" value={record.package_type} />
                <Field label="Device Name" value={record.device_name} />
                <Field label="Lot ID No" value={record.lot_id_no} />
                <Field label="Lot Qty" value={record.lot_qty} />
                <Field label="Machine No" value={record.machine_no} />
                <Field label="Machine Feed Type" value={record.qdn_machine_feed_type} />
                <Field label="Machine Platform" value={record.qdn_machine_platform} />
                <Field label="Detection Area" value={record.detection_area} />
                <Field label="Other Area" value={record.other_area} />
                <Field label="Date & Time of Detection" value={dateVal(record.detection_datetime)} />

                <div className="qdn-field">
                    <label>Type of Non-Conformity</label>
                    <input type="text" value={val(record.nonconformity_type)} disabled readOnly />
                </div>
                <div className="qdn-field">
                    <label>Classification</label>
                    <div className="qdn-checkbox-row">
                        {['Minor', 'Major', 'Critical'].map((c) => (
                            <FlagRow
                                key={c}
                                label={c}
                                checked={record.nonconformity_classification_label === c}
                            />
                        ))}
                    </div>
                </div>
                <div className="qdn-field qdn-field-wide">
                    <label>Details of Non-Conformity</label>
                    <div
                        className="qdn-rte-content qdn-readonly-html"
                        dangerouslySetInnerHTML={{ __html: record.nonconformity_details || '—' }}
                    />
                </div>

                <Field label="Issued By — Employee No" value={record.issued_by_num} />
                <Field label="Issued By — Name" value={record.issued_by_name} />
                <Field label="Issued By — Position" value={record.issued_by_position} />
                <Field label="Issued By — Department" value={record.issued_by_dept} />
                <Field label="Issued By — Product Line" value={record.issued_by_pl} />
                <Field label="Issued By — Station" value={record.issued_by_station} />
                <Field label="Issued By — Team" value={record.issued_by_team} />

                <Field label="Issued To — Employee No" value={record.issued_to_num} />
                <Field label="Issued To — Name" value={record.issued_to_name} />
                <Field label="Issued To — Position" value={record.issued_to_position} />
                <Field label="Issued To — Department" value={record.issued_to_dept} />
                <Field label="Issued To — Product Line" value={record.issued_to_pl} />
                <Field label="Issued To — Station" value={record.issued_to_station} />
                <Field label="Issued To — Team" value={record.issued_to_team} />

                <Field label="Acknowledged By — Employee No" value={record.acknowledge_by_num} />
                <Field label="Acknowledged By — Name" value={record.acknowledge_by_name} />
                <Field label="Acknowledged By — Position" value={record.acknowledge_by_position} />
                <Field label="Acknowledged By — Department" value={record.acknowledge_by_dept} />
                <Field label="Acknowledged By — Product Line" value={record.acknowledge_by_pl} />
                <Field label="Acknowledged By — Station" value={record.acknowledge_by_station} />
                <Field label="Acknowledged By — Team" value={record.acknowledge_by_team} />

                <Field label="Senior Mgmt — Employee No" value={record.senior_mgmt_by_num} />
                <Field label="Senior Mgmt — Name" value={record.senior_mgmt_by_name} />
                <Field label="Senior Mgmt — Position" value={record.senior_mgmt_by_position} />
                <Field label="Senior Mgmt — Department" value={record.senior_mgmt_by_dept} />
                <Field label="Senior Mgmt — Product Line" value={record.senior_mgmt_by_pl} />
                <Field label="Senior Mgmt — Station" value={record.senior_mgmt_by_station} />
                <Field label="Senior Mgmt — Team" value={record.senior_mgmt_by_team} />

                <Field label="Response Status" value={record.response_status_label} />
                <Field label="24-Hour Response Scheduled" value={dateVal(record.response_24hr_sched)} />
                <Field label="Responded At" value={dateVal(record.respond_24hr_date)} />
                <Field label="Overdue Status" value={record.qdn_overdue_status} />

                <div className="qdn-field qdn-field-wide">
                    <label>Failure Mode</label>
                    <div className="qdn-checkbox-row">
                        <FlagRow label="Man" checked={record.failure_mode_man} />
                        <FlagRow label="Machine" checked={record.failure_mode_machine} />
                        <FlagRow label="Material" checked={record.failure_mode_material} />
                        <FlagRow label="Method" checked={record.failure_mode_method} />
                    </div>
                </div>

                <div className="qdn-field qdn-field-wide">
                    <label>Disposition</label>
                    <div className="qdn-checkbox-row">
                        <FlagRow label="Rework" checked={record.disposition_rework} />
                        <FlagRow label="Split Lot" checked={record.disposition_split_lot} />
                        <FlagRow label="Shutdown" checked={record.disposition_shutdown} />
                        <FlagRow label="Shipback" checked={record.disposition_shipback} />
                        <FlagRow label="Use As Is" checked={record.disposition_use_as_is} />
                    </div>
                </div>
                <Field label="Disposition Date" value={dateVal(record.disposition_date)} />
                {record.invalid_remarks && (
                    <div className="qdn-field qdn-field-wide">
                        <label>Invalid Remarks</label>
                        <div className="qdn-readonly-html">{record.invalid_remarks}</div>
                    </div>
                )}
                <Field label="Disposition By — Employee No" value={record.disposition_by_num} />
                <Field label="Disposition By — Name" value={record.disposition_by_name} />
                <Field label="Disposition By — Position" value={record.disposition_by_position} />
                <Field label="Disposition By — Department" value={record.disposition_by_dept} />
                <Field label="Disposition By — Product Line" value={record.disposition_by_pl} />
                <Field label="Disposition By — Team" value={record.disposition_by_team} />

                {/* containment_status / corrective_status / correction_status
                    have no documented value mapping in qdn_record's column
                    comments (unlike qdn_status / nonconformity_classification /
                    response_status above), so these show the raw stored
                    value rather than an invented label. */}
                <Field label="Containment Status (raw)" value={record.containment_status} />
                <Field label="Corrective Status (raw)" value={record.corrective_status} />
                <Field label="Correction Status (raw)" value={record.correction_status} />
                <Field label="Date Closed" value={dateVal(record.date_closed)} />
                <Field label="Date Created" value={dateVal(record.date_created)} />
            </form>
        </div>
    );
}
