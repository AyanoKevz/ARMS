<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    /**
     * Display a listing of pending applications.
     */
    public function pending()
    {
        // Get applications that haven't been evaluated yet
        // Get applications that are still 'Submitted' based on their latest status log
        $applications = Application::with(['user.organizationProfile', 'user.individualProfile', 'accreditationType', 'latestStatus.status'])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->where('name', 'Submitted');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.hcd.pending', compact('applications'));
    }

    /**
     * Update an application's status to Under Evaluation.
     */
    public function updateToEvaluation(Request $request, Application $application)
    {
        // Use relationship logic for status with null-safe operators
        $latestStatusName = $application->latestStatus?->status?->name;
        
        if ($latestStatusName !== 'Submitted') {
            return back()->with('error', 'Only newly submitted applications can be moved to evaluation.');
        }

        $underEvaluationStatus = \App\Models\ApplicationStatus::where('name', 'Under Evaluation')->first();

        if (!$underEvaluationStatus) {
            return back()->with('error', 'Configuration error: "Under Evaluation" status not found.');
        }

        // Assign the evaluating admin
        $application->handled_by_admin_id = auth()->id();
        $application->save();

        if ($underEvaluationStatus) {
            \App\Models\ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id'      => $underEvaluationStatus->id,
                'updated_by'     => auth()->id(),
                'remarks'        => 'Application is now being evaluated.',
            ]);
        }

        return back()->with('success', 'Application ' . $application->tracking_number . ' is now Under Evaluation.');
    }
}
