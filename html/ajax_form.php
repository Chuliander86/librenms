<?php

/*
 * LibreNMS
 *
 * Copyright (c) 2014 Neil Lathwood <https://github.com/laf/ http://www.lathwood.co.uk>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

// FUA

use LibreNMS\Authentication\LegacyAuth;

$init_modules = array('web', 'auth', 'alerts', 'laravel');
require realpath(__DIR__ . '/..') . '/includes/init.php';

set_debug(isset($_REQUEST['debug']) ? $_REQUEST['debug'] : false);

if (!LegacyAuth::check()) {
    echo 'unauthenticated';
    exit;
}

if (preg_match('/^[a-zA-Z0-9\-]+$/', $_POST['type']) == 1) {
    if (file_exists('includes/forms/'.$_POST['type'].'.inc.php')) {
        include_once 'includes/forms/'.$_POST['type'].'.inc.php';
    }
}
