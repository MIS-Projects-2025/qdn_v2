import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import QdnDetailCard from '@/Components/QdnDetailCard';
import BackLink from '@/Components/BackLink';

/**
 * Read-only detail view for a QDN issued to the current user's department --
 * shows every field recorded so far, whichever workflow stage it's
 * currently sitting in (RCA / CAPA / Dept Approval / QA Verification).
 * Props (from IssuedQdnController@show): qdn
 */
export default function IssuedShow({ qdn }) {
    return (
        <div className="qdn-page">
            <Head title={`QDN ${qdn.qdn_no}`} />
            <BackLink href="/department/qdn" label="Back to QDNs for My Department" />
            <div className="qdn-record-header">
                <h1>QDN {qdn.qdn_no}</h1>
                <button type="button" className="qdn-legacy-save-pdf" onClick={() => window.print()}>
                    <Printer size={15} />
                    Save as PDF
                </button>
            </div>

            <QdnDetailCard qdn={qdn} />
        </div>
    );
}
