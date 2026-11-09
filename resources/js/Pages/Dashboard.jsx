import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { ThemeContext } from "@/Components/ThemeContext";
import { ClassificationBadge, StatusBadge } from "@/Components/Badges";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { useContext, useState } from "react";
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    Tooltip,
    Legend,
} from "chart.js";
import { Bar, Doughnut } from "react-chartjs-2";
import { FileWarning, FolderOpen, Search } from "lucide-react";

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend);

// Same hues as the classification badges in qdn.css, so a "Major" slice
// and a "Major" badge read as the same thing.
const CLASSIFICATION_COLORS = {
    Minor: "#1d4ed8",
    Major: "#ea580c",
    Critical: "#b91c1c",
    Unclassified: "#9ca3af",
};
const BAR_COLOR = "#1d4ed8";

function useChartTheme() {
    const { theme } = useContext(ThemeContext) ?? {};
    const dark = theme === "dark";
    return {
        text: dark ? "#d1d5db" : "#374151",
        grid: dark ? "rgba(255,255,255,0.08)" : "rgba(0,0,0,0.06)",
        surface: dark ? "#111827" : "#ffffff",
    };
}

function Card({ title, subtitle, children, className = "" }) {
    return (
        <section className={`rounded-xl border border-border bg-card p-4 sm:p-5 shadow-sm ${className}`}>
            {title && (
                <header className="mb-3">
                    <h2 className="text-base font-semibold text-foreground">{title}</h2>
                    {subtitle && <p className="text-xs text-muted-foreground">{subtitle}</p>}
                </header>
            )}
            {children}
        </section>
    );
}

function Stat({ label, value, icon: Icon }) {
    return (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm flex items-center gap-4">
            <div className="h-11 w-11 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                <Icon className="h-5 w-5" />
            </div>
            <div>
                <div className="text-2xl font-bold text-foreground tabular-nums">{value}</div>
                <div className="text-xs text-muted-foreground">{label}</div>
            </div>
        </div>
    );
}

function Empty({ children = "No QDNs yet." }) {
    return <p className="py-10 text-center text-sm text-muted-foreground">{children}</p>;
}

function PersonalCharts({ personal }) {
    const t = useChartTheme();

    const axis = {
        ticks: { color: t.text, precision: 0 },
        grid: { color: t.grid },
        border: { display: false },
    };

    const ncTop = personal.byNonconformity.slice(0, 10);
    const ncData = {
        labels: ncTop.map((r) => r.label),
        datasets: [{ label: "QDNs", data: ncTop.map((r) => r.total), backgroundColor: BAR_COLOR, borderRadius: 4, maxBarThickness: 22 }],
    };
    const ncOptions = {
        indexAxis: "y",
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { ...axis, beginAtZero: true }, y: { ...axis, grid: { display: false } } },
    };

    const classEntries = Object.entries(personal.byClassification).filter(([, v]) => v > 0);
    const classData = {
        labels: classEntries.map(([k]) => k),
        datasets: [{
            data: classEntries.map(([, v]) => v),
            backgroundColor: classEntries.map(([k]) => CLASSIFICATION_COLORS[k]),
            borderColor: t.surface,
            borderWidth: 2,
        }],
    };
    const classOptions = {
        maintainAspectRatio: false,
        cutout: "62%",
        plugins: { legend: { position: "bottom", labels: { color: t.text, usePointStyle: true, boxWidth: 8 } } },
    };

    const monthData = {
        labels: personal.byMonth.map((m) => m.label),
        datasets: [{ label: "QDNs issued", data: personal.byMonth.map((m) => m.total), backgroundColor: BAR_COLOR, borderRadius: 4, maxBarThickness: 28 }],
    };
    const monthOptions = {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { ...axis, grid: { display: false } }, y: { ...axis, beginAtZero: true } },
    };

    return (
        <>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Stat label="QDNs issued to me" value={personal.total} icon={FileWarning} />
                <Stat label="Still open" value={personal.open} icon={FolderOpen} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <Card title="By Non-Conformity" subtitle="Top 10, issued to me" className="lg:col-span-2">
                    {ncTop.length ? (
                        <div style={{ height: Math.max(180, ncTop.length * 34) }}>
                            <Bar data={ncData} options={ncOptions} />
                        </div>
                    ) : <Empty />}
                </Card>
                <Card title="By Classification">
                    {classEntries.length ? (
                        <div className="h-64"><Doughnut data={classData} options={classOptions} /></div>
                    ) : <Empty />}
                </Card>
            </div>

            <Card title="By Date Issued" subtitle="Last 12 months">
                {personal.total ? (
                    <div className="h-64"><Bar data={monthData} options={monthOptions} /></div>
                ) : <Empty />}
            </Card>

            <Card title="My Recent QDNs">
                {personal.recent.length ? (
                    <QdnTable rows={personal.recent} showDetection={false} />
                ) : <Empty />}
            </Card>
        </>
    );
}

function QdnTable({ rows, showDetection = true }) {
    return (
        <div className="overflow-x-auto -mx-4 sm:mx-0">
            <table className="min-w-full text-sm">
                <thead>
                    <tr className="text-left text-xs uppercase tracking-wide text-muted-foreground border-b border-border">
                        <th className="px-3 py-2 font-semibold">QDN No.</th>
                        {showDetection && <th className="px-3 py-2 font-semibold">Detection Area</th>}
                        <th className="px-3 py-2 font-semibold">Non-Conformity</th>
                        <th className="px-3 py-2 font-semibold">Classification</th>
                        <th className="px-3 py-2 font-semibold">Date Issued</th>
                        <th className="px-3 py-2 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((q) => (
                        <tr key={q.id} className="border-b border-border/60 hover:bg-accent/50">
                            <td className="px-3 py-2 font-medium whitespace-nowrap">
                                <Link href={route("qdn.records.show", q.id)} className="text-primary hover:underline">
                                    {q.qdn_no}
                                </Link>
                            </td>
                            {showDetection && <td className="px-3 py-2">{q.detection_area || "—"}</td>}
                            <td className="px-3 py-2">{q.nonconformity || "—"}</td>
                            <td className="px-3 py-2"><ClassificationBadge classification={q.classification} /></td>
                            <td className="px-3 py-2 whitespace-nowrap tabular-nums">{q.date_issued || "—"}</td>
                            <td className="px-3 py-2"><StatusBadge status={q.status} label={q.status_label} /></td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function DepartmentQdns({ approveeCount, page, filters }) {
    const [form, setForm] = useState({
        search: filters.search ?? "",
        classification: filters.classification ?? "",
        from: filters.from ?? "",
        to: filters.to ?? "",
    });

    const apply = (e) => {
        e?.preventDefault();
        const params = Object.fromEntries(Object.entries(form).filter(([, v]) => v));
        router.get(route("dashboard"), params, { preserveState: true, preserveScroll: true, replace: true });
    };
    const reset = () => {
        setForm({ search: "", classification: "", from: "", to: "" });
        router.get(route("dashboard"), {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const input = "h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring";

    return (
        <Card
            title="QDNs of Employees I Approve"
            subtitle={`All QDNs issued to the ${approveeCount} employee${approveeCount === 1 ? "" : "s"} who list you as approver`}
        >
            <form onSubmit={apply} className="flex flex-wrap items-end gap-2 mb-4">
                <label className="flex flex-col gap-1 text-xs text-muted-foreground flex-1 min-w-[180px]">
                    Search
                    <div className="relative">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <input
                            className={`${input} w-full pl-8`}
                            placeholder="QDN no, detection area, non-conformity"
                            value={form.search}
                            onChange={(e) => setForm({ ...form, search: e.target.value })}
                        />
                    </div>
                </label>
                <label className="flex flex-col gap-1 text-xs text-muted-foreground">
                    Classification
                    <select className={input} value={form.classification} onChange={(e) => setForm({ ...form, classification: e.target.value })}>
                        <option value="">All</option>
                        <option>Minor</option>
                        <option>Major</option>
                        <option>Critical</option>
                    </select>
                </label>
                <label className="flex flex-col gap-1 text-xs text-muted-foreground">
                    From
                    <input type="date" className={input} value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} />
                </label>
                <label className="flex flex-col gap-1 text-xs text-muted-foreground">
                    To
                    <input type="date" className={input} value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} />
                </label>
                <button type="submit" className="h-9 px-4 rounded-md bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90">
                    Filter
                </button>
                <button type="button" onClick={reset} className="h-9 px-3 rounded-md border border-input text-sm hover:bg-accent">
                    Reset
                </button>
            </form>

            {page.data.length ? (
                <>
                    <QdnTable rows={page.data} />
                    <div className="flex flex-wrap items-center justify-between gap-2 mt-4 text-xs text-muted-foreground">
                        <span>
                            Showing {page.from}–{page.to} of {page.total}
                        </span>
                        <div className="flex flex-wrap gap-1">
                            {page.links.map((l, i) => (
                                <Link
                                    key={i}
                                    href={l.url || "#"}
                                    preserveScroll
                                    preserveState
                                    disabled={!l.url}
                                    as={l.url ? "a" : "span"}
                                    className={`min-w-8 h-8 px-2 inline-flex items-center justify-center rounded-md border text-xs ${
                                        l.active
                                            ? "bg-primary text-primary-foreground border-primary"
                                            : "border-input hover:bg-accent"
                                    } ${!l.url ? "opacity-40 pointer-events-none" : ""}`}
                                    dangerouslySetInnerHTML={{ __html: l.label }}
                                />
                            ))}
                        </div>
                    </div>
                </>
            ) : (
                <Empty>No QDNs match these filters.</Empty>
            )}
        </Card>
    );
}

export default function Dashboard({ personal, isLeader, approveeCount = 0, departmentQdns, filters = {} }) {
    const { emp_data } = usePage().props;
    const firstName = emp_data?.emp_firstname || emp_data?.emp_name || "";

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="space-y-4 max-w-7xl mx-auto">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Dashboard</h1>
                    <p className="text-sm text-muted-foreground">
                        {firstName ? `Hi ${firstName}, here` : "Here"} are the QDNs issued to you.
                    </p>
                </div>

                {personal ? <PersonalCharts personal={personal} /> : <Card><Empty /></Card>}

                {isLeader && departmentQdns && (
                    <DepartmentQdns approveeCount={approveeCount} page={departmentQdns} filters={filters} />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
