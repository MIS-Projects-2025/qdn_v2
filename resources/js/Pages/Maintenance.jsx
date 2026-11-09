import React from "react";
import { Head } from "@inertiajs/react";

// Rendered by AuthMiddleware::checkMaintenance() with a 503 while
// system_status is "maintenance".
export default function Maintenance({ message, logoutUrl, emp_data }) {
    return (
        <>
            <Head title="Under Maintenance" />
            <div className="flex items-center justify-center min-h-screen px-6 bg-gray-100 dark:bg-gray-900">
                <div className="text-center max-w-xl">
                    <h1 className="text-5xl font-bold text-gray-800 dark:text-gray-100 mb-4">
                        Under Maintenance
                    </h1>
                    <p className="text-lg text-gray-500 dark:text-gray-400">
                        {message || "System is currently under maintenance."}
                    </p>
                    {emp_data?.emp_name && (
                        <p className="mt-2 text-sm text-gray-400">
                            Signed in as {emp_data.emp_name}
                        </p>
                    )}
                    {logoutUrl && (
                        <a
                            href={logoutUrl}
                            className="inline-block mt-6 px-4 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900"
                        >
                            Log out
                        </a>
                    )}
                </div>
            </div>
        </>
    );
}
