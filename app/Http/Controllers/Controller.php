<?php

namespace App\Http\Controllers;

use App\Models\Qdn;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class Controller
{
    /**
     * For a workflow stage's page: if the QDN has already moved past that
     * stage (e.g. someone pressed Back after approving), send the user to
     * the QDN's record page with a notice instead of a 409 error page.
     */
    protected function redirectIfNotAt(Qdn $qdn, string $status, string $stage): void
    {
        if ($qdn->status !== $status) {
            throw new HttpResponseException(
                redirect()->route('qdn.records.show', $qdn)->with(
                    'success',
                    "QDN {$qdn->qdn_no} is no longer waiting for {$stage}. It is now: {$qdn->statusLabel()}."
                )
            );
        }
    }
}
