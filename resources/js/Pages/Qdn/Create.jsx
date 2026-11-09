import { useEffect, useMemo, useState } from "react";
import { useForm, Head } from "@inertiajs/react";
import RichTextEditor from "@/Components/RichTextEditor";
import BackLink from "@/Components/BackLink";
import EmployeeSearchSelect from "@/Components/EmployeeSearchSelect";
import PhotoUpload from "@/Components/PhotoUpload";

const CLASSIFICATIONS = ["Minor", "Major", "Critical"];

/**
 * Step 1 — Create QDN.
 * Props (from QdnController@create):
 *   customers, machines, locations, nonconformities, issuedByName
 */
export default function Create({
    customers,
    machines,
    locations,
    nonconformities,
    issuedByName,
}) {
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        customer_id: "",
        lot_id: "",
        lot_qty: "",
        device_name: "",
        package_name: "", // display-only, auto-filled
        machine_id: "",
        location_id: "",
        detected_at: "",
        nonconformity_id: "",
        details: "",
        // JPG photos -- only for Final Visual Inspection / OQA.
        photos: [],
        // Only used -- and only required -- when the selected Customer is
        // "Others" (see isOthers below). issued_to_name/department/
        // station/prodline/team are display-only, same as Qdn/Validate.jsx's
        // Issued To fields -- QdnController@store re-resolves all of them
        // itself from issued_to_employee_id via Employee::find(), never
        // trusting these from the request.
        issued_to_employee_id: "",
        issued_to_name: "",
        department: "",
        station: "",
        prodline: "",
        team: "",
    });

    const [classification, setClassification] = useState(null);
    const [packageLookupPending, setPackageLookupPending] = useState(false);

    // "Others" has no customer-specific department to route to, so
    // Create QDN captures Issued To itself and (per QdnController@store)
    // skips PE Validation entirely, going straight to Dept RCA -- same
    // "Others" match StoreQdnRequest uses server-side (case-insensitive/
    // trimmed, same convention as CurrentEmployee::isPe()/isQa()).
    const isOthers = useMemo(() => {
        const selected = customers.find(
            (c) => String(c.id) === String(data.customer_id),
        );
        return (
            (selected?.customer_name ?? "").trim().toLowerCase() === "others"
        );
    }, [customers, data.customer_id]);

    // Auto-fill Classification checkboxes whenever the selected
    // nonconformity changes (1-Minor, 2-Major, 3-Critical).
    useEffect(() => {
        const selected = nonconformities.find(
            (n) => String(n.id) === String(data.nonconformity_id),
        );
        const map = { 1: "Minor", 2: "Major", 3: "Critical" };
        // Keyed off the server-normalized category_number (see
        // QdnController@create / Nonconformity::categoryNumber()) instead
        // of the raw nonconformity_category value, which may be stored as
        // "1-Minor" rather than a bare 1.
        setClassification(
            selected?.category_number
                ? (map[selected.category_number] ?? null)
                : null,
        );
    }, [data.nonconformity_id, nonconformities]);

    // Clear out any previously-picked employee if the customer is
    // changed away from "Others" -- avoids silently resubmitting a stale
    // Issued To that's no longer visible on the form.
    useEffect(() => {
        if (!isOthers && data.issued_to_employee_id) {
            setData((prev) => ({
                ...prev,
                issued_to_employee_id: "",
                issued_to_name: "",
                department: "",
                station: "",
                prodline: "",
                team: "",
            }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isOthers]);

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

    // Auto-fill Package Name once Device Name is entered, by looking it
    // up against qdn_db.package_list (devicename -> package_type).
    function handleDeviceNameBlur() {
        if (!data.device_name) {
            setData("package_name", "");
            return;
        }
        setPackageLookupPending(true);
        fetch(
            `/qdn/package-lookup?device_name=${encodeURIComponent(data.device_name)}`,
            {
                headers: { Accept: "application/json" },
            },
        )
            .then((res) => {
                // Previously this went straight to res.json() with no
                // .catch() below -- if the session had expired or anything
                // else made the server respond with HTML instead of JSON
                // (e.g. a redirect to the login page), res.json() would
                // throw and the failure would vanish as an unhandled
                // promise rejection: Package Name would just silently stay
                // blank with nothing in the UI to explain why.
                if (!res.ok) {
                    throw new Error(
                        `Package lookup failed: HTTP ${res.status}`,
                    );
                }
                return res.json();
            })
            .then((json) => setData("package_name", json.package_name ?? ""))
            .catch((err) => {
                console.error(err);
                setData("package_name", "");
            })
            .finally(() => setPackageLookupPending(false));
    }

    const needsPhotos = useMemo(
        () => !!locations.find((l) => String(l.id) === String(data.location_id))?.requires_photos,
        [locations, data.location_id],
    );

    function handleSubmit(e) {
        e.preventDefault();
        // Only send photos for Final Visual Inspection / OQA.
        transform((d) => (needsPhotos ? d : { ...d, photos: [] }));
        post("/qdn", {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    }

    return (
        <div className="qdn-page">
            <Head title="Create QDN" />
            <BackLink href="/qdn/dashboard" label="Back to QDN Dashboard" />
            <h1>Create QDN</h1>

            <form onSubmit={handleSubmit} className="qdn-form">
                <div className="qdn-field">
                    <label>QDN No</label>
                    <input
                        type="text"
                        value="(auto-generated on submit)"
                        disabled
                        readOnly
                    />
                </div>

                <div className="qdn-field">
                    <label htmlFor="customer_id">Customer</label>
                    <select
                        id="customer_id"
                        value={data.customer_id}
                        onChange={(e) => setData("customer_id", e.target.value)}
                    >
                        <option value="">-- Select Customer --</option>
                        {customers.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.customer_name}
                            </option>
                        ))}
                    </select>
                    {errors.customer_id && (
                        <p className="qdn-field-error">{errors.customer_id}</p>
                    )}
                    {isOthers && (
                        <p className="qdn-hint">
                            Lot ID, Lot Qty, Device Name, Package Name, and
                            Machine No can be left blank for Others -- blank
                            ones are saved as "N/A" (Lot Qty as 0).
                        </p>
                    )}
                </div>

                <div className="qdn-field">
                    <label htmlFor="lot_id">
                        Lot ID{isOthers ? " (optional)" : ""}
                    </label>
                    <input
                        id="lot_id"
                        type="text"
                        value={data.lot_id}
                        onChange={(e) => setData("lot_id", e.target.value)}
                    />
                    {errors.lot_id && (
                        <p className="qdn-field-error">{errors.lot_id}</p>
                    )}
                </div>

                <div className="qdn-field">
                    <label htmlFor="lot_qty">
                        Lot Qty{isOthers ? " (optional)" : ""}
                    </label>
                    <input
                        id="lot_qty"
                        type="number"
                        min="1"
                        value={data.lot_qty}
                        onChange={(e) => setData("lot_qty", e.target.value)}
                    />
                    {errors.lot_qty && (
                        <p className="qdn-field-error">{errors.lot_qty}</p>
                    )}
                </div>

                <div className="qdn-field">
                    <label htmlFor="device_name">
                        Device Name{isOthers ? " (optional)" : ""}
                    </label>
                    <input
                        id="device_name"
                        type="text"
                        value={data.device_name}
                        onChange={(e) => setData("device_name", e.target.value)}
                        onBlur={handleDeviceNameBlur}
                    />
                    {errors.device_name && (
                        <p className="qdn-field-error">{errors.device_name}</p>
                    )}
                </div>

                <div className="qdn-field">
                    <label htmlFor="package_name">Package Name</label>
                    <input
                        id="package_name"
                        type="text"
                        value={
                            packageLookupPending
                                ? "Looking up…"
                                : data.package_name
                        }
                        disabled
                        readOnly
                    />
                </div>

                <div className="qdn-field">
                    <label htmlFor="machine_id">
                        Machine No{isOthers ? " (optional)" : ""}
                    </label>
                    <select
                        id="machine_id"
                        value={data.machine_id}
                        onChange={(e) => setData("machine_id", e.target.value)}
                    >
                        <option value="">-- Select Machine --</option>
                        {machines.map((m) => (
                            <option key={m.id} value={m.id}>
                                {m.machine_num}
                            </option>
                        ))}
                    </select>
                    {errors.machine_id && (
                        <p className="qdn-field-error">{errors.machine_id}</p>
                    )}
                </div>

                <div className="qdn-field">
                    <label htmlFor="location_id">Detection Area</label>
                    <select
                        id="location_id"
                        value={data.location_id}
                        onChange={(e) => setData("location_id", e.target.value)}
                    >
                        <option value="">-- Select Detection Area --</option>
                        {locations.map((l) => (
                            <option key={l.id} value={l.id}>
                                {l.location_name}
                            </option>
                        ))}
                    </select>
                    {errors.location_id && (
                        <p className="qdn-field-error">{errors.location_id}</p>
                    )}
                </div>

                {needsPhotos && (
                    <div className="qdn-field qdn-field-wide">
                        <label>Photos (required for Final Visual Inspection / OQA)</label>
                        <PhotoUpload
                            value={data.photos}
                            onChange={(files) => setData("photos", files)}
                            error={
                                errors.photos ||
                                Object.entries(errors).find(([k]) => k.startsWith("photos."))?.[1]
                            }
                        />
                    </div>
                )}

                <div className="qdn-field">
                    <label htmlFor="detected_at">
                        Date &amp; Time of Detection
                    </label>
                    <input
                        id="detected_at"
                        type="datetime-local"
                        value={data.detected_at}
                        onChange={(e) => setData("detected_at", e.target.value)}
                    />
                    {errors.detected_at && (
                        <p className="qdn-field-error">{errors.detected_at}</p>
                    )}
                </div>

                <div className="qdn-field">
                    <label htmlFor="nonconformity_id">
                        Type of Non-Conformity / Failure Mode
                    </label>
                    <select
                        id="nonconformity_id"
                        value={data.nonconformity_id}
                        onChange={(e) =>
                            setData("nonconformity_id", e.target.value)
                        }
                    >
                        <option value="">-- Select Type --</option>
                        {nonconformities.map((n) => (
                            <option key={n.id} value={n.id}>
                                {n.nonconformity_name}
                            </option>
                        ))}
                    </select>
                    {errors.nonconformity_id && (
                        <p className="qdn-field-error">
                            {errors.nonconformity_id}
                        </p>
                    )}
                </div>

                <div className="qdn-field">
                    <label>Classification</label>
                    <div className="qdn-checkbox-row">
                        {CLASSIFICATIONS.map((c) => (
                            <label key={c} className="qdn-checkbox">
                                <input
                                    type="checkbox"
                                    checked={classification === c}
                                    disabled
                                    readOnly
                                />
                                {c}
                            </label>
                        ))}
                    </div>
                </div>

                <div className="qdn-field qdn-field-wide">
                    <label htmlFor="details">
                        Details of Non-Conformity / Failure Mode
                    </label>
                    <RichTextEditor
                        value={data.details}
                        onChange={(html) => setData("details", html)}
                        error={errors.details}
                    />
                </div>

                {isOthers && (
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
                            <p className="qdn-hint">
                                Customer is "Others" -- there's no customer
                                record to route this to a department
                                automatically, so select who it's issued to.
                                This QDN will skip PE Validation and go straight
                                to that employee's department for RCA.
                            </p>
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

                <div className="qdn-field">
                    <label>Issued By</label>
                    {/* Server-derived from the SSO-authenticated user — see
                        QdnController@store, which reads $request->user()->name
                        rather than trusting client input. */}
                    <input
                        type="text"
                        value={issuedByName ?? ""}
                        disabled
                        readOnly
                    />
                </div>

                <button
                    type="submit"
                    disabled={
                        processing || (isOthers && !data.issued_to_employee_id)
                    }
                >
                    Submit
                </button>
            </form>
        </div>
    );
}
