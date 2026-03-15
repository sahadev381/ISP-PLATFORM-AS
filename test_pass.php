<?php
$hash = '$2y$10$4VtDBsjDatGNY3LcYdqse.PO1tifm8Hj7qi8r6QYQZplmI60sTAv6';
$password = 'radiuspass';

if (password_verify($password, $hash)) {
    echo "Password is correct!";
} else {
    echo "Password is incorrect.";
}
?>