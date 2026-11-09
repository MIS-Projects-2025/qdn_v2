import { Link } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";

/**
 * "Back" returns to the page you actually came from, with its filters,
 * search, pagination and scroll position, the same as the browser's Back
 * button. If this page was opened directly (new tab, bookmark, refresh),
 * there is nothing to go back to, so it falls back to `href`.
 */
export default function BackLink({ href, label = "Back" }) {
    const goBack = (e) => {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return; // open in new tab
        // window.__qdnVisits is counted in app.jsx (first visit = initial load).
        if ((window.__qdnVisits ?? 0) > 1 && window.history.length > 1) {
            e.preventDefault();
            window.history.back();
        }
    };

    return (
        <Link href={href} className="qdn-back-link" onClick={goBack}>
            <ArrowLeft size={16} />
            {label}
        </Link>
    );
}
