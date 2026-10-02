<?php
/**
 * Role checks and branch isolation (includes/rbac.php).
 *
 * rbac.php is normally pulled in by includes/auth.php, which redirects
 * when there is no session. Here it is required directly and $_SESSION
 * is set by hand, so the decision logic can be exercised without a web
 * request.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/rbac.php';

/** @var TestRunner $t */

/** Pretend to be a logged-in account. */
$as = static function (string $role, ?int $branch = null): void {
    $_SESSION['role'] = $role;
    $_SESSION['branch_id'] = $branch;
};

$t->group('role identification');

$as('superadmin');
$t->is('role is reported', current_role(), 'superadmin');
$t->true('superadmin recognised', is_superadmin());
$t->false('superadmin is not a manager', is_manager());

$as('manager', 2);
$t->true('manager recognised', is_manager());
$t->false('manager is not superadmin', is_superadmin());

$as('support', 3);
$t->true('support recognised', is_support());

$_SESSION = [];
$t->is('missing session means no role', current_role(), '');
$t->false('no role is not superadmin', is_superadmin());

$t->group('role hierarchy');

$as('superadmin');
$t->true('superadmin satisfies support', role_at_least('support'));
$t->true('superadmin satisfies manager', role_at_least('manager'));
$t->true('superadmin satisfies superadmin', role_at_least('superadmin'));

$as('manager', 1);
$t->true('manager satisfies support', role_at_least('support'));
$t->false('manager does not satisfy superadmin', role_at_least('superadmin'));

$as('support', 1);
$t->false('support does not satisfy manager', role_at_least('manager'));
$t->true('support satisfies support', role_at_least('support'));

// An unknown role must not accidentally outrank anything. This is the
// bug the old isBranchAdmin()/isStaff() helpers hid: they tested for
// role names this application never stores.
$as('branchadmin', 1);
$t->false('an unknown role satisfies nothing', role_at_least('support'));
$t->is('an unknown role ranks zero', role_rank('branchadmin'), 0);
$t->is('an unknown role ranks below support', role_rank('support') > role_rank('nonsense'), true);

$t->group('branch scoping');

$as('superadmin');
$t->is('superadmin is unscoped', current_branch_id(), null);
$t->is('superadmin gets an empty fragment', branch_scope(), ['', []]);
$t->is('superadmin gets an empty fragment with an alias', branch_scope('c'), ['', []]);

$as('manager', 7);
$t->is('manager is pinned to its branch', current_branch_id(), 7);
$t->is('unaliased fragment', branch_scope(), [' AND branch_id = ?', [7]]);
$t->is('aliased fragment', branch_scope('c'), [' AND c.branch_id = ?', [7]]);
$t->is('custom column', branch_scope('t', 'owner_branch'), [' AND t.owner_branch = ?', [7]]);

// The fragment is spliced into SQL, so the branch must always travel as
// a bound parameter and never be inlined.
[$sql, $params] = branch_scope('c');
$t->lacks('fragment contains no literal value', $sql, '7');
$t->is('fragment uses a placeholder', substr_count($sql, '?'), 1);
$t->is('parameter carries the branch', $params, [7]);

$t->group('row ownership');

$as('manager', 7);
$t->true('owns a row from its branch', branch_owns(['branch_id' => 7]));
$t->true('string branch id still matches', branch_owns(['branch_id' => '7']));
$t->false('rejects another branch', branch_owns(['branch_id' => 8]));
$t->false('rejects a row with no branch column', branch_owns(['id' => 1]));
$t->false('rejects a non-array', branch_owns(null));
$t->true('honours a custom column', branch_owns(['owner_branch' => 7], 'owner_branch'));

$as('superadmin');
$t->true('superadmin owns any branch', branch_owns(['branch_id' => 99]));
$t->true('superadmin owns a row with no branch', branch_owns(['id' => 1]));

$_SESSION = [];
