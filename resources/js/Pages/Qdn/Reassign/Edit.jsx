import { useForm, Head } from '@inertiajs/react';
import EmployeeSearchSelect from '@/Components/EmployeeSearchSelect';
import BackLink from '@/Components/BackLink';

/**
 * Department: PE corrects a QDN's "Issued To" employee/department.
 * Props (from QdnReassignController@edit): qdn, currentValidation, history
 *
 * currentValidation is the latest QdnValidation row (whatever it currently
 * resolves to, validation or reassignment); history is every row, oldest
 * first, so PE can see the full trail of who issued/reissued this QDN and
 * why before making another change.
 */
export default function ReassignEdit({ qdn, currentValidation, history }) {
    const { data, setData, post, processing, errors } = useForm({
        issued_to_employee_id: '',
        issued_to_name: '',
        department: '',
        remarks: '',
    });

    function handleEmployeeSelect(employee) {
        setData((prev) => ({
            ...prev,
            issued_to_employee_id: employee?.EMPLOYID ?? '',
            issued_to_name: employee?.EMPLOYNAME ?? '',
            department: employee?.DEPARTMENT ?? '',
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        post(`/pe/reassign/${qdn.id}`);
    }

    return (
        <div className="qdn-page">
            <Head title={`Reassign ${qdn.qdn_no}`} />
            <BackLink href="/pe/reassign" label="Back to Reassign Queue" />
            <h1>Reassign QDN Issuance — {qdn.qdn_no}</h1>

            <section className="qdn-readonly-summary">
                <h2>Current Issuance</h2>
                <dl>
                    <dt>Status</dt>
                    <dd>{qdn.status_label ?? qdn.status}</dd>
                    <dt>Issued Department</dt>
                    <dd>{qdn.issued_department ?? '—'}</dd>
                    <dt>Currently Issued To</dt>
                    <dd>
                        {currentValidation?.issued_to_name
                            ? `${currentValidation.issued_to_employee_id} — ${currentValidation.issued_to_name} (${currentValidation.department ?? '—'})`
                            : '—'}
                    </dd>
                </dl>
            </section>

            <form onSubmit={handleSubmit} className="qdn-form">
                <div className="qdn-field">
                    <label htmlFor="issued_to_employee_id">Reissue To</label>
                    <EmployeeSearchSelect
                        value={
                            data.issued_to_employee_id
                                ? {
                                      EMPLOYID: data.issued_to_employee_id,
                                      EMPLOYNAME: data.issued_to_name,
                                      DEPARTMENT: data.department,
                                  }
                                : null
                        }
                        onChange={handleEmployeeSelect}
                    />
                    {errors.issued_to_employee_id && (
                        <p className="qdn-field-error">{errors.issued_to_employee_id}</p>
                    )}
                </div>

                <div className="qdn-field">
                    <label>New Department</label>
                    <input type="text" value={data.department} disabled readOnly />
                </div>

                <div className="qdn-field qdn-field-wide">
                    <label htmlFor="remarks">Reason for Reassignment</label>
                    <textarea
                        id="remarks"
                        value={data.remarks}
                        onChange={(e) => setData('remarks', e.target.value)}
                    />
                    {errors.remarks && <p className="qdn-field-error">{errors.remarks}</p>}
                </div>

                <button type="submit" disabled={processing || !data.issued_to_employee_id}>
                    Reassign
                </button>
            </form>

            <section className="qdn-readonly-summary">
                <h2>Issuance History</h2>
                {history.length === 0 && <p>No history recorded.</p>}
                {history.length > 0 && (
                    <table className="qdn-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Issued To</th>
                                <th>Department</th>
                                <th>By</th>
                                <th>When</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            {history.map((row) => (
                                <tr key={row.id}>
                                    <td>{row.type === 'reassignment' ? 'Reassignment' : 'Original Validation'}</td>
                                    <td>
                                        {row.issued_to_employee_id
                                            ? `${row.issued_to_employee_id} — ${row.issued_to_name}`
                                            : '—'}
                                    </td>
                                    <td>{row.department ?? '—'}</td>
                                    <td>{row.validated_by ?? '—'}</td>
                                    <td>{row.validated_at ? new Date(row.validated_at).toLocaleString() : '—'}</td>
                                    <td>{row.remarks ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>
        </div>
    );
}
