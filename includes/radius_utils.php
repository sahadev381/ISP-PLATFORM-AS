<?php

/**
 * Failure reason detector for RADIUS authentication
 *
 * @param string $reply The RADIUS server reply (e.g., Access-Accept, Access-Reject)
 * @param string|null $pass The password provided during authentication
 * @return string A human-readable reason for the result
 */
function radius_reason($reply, $pass) {
    if ($reply === 'Access-Accept') {
        return 'Login successful';
    }

    if ($reply === 'Access-Reject') {
        if ($pass === '' || $pass === null) {
            return 'Password not provided';
        }
        return 'Authentication rejected (wrong password / MAC lock )';
    }

    return 'Unknown result';
}
