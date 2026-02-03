<?php
$line = "025828  1   0000000002  sagar khatiwada                     7   0   2025/11/23  10:06:14";
$data = preg_split('/\t+|\s{2,}/', trim($line));
var_export($data);
