import { useForm, Head } from "@inertiajs/react";
import EmployeeSearchSelect from "@/Components/EmployeeSearchSelect";
import BackLink from "@/Components/BackLink";
import QdnIssuanceFields, { DISPOSITIONS } from "@/Components/QdnIssuanceFields";


/**
 * Step 2 — Validate by Department: PE.
 * Props (from PeValidationController@show): qdn
 */
export default function Validate({ qdn }) {
    const { data, setData, post, processing, errors } = useForm({
        disposition: "", // one of DISPOSITIONS
        issued_to_employee_id: "",
        issued_to_name: "",
        department: "",
        station: "",
        prodline: "",
        team: "",
        validated_by: "",
        remarks: "",
    });

    // "Issued To" is a server-side type-ahead now (EmployeeSearchSelect ->
    // GET /employees/search, unscoped -- company-wide) instead of a
    // preloaded dropdown of the entire employee_masterlist. Selecting
    // fills Department/Station/ProdLine/Team in immediately from the
    // search result, no extra request needed (the old /pe/employee-lookup
    // round trip stays unused, same as before).
    function handleEmployeeSelect(employee) {
        setData((prev) => ({
            ...prev,
            issued_to_employee_id: employee?.EMPLOYID ?? "",
            issued_to_name: employee?.EMPLOYNAME ?? "",
            department: employee?.DEPARTMENT ?? "",
            station: employee?.STATION ?? "",
            prodline: employee?.PRODLINE ?? "",
            team: employee?.TEAM ?? "",
        }));
    }

    // Exactly one disposition; clicking the checked one again clears it.
    function setDisposition(value) {
        setData("disposition", data.disposition === value ? "" : value);
    }

    const isInvalid = data.disposition === "invalid";

    function handleSubmit(e) {
        e.preventDefault();
        post(`/pe/qdn/${qdn.id}/validate`);
    }

    return (
        <div className="qdn-page">
            <Head title={`Validate ${qdn.qdn_no}`} />
            <BackLink href={route("pe.queue.index")} label="Back to PE Queue" />
            <h1>PE Validation — {qdn.qdn_no}</h1>

            <QdnIssuanceFields qdn={qdn} />

            <form onSubmit={handleSubmit} className="qdn-form">
                <div className="qdn-field qdn-field-wide">
                    <h2 className="qdn-form-heading">PE Validation</h2>
                </div>

                <div className="qdn-field qdn-field-wide">
                    <label>Disposition</label>
                    <div className="qdn-checkbox-row qdn-checkbox-row-wrap">
                        {DISPOSITIONS.map((d) => (
                            <label key={d.value} className="qdn-checkbox">
                                <input
                                    type="checkbox"
                                    checked={data.disposition === d.value}
                                    onChange={() => setDisposition(d.value)}
                                />
                                {d.label}
                            </label>
                        ))}
                    </div>
                    <p className="qdn-field-hint">
                        Invalid closes the QDN. Any other disposition sends it
                        to the Issued To employee's department for RCA.
                    </p>
                    {errors.disposition && (
                        <p className="qdn-field-error">{errors.disposition}</p>
                    )}
                </div>

                {data.disposition && !isInvalid && (
                    <>
                        <div className="qdn-field">
                            <label htmlFor="issued_to_employee_id">
                                Issued To
                            </label>
                            <EmployeeSearchSelect
                                value={
                                    data.issued_to_employee_id
                                        ? {
                                              EMPLOYID:
                                                  data.issued_to_employee_id,
                                              EMPLOYNAME: data.issued_to_name,
                                              DEPARTMENT: data.department,
                                          }
                                        : null
                                }
                                onChange={handleEmployeeSelect}
                            />
                            {errors.issued_to_employee_id && (
                                <p className="qdn-field-error">
                                    {errors.issued_to_employee_id}
                                </p>
                            )}
                        </div>

                        <div className="qdn-field">
                            <label>Department</label>
                            <input
                                type="text"
                                value={data.department}
                                disabled
                                readOnly
                            />
                        </div>

                        <div className="qdn-field">
                            <label>Station</label>
                            <input
                                type="text"
                                value={data.station}
                                disabled
                                readOnly
                            />
                        </div>

                        <div className="qdn-field">
                            <label>Product Line</label>
                            <input
                                type="text"
                                value={data.prodline}
                                disabled
                                readOnly
                            />
                        </div>

                        <div className="qdn-field">
                            <label>Team Responsible</label>
                            <input
                                type="text"
                                value={data.team}
                                disabled
                                readOnly
                            />
                        </div>
                    </>
                )}

                <div className="qdn-field qdn-field-wide">
                    <label htmlFor="remarks">Remarks</label>
                    <textarea
                        id="remarks"
                        value={data.remarks}
                        onChange={(e) => setData("remarks", e.target.value)}
                    />
                </div>

                <button
                    type="submit"
                    disabled={
                        processing ||
                        !data.disposition ||
                        (!isInvalid && !data.issued_to_employee_id)
                    }
                >
                    Submit
                </button>
            </form>
        </div>
    );
}
