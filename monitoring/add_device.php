<?php
require 'db.php';

if ($_POST) {
    $stmt = $mysqli->prepare(
        "INSERT INTO devices (name, ip_address, type) VALUES (?,?,?)"
    );
    $stmt->bind_param("sss", $_POST['name'], $_POST['address'], $_POST['type']);
    $stmt->execute();
    header("Location: dashboard.php");
}
?>

<form method="post">
Name:<br><input name="name"><br>
Address:<br><input name="address"><br>
Type:<br>
<select name="type">
<option value="ping">Ping</option>
<option value="http">HTTP</option>
</select><br><br>
<button>Add Device</button>
</form>

