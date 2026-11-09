import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import QdnDetailCard from '@/Components/QdnDetailCard';
import BackLink from '@/Components/BackLink';

/**
 * Every field recorded for a single QDN -- base details, the original PE
 * validation decision, and whichever of RCA / CAPA / QA Verification has
 * happened so far. Props (from QdnController@recordShow): qdn (includes
 * qdn.validation, the qdn_validations row, when one exists)
 */
export default function RecordsShow({ qdn }) {
    return (
        <div className="qdn-page">
            <Head title={`QDN Record — ${qdn.qdn_no}`} />
            <BackLink href="/qdn/records" label="Back to QDN Records" />
            <div className="qdn-record-header">
                <h1>QDN Record — {qdn.qdn_no}</h1>
                {/* Browser print dialog -> "Save as PDF". Hidden in the printout. */}
                <button type="button" className="qdn-legacy-save-pdf" onClick={() => window.print()}>
                    <Printer size={15} />
                    Save as PDF
                </button>
            </div>

            <QdnDetailCard qdn={qdn} />
        </div>
    );
}
