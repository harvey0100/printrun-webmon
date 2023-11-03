<?php
/*
 * This is an KISS (Keep It Simple and Stupid) web monitor for
 * printrun / pronterface written in PHP.
 * Copyright (C) 2016  Jaroslav Škarvada <jskarvad@redhat.com>
 * The code is copied under GPLv3+. Use it on your own risk.
 */

// RPC defaults
$host = "localhost";
$path = "/";
$def_port = 7978;

$delay = 2; // how long to cache cameras output before new shot is captured
$cache_delay = 2; // how long to cache in browser in seconds before refresh is needed
$video_dev_glob = "/dev/video*";
//$video_dev_blacklist = array("/dev/video0", "/dev/video1");
$video_dev_blacklist = array();
$page_title = "3D printer status";
// resolution of images
$img_res_preview = "320x240";
$img_res_full = "1280x720";
$rulesets = [
  [
    'match_rules' => [
      ["ID_V4L_CAPABILITIES", ":"],
    ],
    'action' => 'ignore'
  ],
  [
    'match_rules' => [
      ["ID_V4L_CAPABILITIES", ":capture:"],
      ["ID_MODEL_ID", "704d"],
      ["ID_VENDOR_ID", "0458"],
    ],
    'fswebcam_args' => [
      "--s", "45"
    ],
  ]
];


// safety stop when searching for pronterfaces, if anything goes bad
// do not probe for more than NUM pronterfaces, under normal conditions
// it stops probing earlier
$printers_max = 100;
$status_cache = "data/status-cache";
$v4l_lock = "/tmp/printrun-webmon-v4l.lock";
$status_cache_lock = "/tmp/printrun-webmon-status-cache.lock";

// project home page for self-advertising :) empty string to disable
$homepage = "https://github.com/yarda/printrun-webmon";
