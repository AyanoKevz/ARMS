<?php

use App\Models\Accreditation;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Mail::fake();
});

function auditAdmin(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

/** One audit entry written directly, for the listing tests. */
function auditEntry(string $role, array $overrides = []): AuditLog
{
    return AuditLog::create(array_merge([
        'user_id'     => null,
        'actor_name'  => $role . ' Person',
        'actor_email' => strtolower(str_replace(' ', '', $role)) . '@example.test',
        'admin_role'  => $role,
        'action'      => 'application.document_evaluated',
        'description' => 'Evaluated an application document',
        'subject_label' => 'ARMS2026-AB0001',
        'created_at'  => now(),
    ], $overrides));
}

test('a successful admin action is recorded with who, what, when and which record', function () {
    $teamLead      = auditAdmin('hcdarms2026@gmail.com');
    $accreditation = Accreditation::where('status', 'active')->firstOrFail();

    $this->actingAs($teamLead)
        ->post(route('admin.hcd.accreditations.revoke', $accreditation->id))
        ->assertSessionHas('success');

    $log = AuditLog::where('action', 'accreditation.revoked')->firstOrFail();

    expect($log->user_id)->toBe($teamLead->id);
    expect($log->admin_role)->toBe('Team Lead');
    expect($log->actor_email)->toBe('hcdarms2026@gmail.com');
    expect($log->description)->toBe('Revoked an accreditation');
    expect($log->subject_type)->toBe('Accreditation');
    expect($log->subject_id)->toBe($accreditation->id);
    expect($log->subject_label)->toBe($accreditation->accreditation_number);
    expect($log->created_at)->not->toBeNull();
});

test('a refused action leaves no entry', function () {
    $teamLead      = auditAdmin('hcdarms2026@gmail.com');
    $accreditation = Accreditation::where('status', 'active')->firstOrFail();
    $accreditation->update(['status' => 'revoked']);

    // Revoking an already-revoked accreditation is refused with an error.
    $this->actingAs($teamLead)
        ->post(route('admin.hcd.accreditations.revoke', $accreditation->id))
        ->assertSessionHas('error');

    expect(AuditLog::where('action', 'accreditation.revoked')->exists())->toBeFalse();
});

test('admin sign-ins are recorded, applicant sign-ins are not', function () {
    auth()->login(auditAdmin('oshcdevs@gmail.com'));
    auth()->logout();

    auth()->login(User::where('email', 'testprovider1@example.com')->firstOrFail());

    expect(AuditLog::where('action', 'auth.login')->pluck('admin_role')->all())->toBe(['Training Evaluator']);
    expect(AuditLog::where('action', 'auth.logout')->count())->toBe(1);
});

test('each role sees only its own role\'s entries', function () {
    auditEntry('Team Lead');
    auditEntry('Evaluator');
    auditEntry('Evaluator');
    auditEntry('Training Evaluator');
    auditEntry('Verifier');

    $cases = [
        'hcdarms2026@gmail.com' => ['Team Lead', 1],
        'data@oshc.dole.gov.ph' => ['Evaluator', 2],
        'oshcdevs@gmail.com'    => ['Training Evaluator', 1],
    ];

    foreach ($cases as $email => [$role, $count]) {
        // A fresh session per admin: AuthenticateAdminSession signs out a
        // session that changes hands between two admin accounts.
        $this->flushSession();

        // Viewing the page writes nothing, so the counts are exact.
        $json = $this->actingAs(auditAdmin($email))
            ->getJson(route('admin.hcd.audit_logs.data', ['draw' => 1, 'start' => 0, 'length' => 25]))
            ->assertOk()
            ->json();

        expect($json['recordsTotal'])->toBe($count);
        expect($json['recordsFiltered'])->toBe($count);

        foreach ($json['data'] as $row) {
            expect($row[1])->toContain($role . ' Person');
        }
    }
});

test('search, action and date filters narrow the entries on the server', function () {
    auditEntry('Evaluator', ['actor_name' => 'Maria Santos', 'created_at' => now()->subDays(10)]);
    auditEntry('Evaluator', ['actor_name' => 'Juan Cruz', 'action' => 'interview.scheduled', 'description' => 'Scheduled an interview']);

    $evaluator = auditAdmin('data@oshc.dole.gov.ph');
    $url = fn (array $q) => route('admin.hcd.audit_logs.data', array_merge(['draw' => 1, 'start' => 0, 'length' => 25], $q));

    // Case-insensitive search.
    $json = $this->actingAs($evaluator)->getJson($url(['search' => ['value' => 'maria']]))->json();
    expect($json['recordsFiltered'])->toBe(1);
    expect($json['recordsTotal'])->toBe(2);

    $json = $this->actingAs($evaluator)->getJson($url(['action_filter' => 'interview.scheduled']))->json();
    expect($json['recordsFiltered'])->toBe(1);
    expect($json['data'][0][2])->toBe('Scheduled an interview');

    $json = $this->actingAs($evaluator)->getJson($url(['date_from' => now()->subDay()->format('Y-m-d')]))->json();
    expect($json['recordsFiltered'])->toBe(1);
});

test('the page is paged on the server and never sends more than 100 rows', function () {
    foreach (range(1, 120) as $i) {
        auditEntry('Evaluator', ['subject_label' => 'ARMS2026-AB' . str_pad($i, 4, '0', STR_PAD_LEFT)]);
    }

    $json = $this->actingAs(auditAdmin('data@oshc.dole.gov.ph'))
        ->getJson(route('admin.hcd.audit_logs.data', ['draw' => 3, 'start' => 0, 'length' => 1000]))
        ->json();

    expect($json['draw'])->toBe(3);
    expect($json['recordsTotal'])->toBe(120);
    expect($json['data'])->toHaveCount(100);
});

test('the action filter offers only what the role can do, and everything to the Team Lead', function () {
    $options = function (string $email) {
        $this->flushSession();

        return $this->actingAs(auditAdmin($email))
            ->get(route('admin.hcd.audit_logs.index'))
            ->assertOk()
            ->viewData('actions');
    };

    $evaluator = $options('data@oshc.dole.gov.ph');
    expect($evaluator)->toHaveKey('application.document_evaluated');
    expect($evaluator)->toHaveKey('auth.login');
    expect($evaluator)->not->toHaveKey('ntc.document_evaluated');
    expect($evaluator)->not->toHaveKey('admin.invited');
    expect($evaluator)->not->toHaveKey('payment.requested');

    $trainingEvaluator = $options('oshcdevs@gmail.com');
    expect($trainingEvaluator)->toHaveKey('ntc.document_evaluated');
    expect($trainingEvaluator)->toHaveKey('ptr.evaluation_saved');
    expect($trainingEvaluator)->toHaveKey('account.password_changed');
    expect($trainingEvaluator)->not->toHaveKey('application.document_evaluated');
    expect($trainingEvaluator)->not->toHaveKey('interview.scheduled');

    $teamLead = $options('hcdarms2026@gmail.com');
    expect($teamLead)->toHaveKey('admin.invited');
    expect($teamLead)->toHaveKey('ntc.document_evaluated');
    expect($teamLead)->toHaveKey('application.assigned');
    expect(count($teamLead))->toBeGreaterThan(count($evaluator) + count($trainingEvaluator) - 4);
});

test('the audit trail can be viewed but not exported or printed', function () {
    $html = $this->actingAs(auditAdmin('data@oshc.dole.gov.ph'))
        ->get(route('admin.hcd.audit_logs.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('print: false');
    expect($html)->not->toContain('exportUrl');
    expect($html)->not->toContain('buttons.print.min.js');

    // And the export endpoint no longer exists.
    $this->actingAs(auditAdmin('data@oshc.dole.gov.ph'))
        ->get('/admin/hcd/audit-logs/export')
        ->assertNotFound();
});

test('the audit page and its menu item are only for the three HCD roles', function () {
    foreach (['hcdarms2026@gmail.com', 'data@oshc.dole.gov.ph', 'oshcdevs@gmail.com'] as $email) {
        $this->flushSession();

        $this->actingAs(auditAdmin($email))
            ->get(route('admin.hcd.audit_logs.index'))
            ->assertOk()
            ->assertSee('Audit Logs');

        $this->actingAs(auditAdmin($email))
            ->get(route('admin.hcd.directory.admins'))
            ->assertSee(route('admin.hcd.audit_logs.index'), false);
    }

    // A Verifier has no audit page here, and an applicant never reaches it.
    $this->flushSession();
    $this->actingAs(auditAdmin('oshc1987accreditation@gmail.com'))
        ->getJson(route('admin.hcd.audit_logs.data'))
        ->assertForbidden();

    $this->actingAs(User::where('email', 'testprovider1@example.com')->firstOrFail())
        ->get(route('admin.hcd.audit_logs.index'))
        ->assertStatus(403);
});
