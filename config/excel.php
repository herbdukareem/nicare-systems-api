<?php

$defaults = require base_path('vendor/maatwebsite/excel/config/excel.php');

$defaults['exports']['chunk_size'] = (int) env('EXCEL_EXPORT_CHUNK_SIZE', 1000);
$defaults['cache']['driver'] = env('EXCEL_CACHE_DRIVER', 'batch');
$defaults['cache']['batch']['memory_limit'] = (int) env('EXCEL_CACHE_BATCH_MEMORY_LIMIT', 20000);
$defaults['cache']['illuminate']['store'] = env('EXCEL_CACHE_STORE');

return $defaults;
