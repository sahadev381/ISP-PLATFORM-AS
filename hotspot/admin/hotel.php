<?php
session_start();
$page_title = "Hotel Management";
$page = 'hotel';
$base_path = '.';

chdir(__DIR__ . '/../..');
include_once 'config.php';
include_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';

// Handle hotel actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add/Update hotel
    if ($action == 'save_hotel') {
        $hotelId = (int)($_POST['hotel_id'] ?? 0);
        $name = $conn->real_escape_string($_POST['name']);
        $code = $conn->real_escape_string($_POST['code']);
        $address = $conn->real_escape_string($_POST['address']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $email = $conn->real_escape_string($_POST['email']);
        $contact = $conn->real_escape_string($_POST['contact_person']);
        $checkoutTime = $_POST['checkout_time'];
        $gracePeriod = (int)$_POST['grace_period'];
        
        if ($hotelId > 0) {
            $conn->query("UPDATE hotspot_hotels SET 
                name='$name', code='$code', address='$address', phone='$phone', 
                email='$email', contact_person='$contact', checkout_time='$checkoutTime', 
                grace_period_mins=$gracePeriod WHERE id=$hotelId");
            $message = "Hotel updated!";
        } else {
            $conn->query("INSERT INTO hotspot_hotels (name, code, address, phone, email, contact_person, checkout_time, grace_period_mins) 
                VALUES ('$name', '$code', '$address', '$phone', '$email', '$contact', '$checkoutTime', $gracePeriod)");
            $message = "Hotel created!";
        }
    }
    
    // Add room
    if ($action == 'add_room') {
        $hotelId = (int)$_POST['hotel_id'];
        $roomNumber = $conn->real_escape_string($_POST['room_number']);
        $floor = $conn->real_escape_string($_POST['floor']);
        $macAddress = $conn->real_escape_string($_POST['mac_address']);
        
        $conn->query("INSERT INTO hotspot_rooms (hotel_id, room_number, floor, mac_address) 
            VALUES ($hotelId, '$roomNumber', '$floor', '$macAddress')");
        $message = "Room added!";
    }
    
    // Check-in guest
    if ($action == 'checkin') {
        $roomId = (int)$_POST['room_id'];
        $guestName = $conn->real_escape_string($_POST['guest_name']);
        $guestPhone = $conn->real_escape_string($_POST['guest_phone']);
        $guestId = $conn->real_escape_string($_POST['guest_id_proof']);
        $planId = (int)$_POST['plan_id'];
        
        $conn->query("UPDATE hotspot_rooms SET 
            status='occupied', guest_name='$guestName', guest_phone='$guestPhone', 
            guest_id_proof='$guestId', plan_id=$planId, checkin_time=NOW() 
            WHERE id=$roomId");
        $message = "Guest checked in!";
    }
    
    // Check-out guest
    if ($action == 'checkout' && isset($_POST['room_id'])) {
        $roomId = (int)$_POST['room_id'];
        $conn->query("UPDATE hotspot_rooms SET 
            status='available', guest_name=NULL, guest_phone=NULL, guest_id_proof=NULL, 
            checkin_time=NULL, checkout_time=NOW() WHERE id=$roomId");
        $message = "Guest checked out!";
    }
    
    // Delete hotel
    if ($action == 'delete_hotel' && isset($_POST['hotel_id'])) {
        $hotelId = (int)$_POST['hotel_id'];
        $conn->query("DELETE FROM hotspot_rooms WHERE hotel_id=$hotelId");
        $conn->query("DELETE FROM hotspot_hotels WHERE id=$hotelId");
        $message = "Hotel deleted";
    }
}

// Get hotels
$hotels = $conn->query("SELECT * FROM hotspot_hotels WHERE status='active'");

// Get plans for dropdown
$plans = $conn->query("SELECT * FROM hotspot_profiles WHERE status='active'");

include 'includes/header_hotspot.php';
?>

<div class="container-fluid p-4">
    <div style="margin-bottom: 25px;">
        <h2 style="margin: 0; color: #1e293b; font-weight: 700;">
            <i class="fa fa-hotel" style="color: var(--primary);"></i> Hotel Solution
        </h2>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $message ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#hotels">Hotels</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rooms">Room Status</button></li>
    </ul>

    <div class="tab-content">
        <!-- Hotels Tab -->
        <div class="tab-pane fade show active" id="hotels">
            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#hotelModal">
                <i class="fas fa-plus"></i> Add Hotel
            </button>

            <div class="row">
                <?php while ($hotel = $hotels->fetch_assoc()): 
                    $roomStats = $conn->query("
                        SELECT status, COUNT(*) as cnt FROM hotspot_rooms 
                        WHERE hotel_id = {$hotel['id']} GROUP BY status
                    ")->fetch_all(MYSQLI_ASSOC);
                    $occupied = 0; $available = 0;
                    foreach ($roomStats as $s) {
                        if ($s['status'] == 'occupied') $occupied = $s['cnt'];
                        if ($s['status'] == 'available') $available = $s['cnt'];
                    }
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?= htmlspecialchars($hotel['name']) ?></h5>
                            <span class="badge bg-primary"><?= $hotel['code'] ?></span>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($hotel['address'] ?? 'N/A') ?></p>
                            <p><i class="fas fa-phone"></i> <?= $hotel['phone'] ?? 'N/A' ?></p>
                            <div class="row text-center mt-3">
                                <div class="col-6">
                                    <h4 class="text-success"><?= $available ?></h4>
                                    <small>Available</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-danger"><?= $occupied ?></h4>
                                    <small>Occupied</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal" 
                                data-hotel-id="<?= $hotel['id'] ?>" data-hotel-name="<?= $hotel['name'] ?>">
                                <i class="fas fa-door-open"></i> Manage Rooms
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Rooms Tab -->
        <div class="tab-pane fade" id="rooms">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bed"></i> All Rooms</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Hotel</th>
                                <th>Room</th>
                                <th>Floor</th>
                                <th>MAC</th>
                                <th>Guest</th>
                                <th>Phone</th>
                                <th>Check-in</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $allRooms = $conn->query("
                                SELECT r.*, h.name as hotel_name 
                                FROM hotspot_rooms r
                                JOIN hotspot_hotels h ON r.hotel_id = h.id
                                ORDER BY h.name, r.room_number
                            ");
                            while ($room = $allRooms->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?= $room['hotel_name'] ?></td>
                                <td><?= $room['room_number'] ?></td>
                                <td><?= $room['floor'] ?? '-' ?></td>
                                <td><code><?= $room['mac_address'] ?? '-' ?></code></td>
                                <td><?= $room['guest_name'] ?? '-' ?></td>
                                <td><?= $room['guest_phone'] ?? '-' ?></td>
                                <td><?= $room['checkin_time'] ? date('M d, H:i', strtotime($room['checkin_time'])) : '-' ?></td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $room['status'] == 'occupied' ? 'danger' : 
                                        ($room['status'] == 'available' ? 'success' : 'warning')
                                    ?>">
                                        <?= $room['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($room['status'] == 'available'): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#checkinModal"
                                            data-room-id="<?= $room['id'] ?>" data-room-number="<?= $room['room_number'] ?>">
                                            <i class="fas fa-sign-in-alt"></i> Check-in
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="checkout">
                                            <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <i class="fas fa-sign-out-alt"></i> Check-out
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hotel Modal -->
<div class="modal fade" id="hotelModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_hotel">
                <input type="hidden" name="hotel_id" id="hotel_id">
                <div class="modal-header">
                    <h5>Hotel Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Hotel Name</label>
                        <input type="text" name="name" id="hotel_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Code</label>
                        <input type="text" name="code" id="hotel_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Address</label>
                        <textarea name="address" id="hotel_address" class="form-control"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Phone</label>
                                <input type="text" name="phone" id="hotel_phone" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" id="hotel_email" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Checkout Time</label>
                                <input type="time" name="checkout_time" class="form-control" value="12:00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Grace Period (mins)</label>
                                <input type="number" name="grace_period" class="form-control" value="30">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Hotel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Room Modal -->
<div class="modal fade" id="roomModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_room">
                <input type="hidden" name="hotel_id" id="room_hotel_id">
                <div class="modal-header">
                    <h5>Add Room to <span id="room_hotel_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Room Number</label>
                        <input type="text" name="room_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Floor</label>
                        <input type="text" name="floor" class="form-control" placeholder="e.g., 1, 2, 3...">
                    </div>
                    <div class="mb-3">
                        <label>MAC Address (Optional)</label>
                        <input type="text" name="mac_address" class="form-control" placeholder="XX:XX:XX:XX:XX:XX">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Check-in Modal -->
<div class="modal fade" id="checkinModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="checkin">
                <input type="hidden" name="room_id" id="checkin_room_id">
                <div class="modal-header">
                    <h5>Check-in Room <span id="checkin_room_number"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Guest Name</label>
                        <input type="text" name="guest_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Phone Number</label>
                        <input type="text" name="guest_phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>ID Proof</label>
                        <input type="text" name="guest_id_proof" class="form-control" placeholder="Passport/ID Number">
                    </div>
                    <div class="mb-3">
                        <label>Plan</label>
                        <select name="plan_id" class="form-select">
                            <?php while ($plan = $plans->fetch_assoc()): ?>
                                <option value="<?= $plan['id'] ?>"><?= $plan['name'] ?> - Rs.<?= $plan['price'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Check-in Guest</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<script>
document.getElementById('roomModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('room_hotel_id').value = btn.getAttribute('data-hotel-id');
    document.getElementById('room_hotel_name').textContent = btn.getAttribute('data-hotel-name');
});

document.getElementById('checkinModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('checkin_room_id').value = btn.getAttribute('data-room-id');
    document.getElementById('checkin_room_number').textContent = btn.getAttribute('data-room-number');
});
</script>