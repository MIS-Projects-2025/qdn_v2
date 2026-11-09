import { useEffect, useState } from "react";
import axios from "axios";

/**
 * Photos attached to a QDN (Final Visual Inspection / OQA), loaded from
 * GET /qdn/{id}/photos. Renders nothing when the QDN has none. Prints in
 * the Save as PDF output.
 */
export default function QdnPhotos({ qdnId, asField = false }) {
    const [photos, setPhotos] = useState([]);

    useEffect(() => {
        if (!qdnId) return;
        let alive = true;
        axios
            .get(route("qdn.photos.index", qdnId))
            .then((r) => alive && setPhotos(r.data?.photos ?? []))
            .catch(() => alive && setPhotos([]));
        return () => {
            alive = false;
        };
    }, [qdnId]);

    if (!photos.length) return null;

    const grid = (
            <div className="qdn-photo-grid qdn-photo-grid-view">
                {photos.map((p) => (
                    <a key={p.id} href={p.url} target="_blank" rel="noreferrer" className="qdn-photo-thumb qdn-photo-view">
                        <img src={p.url} alt={p.name || "QDN photo"} loading="eager" />
                        {p.name && <figcaption>{p.name}</figcaption>}
                    </a>
                ))}
            </div>
    );

    // asField: render as a wide form field (label + grid), used inside
    // form-style pages such as PE Validation.
    if (asField) {
        return (
            <div className="qdn-field qdn-field-wide qdn-photos-section">
                <label>Photos</label>
                {grid}
            </div>
        );
    }

    return (
        <section className="qdn-readonly-summary qdn-photos-section">
            <h2>Photos</h2>
            {grid}
        </section>
    );
}
