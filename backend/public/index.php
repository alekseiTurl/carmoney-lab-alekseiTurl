<?php

declare(strict_types=1);

/**
 * Точка входа HTTP-приложения: подключаем автозагрузчик Composer
 * и передаём управление собранному в AppFactory Slim-приложению.
 */

use CarMoneyLab\AppFactory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

AppFactory::create()->run();
