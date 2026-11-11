<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreQdnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // wire up auth/role checks once SSO is integrated
    }

    /**
     * HTML inputs submit "" for an empty field, not null -- the
     * `nullable` rule below only skips the other rules when the value is
     * actually null, so without this an empty Lot Qty / Machine No etc.
     * would still fail `integer`/`string` validation instead of being
     * treated as "left blank." Only matters for Customer: Others, where
     * these are the only fields allowed to be blank in the first place;
     * every other customer still has them required, and this never
     * converts a present, non-empty value.
     */
    protected function prepareForValidation(): void
    {
        $blankable = ['lot_id', 'lot_qty', 'device_name', 'machine_id'];

        $this->merge(
            collect($blankable)
                ->filter(fn($key) => $this->has($key))
                ->mapWithKeys(fn($key) => [$key => $this->input($key) === '' ? null : $this->input($key)])
                ->all()
        );
    }

    public function rules(): array
    {
        $isOthers = $this->customerIsOthers();

        return [
            'customer_id' => ['required', 'integer'],
            // Customer: Others can leave Lot ID / Lot Qty / Device Name /
            // Machine No blank -- QdnController@store substitutes "N/A"
            // (0 for the numeric Lot Qty) for whichever of these come
            // through null. Every other customer keeps them required,
            // unchanged from before.
            'lot_id' => [$isOthers ? 'nullable' : 'required', 'string', 'max:100'],
            'lot_qty' => [$isOthers ? 'nullable' : 'required', 'integer', 'min:1'],
            'device_name' => [$isOthers ? 'nullable' : 'required', 'string', 'max:150'],
            'machine_id' => [$isOthers ? 'nullable' : 'required', 'integer'],
            'location_id' => ['required', 'integer'],
            'detected_at' => ['required', 'date'],
            'nonconformity_id' => ['required', 'integer'],
            'details' => ['required', 'string'],
            // JPG photos: required (1-10) when the Detection Area is Final
            // Visual Inspection or OQA, otherwise not needed (ignored).
            'photos' => [$this->requiresPhotos() ? 'required' : 'nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'mimes:jpg,jpeg', 'max:10240'],
            // No longer accepted from the client -- QdnController@store sets
            // issued_by from the authenticated user (auth()->user()->name).

            // Customer: Others has no customer-specific routing to lean
            // on, so Create QDN captures who it's issued to right away
            // instead of waiting for a separate PE Validation step --
            // see QdnController@store, which skips straight to
            // STATUS_FOR_DEPT_RCA when this is present. Only the
            // employee id is validated here; name/department/station/
            // prodline/team are never trusted from client input -- the
            // controller re-resolves all of those itself via
            // Employee::find(), same pattern as
            // PeValidationController@store's Issued To handling.
            'issued_to_employee_id' => [
                $isOthers ? 'required' : 'nullable',
                'string',
            ],
            // Customer: Others -- the department picked on Create QDN.
            // Only used to narrow the Issued To search; routing still
            // follows the selected employee's own department (see
            // withValidator() below, which rejects a mismatch).
            'issued_department' => [
                $isOthers ? 'required' : 'nullable',
                'string',
                'max:150',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'issued_department.required' => 'Select the Issued Department (required when Customer is Others).',
            'issued_to_employee_id.required' => 'Select who this QDN is issued to (required when Customer is Others).',
            'photos.required' => 'Upload at least one JPG photo for Final Visual Inspection / OQA.',
            'photos.*.mimes' => 'Photos must be JPG files.',
            'photos.*.max' => 'Each photo must be 10 MB or smaller.',
            'photos.max' => 'Upload at most 10 photos.',
        ];
    }

    /**
     * Customer: Others -- the Issued To employee must belong to the
     * Issued Department that was picked, so the QDN lands in the queue
     * the issuer expects.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->customerIsOthers()) {
                return;
            }
            $department = trim((string) $this->input('issued_department'));
            $employeeId = $this->input('issued_to_employee_id');
            if ($department === '' || !$employeeId) {
                return;
            }
            $employee = \App\Models\Masterlist\Employee::find($employeeId);
            if ($employee && strcasecmp(trim((string) $employee->DEPARTMENT), $department) !== 0) {
                $validator->errors()->add(
                    'issued_to_employee_id',
                    "{$employee->EMPLOYNAME} is in {$employee->DEPARTMENT}, not {$department}."
                );
            }
        });
    }

    /**
     * True when the submitted customer_id resolves to a Customer named
     * "Others" -- matched case-insensitively/trimmed, same convention as
     * CurrentEmployee::isPe()/isQa() use for department names, so this
     * doesn't silently break over a casing/whitespace difference in the
     * customer_list data.
     */
    private function customerIsOthers(): bool
    {
        $customerId = $this->input('customer_id');
        if (!$customerId) {
            return false;
        }

        $customerName = Customer::find($customerId)?->customer_name ?? '';

        return trim(strtolower($customerName)) === 'others';
    }

    private function requiresPhotos(): bool
    {
        $location = \App\Models\Location::find($this->input('location_id'));

        return \App\Models\Qdn::requiresPhotos($location?->location_name);
    }
}
