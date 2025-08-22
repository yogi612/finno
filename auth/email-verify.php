<?php
// Email verification is no longer required. Redirect to login or dashboard.
header('Location: /login');
exit;