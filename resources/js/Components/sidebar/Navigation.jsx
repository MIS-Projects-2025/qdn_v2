import { usePage } from "@inertiajs/react";
import SidebarLink from "@/Components/sidebar/SidebarLink";
import Dropdown from "@/Components/sidebar/DropDown";
import {
    LayoutDashboard,
    Home,
    FilePlus2,
    ClipboardCheck,
    Building2,
    ShieldCheck,
    FolderArchive,
    LineChart,
    Users,
    Camera,
    Wrench,
} from "lucide-react";

const icon = (Icon) => <Icon className="w-5 h-5" />;

export default function NavLinks({ isSidebarOpen }) {
    const { qdnRoles, qdnCounts } = usePage().props;
    const roles = qdnRoles ?? {};
    const counts = qdnCounts ?? {};

    const sum = (...keys) => keys.reduce((n, k) => n + (counts[k] ?? 0), 0);
    // Dropdown/SidebarLink only render a badge for a positive number.
    const badge = (n) => (n > 0 ? n : null);

    // Every employee belongs to a department, so these are always shown;
    // each page enforces its own rule (Supervisor+ for RCA/CAPA submit,
    // Section Head/Manager for Approval).
    const departmentLinks = [
        { href: route("issued.qdn.index"), label: "QDNs for My Department", notification: badge(counts.issued) },
        { href: route("qdn.rca.index"), label: "Dept: RCA", notification: badge(counts.rca) },
        { href: route("qdn.capa.index"), label: "Dept: CAPA", notification: badge(counts.capa) },
        ...(roles.isApprover
            ? [{ href: route("qdn.approval.index"), label: "Dept: Approval", notification: badge(counts.approval) }]
            : []),
    ];

    const peLinks = [
        { href: route("pe.queue.index"), label: "PE Queue" },
        { href: route("pe.reassign.index"), label: "Reassign QDN" },
    ];

    const reportLinks = [
        { href: route("qdn.compliance.index"), label: "Compliance Detail" },
        { href: route("qdn.corrective-action-tracker"), label: "Corrective Action Tracker" },
        { href: route("qdn.effectiveness-verification"), label: "Effectiveness Verification" },
    ];

    const recordLinks = [
        { href: route("qdn.records.index"), label: "QDN Records" },
        { href: route("qdn.legacy-records.index"), label: "Legacy Records" },
    ];

    // Maintenance: QA / QMS only (qdnRoles.canMaintain). One page with a
    // tab per list; these links open it on the matching tab.
    const maintenanceLinks = [
        { href: route("qdn.maintenance.index", "root-causes"), label: "Root Cause List" },
        { href: route("qdn.maintenance.index", "specs"), label: "Specs List" },
        { href: route("qdn.maintenance.index", "violations"), label: "Violation List" },
        { href: route("qdn.maintenance.index", "defects"), label: "Defect List" },
        { href: route("qdn.maintenance.index", "locations"), label: "Location List" },
        { href: route("qdn.maintenance.index", "customers"), label: "Customer List" },
        { href: route("qdn.maintenance.index", "nonconformities"), label: "Non-conformity List" },
    ];

    return (
        <nav
            className="flex flex-col flex-grow space-y-1 overflow-y-auto"
            style={{ scrollbarWidth: "none" }}
        >
            <SidebarLink
                href={route("dashboard")}
                label="My Dashboard"
                icon={icon(Home)}
                isSidebarOpen={isSidebarOpen}
            />
            <SidebarLink
                href={route("qdn.dashboard")}
                label="QDN Dashboard"
                icon={icon(LayoutDashboard)}
                isSidebarOpen={isSidebarOpen}
            />
            <SidebarLink
                href={route("qdn.fvi-oqa.index")}
                label="FVI / OQA QDNs"
                icon={icon(Camera)}
                isSidebarOpen={isSidebarOpen}
            />
            <SidebarLink
                href={route("qdn.create")}
                label="Create QDN"
                icon={icon(FilePlus2)}
                isSidebarOpen={isSidebarOpen}
            />

            <Dropdown
                label="My Department"
                icon={icon(Building2)}
                links={departmentLinks}
                notification={badge(sum("issued", "rca", "capa") + (roles.isApprover ? counts.approval ?? 0 : 0))}
                isSidebarOpen={isSidebarOpen}
            />

            {roles.isPe && (
                <Dropdown
                    label="Process Engineering"
                    icon={icon(ClipboardCheck)}
                    links={peLinks}
                    isSidebarOpen={isSidebarOpen}
                />
            )}

            {/* Supervisor / Section Head / Manager of PE, EE or QA only */}
            {roles.crossApprovalDepartment && (
                <SidebarLink
                    href={route("qdn.cross-approval.index")}
                    label="PE / EE / QA / Production Approval"
                    icon={icon(Users)}
                    notifications={counts.crossApproval ?? 0}
                    isSidebarOpen={isSidebarOpen}
                />
            )}

            {roles.isQa && (
                <SidebarLink
                    href={route("qa.verification.index")}
                    label="QA Verification"
                    icon={icon(ShieldCheck)}
                    notifications={counts.qaVerification ?? 0}
                    isSidebarOpen={isSidebarOpen}
                />
            )}

            <Dropdown
                label="Analysis"
                icon={icon(LineChart)}
                links={reportLinks}
                isSidebarOpen={isSidebarOpen}
            />

            <Dropdown
                label="Records"
                icon={icon(FolderArchive)}
                links={recordLinks}
                isSidebarOpen={isSidebarOpen}
            />

            {roles.canMaintain && (
                <Dropdown
                    label="Maintenance"
                    icon={icon(Wrench)}
                    links={maintenanceLinks}
                    isSidebarOpen={isSidebarOpen}
                />
            )}
        </nav>
    );
}
