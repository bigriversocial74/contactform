<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/microgifts/_stage10e_operations.php';
if(PHP_SAPI!=='cli')exit(1);
$result=mg_run_retention(mg_db(),(int)($argv[1]??365),(int)($argv[2]??30),(int)($argv[3]??90));
echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
