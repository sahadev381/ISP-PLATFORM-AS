<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}

if ($action == 'list') {
    echo json_encode(db_all($conn, "
        SELECT l.*, r.name as route_name
        FROM wire_leases l
        JOIN fiber_routes r ON l.route_id = r.id
        ORDER BY l.created_at DESC
    "));
}

if ($action == 'add') {
    $route_id = (int) ($_POST['route_id'] ?? 0);
    $client = (string) ($_POST['client_name'] ?? '');
    $core = (int) ($_POST['core_number'] ?? 0);
    $start = (string) ($_POST['lease_start'] ?? '');
    $price = (float) ($_POST['monthly_price'] ?? 0);

    if ($core < 1) {
        die(json_encode(['status' => 'error', 'message' => 'Invalid core number']));
    }

    // Check total cores first so a missing route is reported properly — the
    // old code dereferenced the row without checking it existed.
    $r = db_one($conn, "SELECT total_cores, used_cores FROM fiber_routes WHERE id = ?", [$route_id]);
    if (!$r) {
        die(json_encode(['status' => 'error', 'message' => 'Route not found']));
    }
    if ($core > (int) $r['total_cores']) {
        die(json_encode(['status' => 'error', 'message' => 'Core number exceeds route capacity!']));
    }

    // The check-then-insert below is a race: two concurrent requests could both
    // pass the check and lease the same core. Wrap it in a transaction and lock
    // the route row so the second request waits.
    $conn->begin_transaction();
    try {
        db_one($conn, "SELECT id FROM fiber_routes WHERE id = ? FOR UPDATE", [$route_id]);

        $taken = db_value($conn, "SELECT COUNT(*) FROM wire_leases WHERE route_id = ? AND core_number = ? AND status = 'Active'", [$route_id, $core], 0);
        if ($taken > 0) {
            $conn->rollback();
            die(json_encode(['status' => 'error', 'message' => 'Core already leased!']));
        }

        db_exec($conn, "INSERT INTO wire_leases (route_id, client_name, core_number, lease_start, monthly_price) VALUES (?, ?, ?, ?, ?)",
            [$route_id, $client, $core, $start, $price]);
        db_exec($conn, "UPDATE fiber_routes SET used_cores = used_cores + 1 WHERE id = ?", [$route_id]);

        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('wire_lease add failed: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Could not create lease']);
    }
}

if ($action == 'terminate') {
    $id = (int) ($_POST['id'] ?? 0);
    $l = db_one($conn, "SELECT route_id, status FROM wire_leases WHERE id = ?", [$id]);

    if (!$l) {
        die(json_encode(['status' => 'error', 'message' => 'Lease not found']));
    }

    // Terminating an already-terminated lease used to decrement used_cores
    // again, so repeated calls drove the route's core count down to zero.
    if ($l['status'] === 'Terminated') {
        die(json_encode(['status' => 'error', 'message' => 'Lease is already terminated']));
    }

    $conn->begin_transaction();
    try {
        db_exec($conn, "UPDATE wire_leases SET status = 'Terminated', lease_end = NOW() WHERE id = ? AND status <> 'Terminated'", [$id]);
        db_exec($conn, "UPDATE fiber_routes SET used_cores = GREATEST(used_cores - 1, 0) WHERE id = ?", [(int) $l['route_id']]);
        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('wire_lease terminate failed: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Could not terminate lease']);
    }
}
?>
