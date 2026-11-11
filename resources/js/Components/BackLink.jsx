import { useEffect, useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import { ArrowLeft, PanelLeftClose, PanelLeftOpen, X } from "lucide-react";
import Navigation from "@/Components/sidebar/Navigation";

const STORAGE_KEY = "qdn_sidebar_open";

function readOpen() {
    // Phones/narrow windows: the sidebar covers the page, so start closed.
    if (typeof window !== "undefined" && window.innerWidth < 900) return false;
    try {
        return localStorage.getItem(STORAGE_KEY) === "1";
    } catch {
        return false;
    }
}

function saveOpen(open) {
    try {
        localStorage.setItem(STORAGE_KEY, open ? "1" : "0");
    } catch {
        // Private mode / blocked storage: the toggle still works for this page.
    }
}

/**
 * "Back" returns to the page you actually came from, with its filters,
 * search, pagination and scroll position, the same as the browser's Back
 * button. If this page was opened directly (new tab, bookmark, refresh),
 * there is nothing to go back to, so it falls back to `href`.
 *
 * Next to it, "Show menu" opens the app sidebar (same links as the main
 * layout) on QDN pages, which render without the main layout. The choice
 * is remembered, so the sidebar stays open from page to page until hidden.
 * On wide screens it docks on the left and pushes the page over; on
 * phones it slides over the page.
 */
export default function BackLink({ href, label = "Back" }) {
    const [open, setOpen] = useState(readOpen);

    useEffect(() => {
        document.body.classList.toggle("qdn-sidebar-docked", open);
        return () => document.body.classList.remove("qdn-sidebar-docked");
    }, [open]);

    const toggle = () => {
        setOpen((value) => {
            saveOpen(!value);
            return !value;
        });
    };

    const goBack = (e) => {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return; // open in new tab
        // window.__qdnVisits is counted in app.jsx (first visit = initial load).
        if ((window.__qdnVisits ?? 0) > 1 && window.history.length > 1) {
            e.preventDefault();
            window.history.back();
        }
    };

    return (
        <>
            <div className="qdn-back-row">
                <Link href={href} className="qdn-back-link" onClick={goBack}>
                    <ArrowLeft size={16} />
                    {label}
                </Link>
                <button
                    type="button"
                    className="qdn-sidebar-toggle"
                    onClick={toggle}
                    aria-expanded={open}
                    aria-controls="qdn-sidebar"
                >
                    {open ? <PanelLeftClose size={16} /> : <PanelLeftOpen size={16} />}
                    {open ? "Hide menu" : "Show menu"}
                </button>
            </div>
            {open && <QdnSidebar onClose={toggle} />}
        </>
    );
}

function QdnSidebar({ onClose }) {
    const { display_name, appName } = usePage().props;
    const title = display_name || appName || "QDN";

    return (
        <>
            <div className="qdn-sidebar-backdrop" onClick={onClose} aria-hidden="true" />
            <aside id="qdn-sidebar" className="qdn-sidebar bg-card" aria-label="Main menu">
                <div className="qdn-sidebar-head">
                    <strong>{title}</strong>
                    <button type="button" className="qdn-sidebar-close" onClick={onClose} aria-label="Hide menu">
                        <X size={18} />
                    </button>
                </div>
                <Navigation isSidebarOpen />
            </aside>
        </>
    );
}
