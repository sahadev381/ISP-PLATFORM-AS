<?php
/**
 * Second customer login door - removed.
 *
 * customer/index.php does the same job and does it better: it always
 * runs password_verify() against a dummy hash when the account does
 * not exist, so the response time does not reveal whether a username
 * is registered. This copy returned early instead, which made customer
 * accounts enumerable by timing.
 *
 * Both set the same session keys and both ended at dashboard.php, so
 * the two were interchangeable apart from that weakness. Links to this
 * path (customer/logout.php, customer/register.php) still work.
 */

header('Location: index.php', true, 301);
exit;
