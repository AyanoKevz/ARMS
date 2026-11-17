<?php

namespace App\Http\Controllers\Admin\Accreditation;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\InterviewSlot;
use App\Support\AdminAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Manage Interview Slots" — the Accreditation division opens interview
 * times for OSH Practitioner and OSH Consultant applicants, who choose one
 * from the tracking page once their documents are approved.
 *
 * Several dates can be opened at once, each with any of the preset times
 * (config accreditation.interview_time_slots) and custom times. A slot holds
 * one applicant. Any admin in the division may manage them.
 */
class InterviewSlotController extends Controller
{
    private const MAX_DATES = 60;

    public function index()
    {
        $today = Carbon::today();

        $slots = InterviewSlot::with(['application.user.individualProfile', 'application.user.organizationProfile'])
            ->whereDate('slot_date', '>=', $today->toDateString())
            ->chronological()
            ->get();

        // Approved and waiting to choose a slot.
        $awaiting = Application::with(['user.individualProfile', 'user.organizationProfile', 'latestStatus.status', 'assignedEvaluator.adminProfile'])
            ->whereIn('accreditation_type_id', AdminAccess::accreditationDivisionTypes())
            ->whereHas('latestStatus.status', fn ($q) => $q->where('name', 'Scheduled for Interview'))
            ->whereDoesntHave('interview')
            ->orderBy('updated_at')
            ->get();

        return view('admin.accreditation.division.interview_slots', [
            'slots'        => $slots,
            'awaiting'     => $awaiting,
            'openCount'    => $slots->filter(fn ($s) => ! $s->isBooked() && $s->startsAt()->isFuture())->count(),
            'bookedCount'  => $slots->filter->isBooked()->count(),
            'presets'      => $this->presets(),
            'defaultVenue' => config('accreditation.interview_default_venue'),
        ]);
    }

    public function store(Request $request)
    {
        $presetKeys = array_keys($this->presets());

        // Each date carries the mode and venue chosen when it was added, so
        // one batch can mix face-to-face and online days. A repeated date
        // counts once (the first one added).
        $dates = collect((array) $request->input('dates', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['date']))
            ->unique('date')
            ->values()
            ->all();
        $request->merge(['dates' => $dates]);

        $rules = [
            'dates'          => ['required', 'array', 'min:1', 'max:' . self::MAX_DATES],
            'dates.*.date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'dates.*.mode'   => ['required', 'in:f2f,online'],
            'presets'        => ['nullable', 'array'],
            'presets.*'      => ['in:' . implode(',', $presetKeys)],
            'custom'         => ['nullable', 'array', 'max:12'],
            'custom.*.start' => ['nullable', 'date_format:H:i'],
            'custom.*.end'   => ['nullable', 'date_format:H:i'],
        ];
        $attributes = [];
        foreach ($dates as $i => $row) {
            // Online: the link Start Interview opens. Face-to-Face: an address,
            // never a link left over from switching modes.
            $rules["dates.{$i}.venue"] = ($row['mode'] ?? null) === 'online'
                ? ['required', 'string', 'max:500', 'url']
                : ['required', 'string', 'max:500', 'not_regex:#^\s*(https?://|www\.)#i'];
            $attributes["dates.{$i}.venue"] = rescue(fn () => Carbon::parse($row['date'])->format('M d, Y'), 'the chosen date', false);
        }

        $data = $request->validate($rules, [
            'dates.required'              => 'Add at least one interview date.',
            'dates.*.date.after_or_equal' => 'Interview dates cannot be in the past.',
            'dates.*.venue.required'      => 'Enter the venue or the meeting link for :attribute.',
            'dates.*.venue.url'           => 'Enter the full meeting link for the online interviews on :attribute, starting with https://.',
            'dates.*.venue.not_regex'     => 'The face-to-face interviews on :attribute need the venue address, not a meeting link.',
        ], $attributes);

        $times = $this->chosenTimes($data);

        $now     = Carbon::now();
        $created = 0;
        $skipped = 0;

        foreach ($data['dates'] as ['date' => $date, 'mode' => $mode, 'venue' => $venue]) {
            foreach ($times as [$start, $end]) {
                $startsAt = Carbon::parse("{$date} {$start}");
                $exists   = InterviewSlot::whereDate('slot_date', $date)
                    ->where('start_time', $start)
                    ->where('end_time', $end)
                    ->exists();

                if ($exists || $startsAt->lessThanOrEqualTo($now)) {
                    $skipped++;
                    continue;
                }

                InterviewSlot::create([
                    'slot_date'  => $date,
                    'start_time' => $start,
                    'end_time'   => $end,
                    'mode'       => $mode,
                    'venue'      => trim($venue),
                    'created_by' => auth()->id(),
                ]);
                $created++;
            }
        }

        if ($created === 0) {
            return back()->withInput()->with('error', 'No new slots were opened — every chosen time already exists or has already passed.');
        }

        $dateCount = count($data['dates']);
        $message   = "Opened {$created} interview " . ($created === 1 ? 'slot' : 'slots')
            . " across {$dateCount} " . ($dateCount === 1 ? 'date' : 'dates') . '.';
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (already open or already past).";
        }

        return redirect()->route('admin.accreditation.division.interviews.slots')->with('success', $message);
    }

    /** Close an open slot. A booked slot stays — it is someone's interview. */
    public function destroy(InterviewSlot $slot)
    {
        if ($slot->isBooked()) {
            return back()->with('error', 'This slot has already been chosen by an applicant and cannot be removed.');
        }

        $label = $slot->slot_date->format('M d, Y') . ', ' . $slot->timeLabel();
        $slot->delete();

        return back()->with('success', "Removed the {$label} slot.");
    }

    /** "09:00-10:30" => ['09:00:00', '10:30:00'] */
    private function presets(): array
    {
        $presets = [];
        foreach (config('accreditation.interview_time_slots', []) as [$start, $end]) {
            $presets["{$start}-{$end}"] = [$start . ':00', $end . ':00'];
        }

        return $presets;
    }

    /**
     * The preset and custom times chosen, as [start, end] H:i:s pairs.
     *
     * @throws ValidationException when none are chosen or a custom time is incomplete / reversed
     */
    private function chosenTimes(array $data): array
    {
        $presets = $this->presets();
        $times   = [];

        foreach ($data['presets'] ?? [] as $key) {
            $times[$key] = $presets[$key];
        }

        foreach ($data['custom'] ?? [] as $i => $row) {
            $start = $row['start'] ?? null;
            $end   = $row['end'] ?? null;

            if (! $start && ! $end) {
                continue;
            }

            if (! $start || ! $end) {
                throw ValidationException::withMessages(["custom.{$i}.start" => 'Each custom time needs both a start and an end.']);
            }

            if ($end <= $start) {
                throw ValidationException::withMessages(["custom.{$i}.end" => "A custom time must end after it starts ({$start} – {$end})."]);
            }

            $times["{$start}-{$end}"] = [$start . ':00', $end . ':00'];
        }

        if ($times === []) {
            throw ValidationException::withMessages(['presets' => 'Choose at least one time slot, or add a custom time.']);
        }

        return array_values($times);
    }
}
