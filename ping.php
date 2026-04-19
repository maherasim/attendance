<?php
/** No includes — if this URL fails, PHP or the server path is wrong. */
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
echo 'OK PHP ' . PHP_VERSION . "\n";
