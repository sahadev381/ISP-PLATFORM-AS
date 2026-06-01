<?php
define('KHALTI_PUBLIC_KEY', getenv('KHALTI_PUBLIC_KEY') ?: '');
define('KHALTI_SECRET_KEY', getenv('KHALTI_SECRET_KEY') ?: '');
define('KHALTI_VERIFY_URL', getenv('KHALTI_VERIFY_URL') ?: 'https://khalti.com/api/v2/payment/verify/');
