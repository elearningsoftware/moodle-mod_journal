<?php

// The version change from now will just be in
// the last NN increasing it one by one. All lower
// major versions version values will be lower
// than this. Only when master becomes a stable
// version the version value will be changed for
// time() date.

// In 2.7 versions we jumped to 2015011210 because
// 2.6 was also using 20150112NN.

$plugin->component = 'mod_journal';
$plugin->version  = 2015011210;
$plugin->requires = 2014050800;  // Moodle 2.7
$plugin->release = '1.27.10 (Build: 2015011210)';
$plugin->maturity = MATURITY_STABLE;
$plugin->cron     = 60;

