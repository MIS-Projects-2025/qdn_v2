<?php

namespace App\Http\Controllers;

use App\Models\Qdn;
use App\Support\CurrentEmployee;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PeQueueController extends Controller
{
    /**
     * One combined queue for Department: PE -- originally two separate
     * pages/nav links (PE Validation Queue -> PeValidationController,
     * PE: RCA Validation -> QdnWorkflowController@peRcaIndex). Both
     * stages are "something PE needs to act on next", so they're merged
     * into a single sorted list here; the row's `stage` tells the
     * table which action/icon/route applies and PeValidationController@show
     * / QdnWorkflowController@peRcaShow (unchanged) still do the actual
     * validation/disposition work once a row is opened.
     */
    private function assertIsPe(): void
    {
        if (!CurrentEmployee::isPe()) {
            throw new HttpException(403, 'Only Department: PE can access QDN validation.');
        }
    }

    public function index(): Response
    {
        $this->assertIsPe();

        $pendingValidation = Qdn::where('status', Qdn::STATUS_PENDING_PE)
            ->get(['id', 'qdn_no', 'customer_name', 'device_name', 'classification', 'created_at'])
            ->map(fn (Qdn $qdn) => [
                'id' => $qdn->id,
                'qdn_no' => $qdn->qdn_no,
                'customer_name' => $qdn->customer_name,
                'device_name' => $qdn->device_name,
                'classification' => $qdn->classification,
                'created_at' => $qdn->created_at,
                'stage' => 'validation',
                'stage_label' => 'For PE Validation and Assigning',
                'action_label' => 'Validate',
                'action_href' => route('pe.qdn.show', $qdn->id),
            ]);

        $pendingRcaValidation = Qdn::where('status', Qdn::STATUS_FOR_PE_RCA_VALIDATION)
            ->get(['id', 'qdn_no', 'customer_name', 'device_name', 'classification', 'created_at'])
            ->map(fn (Qdn $qdn) => [
                'id' => $qdn->id,
                'qdn_no' => $qdn->qdn_no,
                'customer_name' => $qdn->customer_name,
                'device_name' => $qdn->device_name,
                'classification' => $qdn->classification,
                'created_at' => $qdn->created_at,
                'stage' => 'rca_validation',
                'stage_label' => 'For PE Validation of RCA',
                'action_label' => 'Review RCA',
                'action_href' => route('pe.rca.show', $qdn->id),
            ]);

        $qdns = $pendingValidation->concat($pendingRcaValidation)
            ->sortBy('created_at')
            ->values();

        return Inertia::render('Qdn/PeQueue/Index', [
            'qdns' => $qdns,
        ]);
    }
}
