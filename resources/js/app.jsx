import React from "react";
import "../css/app.css";
import "../css/qdn.css";
import "./bootstrap";

import { createRoot } from "react-dom/client";
import { createInertiaApp, router } from "@inertiajs/react";

// Counts in-app page visits in this tab so BackLink knows whether there
// is a previous page to return to (see Components/BackLink.jsx).
// After a form submit (POST/PUT/PATCH/DELETE), the previous history entry
// is the form just submitted, so Back must not return there: restart the
// count and BackLink falls back to its own href (e.g. the queue list).
window.__qdnVisits = 0;
let afterSubmit = false;
router.on("before", (event) => {
    const method = String(event.detail.visit?.method || "get").toLowerCase();
    if (method !== "get") afterSubmit = true;
});
// Show controller flash messages (->with('success'|'error', ...)) as toasts
// on every page.
router.on("navigate", (event) => {
    const flash = event.detail.page?.props?.flash;
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

router.on("navigate", () => {
    if (afterSubmit) {
        afterSubmit = false;
        window.__qdnVisits = 1;
        return;
    }
    window.__qdnVisits += 1;
});
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ThemeProvider, ThemeContext } from "../js/Components/ThemeContext";
import { Toaster, toast } from "sonner";

const rawAppName = import.meta.env.VITE_APP_NAME || "Laravel";
const appName = rawAppName
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/\b\w/g, (char) => char.toUpperCase());

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.jsx"),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        const emp_data =
            props.initialPage?.props?.emp_data ||
            props.initialPage?.props?.auth?.emp_data;

        localStorage.removeItem("authify-token");

        if (emp_data?.token && emp_data?.emp_id) {
            setTimeout(() => {
                localStorage.setItem("authify-token", emp_data.token);
            }, 0);
        }

        root.render(
            <React.StrictMode>
                <ThemeProvider>
                    <ThemeContext.Consumer>
                        {({ theme }) => (
                            <>
                                <Toaster
                                    richColors
                                    position="top-center"
                                    theme={theme}
                                />
                                <App {...props} />
                            </>
                        )}
                    </ThemeContext.Consumer>
                </ThemeProvider>
            </React.StrictMode>,
        );
    },
});
