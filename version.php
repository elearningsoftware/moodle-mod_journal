<?php

// Only when master becomes a stable the version value will be changed for
// the current date. Otherwise we just increase the last NN by one.

$plugin->component = 'mod_journal';
$plugin->version  = 2015120400;
$plugin->requires = 2015111600;  // Moodle 3.0
$plugin->release = '30.0 (Build: 2015120400)';
$plugin->maturity = MATURITY_STABLE;
$plugin->cron     = 60;
