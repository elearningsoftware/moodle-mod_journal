<?php

// The version change from now will just be in
// the last NN increasing it one by one. All lower
// major versions version values will be lower
// than this. Only when master becomes a stable
// version the version value will be changed for
// time() date.
$plugin->version  = 2015011300;
$plugin->requires = 2014111000;  // Moodle 2.8
$plugin->release = '28.1 (Build: 2015011300)';
$plugin->maturity = MATURITY_STABLE;
$plugin->cron     = 60;
