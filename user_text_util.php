<?php

spl_autoload_register(function ($class) {
    include "./classes/{$class}.php";
});

$workWithText = new UserTextUtil($argv[1]);