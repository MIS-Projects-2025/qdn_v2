import { useEffect, useMemo, useRef } from "react";
import { ImagePlus, X } from "lucide-react";

/**
 * JPG photo picker with thumbnails, used on Create QDN when the Detection
 * Area is Final Visual Inspection or OQA. `value` is an array of File.
 */
export default function PhotoUpload({ value = [], onChange, max = 10, error }) {
    const inputRef = useRef(null);
    const previews = useMemo(() => value.map((f) => URL.createObjectURL(f)), [value]);
    useEffect(() => () => previews.forEach((u) => URL.revokeObjectURL(u)), [previews]);

    function add(e) {
        const picked = Array.from(e.target.files || []).filter((f) =>
            /\.jpe?g$/i.test(f.name) || f.type === "image/jpeg",
        );
        onChange([...value, ...picked].slice(0, max));
        e.target.value = "";
    }

    return (
        <div className="qdn-photo-upload">
            <div className="qdn-photo-grid">
                {value.map((f, i) => (
                    <figure key={`${f.name}-${i}`} className="qdn-photo-thumb">
                        <img src={previews[i]} alt={f.name} />
                        <button
                            type="button"
                            className="qdn-photo-remove"
                            aria-label={`Remove ${f.name}`}
                            onClick={() => onChange(value.filter((_, j) => j !== i))}
                        >
                            <X size={14} />
                        </button>
                        <figcaption>{f.name}</figcaption>
                    </figure>
                ))}
                {value.length < max && (
                    <button type="button" className="qdn-photo-add" onClick={() => inputRef.current?.click()}>
                        <ImagePlus size={22} />
                        <span>Add JPG photo</span>
                    </button>
                )}
            </div>
            <input
                ref={inputRef}
                type="file"
                accept=".jpg,.jpeg,image/jpeg"
                multiple
                hidden
                onChange={add}
            />
            <p className="qdn-hint">JPG only, up to {max} photos, 10 MB each.</p>
            {error && <p className="qdn-field-error">{error}</p>}
        </div>
    );
}
