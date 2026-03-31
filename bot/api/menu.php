<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../db/MenuRepo.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode((new MenuRepo())->getCategoriesWithItems());
