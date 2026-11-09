<?php

namespace App\Http\Requests;

use App\Models\Qdn;
use Illuminate\Foundation\Http\FormRequest;

class StoreQdnValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // restrict to Department: PE once auth/roles are wired up
    }

    public function rules(): array
    {
        return [
            // PE now records its disposition (Rework / Split Lot /
            // Shutdown / Shipback / Use As Is / Invalid) right here at the
            // initial validation step, instead of a plain Valid/Invalid
            // choice -- PE only ever reviews a QDN once (see
            // PeValidationController@store / QdnWorkflowController@rcaStore,
            // which no longer routes back to PE after RCA).
            'disposition' => ['required', 'in:' . implode(',', Qdn::DISPOSITIONS)],
            // Issued To is only required when the QDN isn't being closed
            // out as Invalid.
            'issued_to_employee_id' => ['required_unless:disposition,' . Qdn::DISPOSITION_INVALID, 'nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
