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
        $applications = Application::with(['user', 'applicationType'])
            ->where('status', 'Pending')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.hcd.applications.pending', compact('applications'));
    }

    /**
     * Update an application's status to Under Evaluation.
     */
    public function updateToEvaluation(Request $request, Application $application)
    {
        // Enforce basic validation if needed
        if ($application->status !== 'Pending') {
            return back()->with('error', 'Only pending applications can be moved to evaluation.');
        }

        $application->status = 'Under Evaluation';
        // Assign the evaluating admin if necessary:
        // $application->handled_by_admin_id = auth()->user()->id;
        $application->save();

        return back()->with('success', 'Application ' . $application->tracking_number . ' is now Under Evaluation.');
    }
}
