<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Accreditation;
use App\Services\PractitionerApplicationService;
use App\Support\PractitionerForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Renewal of an OSH Practitioner / OSH Consultant accreditation, in the
 * practitioner portal: the DOLE-BWC AF-PCR-A2 form.
 *
 * The form only, for now. It comes filled in with the answers of the
 * application that earned the accreditation; what the AF-PCR-A2 does not ask
 * again (date of birth, education, references…) is shown locked.
 * "Review Details" renders the AF-PCR-A2 from it. It cannot be submitted
 * until the renewal process is built.
 */
class PractitionerRenewalController extends Controller
{
    /** Accreditation statuses that may be renewed (not revoked, archived or voided). */
    public const RENEWABLE = ['active', 'expired'];

    public function index(Request $request): View
    {
        $accreditation = $this->accreditation($request);
        $renewable     = $accreditation && $accreditation->application && in_array($accreditation->status, self::RENEWABLE, true);

        $values = null;
        if ($renewable) {
            ['core' => $core, 'data' => $data] = PractitionerForm::storedValues($accreditation->application);
            $values = ['person' => $core] + $data;
        }

        return view('applicant.practitioner.renewal', [
            'accreditation'     => $accreditation,
            'renewable'         => $renewable,
            'details'           => $accreditation ? PractitionerForm::accreditationDetails($accreditation) : null,
            'pracValues'        => $values,
            'email'             => $request->user()->email,
            'certificateOnFile' => $this->certificatePath($accreditation) !== null,
        ]);
    }

    /**
     * Render the AF-PCR-A2 from what is in the form. Nothing is stored; the
     * photo is dropped into the photo box by the browser, as on registration.
     */
    public function preview(Request $request): JsonResponse
    {
        $accreditation = $this->accreditation($request);
        abort_unless($accreditation && in_array($accreditation->status, self::RENEWABLE, true), 404);

        $request->validate([
            'person'       => ['nullable', 'array'],
            'practitioner' => ['nullable', 'array'],
        ]);

        $core = collect(PractitionerApplicationService::CORE_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => is_string($value = $request->input("person.{$field}")) ? $value : null])
            ->all();

        $form = PractitionerForm::renewalFromInput(
            $core,
            (array) $request->input('practitioner', []),
            $request->user()->email,
            $accreditation,
        );

        return response()->json([
            'html' => view('practitioner.renewal-form.viewer', ['form' => $form])->render(),
        ]);
    }

    /** The applicant's last issued Certificate of Accreditation (checklist item 3), as uploaded by the admins. */
    public function certificate(Request $request): BinaryFileResponse
    {
        $path = $this->certificatePath($this->accreditation($request));
        abort_unless($path, 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /** The user's latest OSH Practitioner / Consultant accreditation. */
    private function accreditation(Request $request): ?Accreditation
    {
        $types = array_map('intval', [config('accreditation.types.practitioner'), config('accreditation.types.consultant')]);

        return Accreditation::where('user_id', $request->user()->id)
            ->whereIn('accreditation_type_id', $types)
            ->with('application')
            ->latest('id')
            ->first();
    }

    private function certificatePath(?Accreditation $accreditation): ?string
    {
        $path = $accreditation?->scanned_certificate;

        return $path && Storage::disk('local')->exists($path) ? $path : null;
    }
}
