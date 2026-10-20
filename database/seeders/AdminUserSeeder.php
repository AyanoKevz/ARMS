<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\AdminProfile;
use App\Models\AdminRole;
use App\Models\Division;

class AdminUserSeeder extends Seeder
{
    /**
     * Every seeded admin account: email, division, role, name and position.
     * All share the same initial password — see seedAdmin().
     */
    private const ADMINS = [
        // ── HCD: Team Leads ───────────────────────────────────────────────────
        ['hcdarms2026@gmail.com',                  'HCD', 'Team Lead',          'HCD',          'Team Lead',  'LSO III'],
        ['mariarevelyn.galura@oshc.dole.gov.ph',   'HCD', 'Team Lead',          'Maria Revelyn', 'Galura',    'LSO III'],

        // ── HCD: Evaluators ───────────────────────────────────────────────────
        ['data@oshc.dole.gov.ph',                  'HCD', 'Evaluator',          'HCD',          'Evaluator',  'LSO III'],
        ['reynold.staana@oshc.dole.gov.ph',        'HCD', 'Evaluator',          'Reynold',      'Sta. Ana',   'LSO III'],

        // ── HCD: Training Evaluators ──────────────────────────────────────────
        ['oshcdevs@gmail.com',                     'HCD', 'Training Evaluator', 'OSHC',         'Training Evaluator', 'LSO III'],
        ['johnremon.gonzales@oshc.dole.gov.ph',    'HCD', 'Training Evaluator', 'John Remon',   'Gonzales',   'LSO III'],
        ['mritzie.leanda@oshc.dole.gov.ph',        'HCD', 'Training Evaluator', 'Mritzie',      'Leanda',     'LSO III'],
        ['beanicole.castro@oshc.dole.gov.ph',      'HCD', 'Training Evaluator', 'Bea Nicole',   'Castro',     'LSO III'],
        ['rhyankeith.melgarejo@oshc.dole.gov.ph',  'HCD', 'Training Evaluator', 'Rhyan Keith',  'Melgarejo',  'LSO III'],

        // ── Accreditation: Verifiers ──────────────────────────────────────────
        ['oshc1987accreditation@gmail.com',        'Accreditation', 'Verifier', 'Accreditation', 'Verifier',  'LSO VI'],
        ['kevin.goto@oshc.dole.gov.ph',            'Accreditation', 'Verifier', 'Kevin',        'Goto',       'LSO VI'],
    ];

    public function run(): void
    {
        // Seed Divisions FIRST
        foreach (['HCD', 'SCD', 'ECD', 'TPID', 'Accreditation'] as $divisionName) {
            Division::firstOrCreate(['name' => $divisionName]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'Admin']);

        foreach (['Evaluator', 'Verifier', 'Team Lead', 'Training Evaluator'] as $roleName) {
            AdminRole::firstOrCreate(['name' => $roleName]);
        }

        $divisions  = Division::pluck('id', 'name');
        $adminRoles = AdminRole::pluck('id', 'name');

        foreach (self::ADMINS as [$email, $division, $role, $firstName, $lastName, $position]) {
            $user = $this->seedAdmin(
                ['email' => $email],
                [
                    'role_id'           => $adminRole->id,
                    'profile_type'      => 'Individual',
                    'user_photo'        => 'images/profile_picture/default_photo.jpg',
                    'email_verified_at' => now(),
                ]
            );

            AdminProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'division_id'   => $divisions[$division],
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'position'      => $position,
                    'admin_role_id' => $adminRoles[$role],
                ]
            );
        }
    }

    /**
     * Create or refresh one seeded admin account.
     *
     * The password is set ONLY when the account is first created. Re-running
     * the seeder on a live system (php artisan db:seed) must never put back a
     * password an admin has since changed.
     *
     * The initial password comes from SEED_ADMIN_PASSWORD in .env, so the real
     * one need not live in the repository. Without it the old default is used,
     * which keeps a fresh install loggable-into — change it after first login.
     */
    private function seedAdmin(array $match, array $attributes): User
    {
        $user = User::firstOrNew($match);
        $user->fill($attributes);

        if (!$user->exists) {
            $user->password = Hash::make(config('app.seed_admin_password') ?: 'Hcd@2026');
        }

        $user->save();

        return $user;
    }
}
