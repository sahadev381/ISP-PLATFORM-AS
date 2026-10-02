<?php
/**
 * Second admin login door - removed.
 *
 * This file was a parallel copy of the login in index.php that
 * authenticated against the same `admins` table but had none of the
 * brute-force protection: no isLockedOut() check, no
 * recordLoginAttempt(), no activity log. An attacker who hit the
 * 5-attempt lockout on index.php could simply carry on guessing here,
 * so the lockout protected nothing as long as this file existed.
 *
 * Nothing in the codebase linked to it. It is kept as a redirect only
 * so existing bookmarks still land somewhere sensible.
 */

header('Location: index.php', true, 301);
exit;
