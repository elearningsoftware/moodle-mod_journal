<?php

// The version change from now will just be in
// the last NN increasing it one by one. All lower
// major versions version values will be lower
// than this. Only when master becomes a stable
// version the version value will be changed for
// time() date.
$plugin->component = 'mod_journal';
$plugin->version  = 2015052700;
$plugin->requires = 2015050500;  // Moodle 2.9
$plugin->release = '29.0 (Build: 2015052700)';
$plugin->maturity = MATURITY_STABLE;
$plugin->cron     = 60;
