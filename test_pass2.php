<?php
$hash = '$2y$10$4VtDBsjDatGNY3LcYdqse.PO1tifm8Hj7qi8r6QYQZplmI60sTAv6';
$passwords = ['radiuspass', 'password', 'admin', '123456', 'radius'];

foreach ($passwords as $pwd) {
    if (password_verify($pwd, $hash)) {
        echo "Password is correct: $pwd\n";
    } else {
        echo "Password is incorrect: $pwd\n";
    }
}
?>