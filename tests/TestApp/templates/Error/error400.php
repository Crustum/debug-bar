<?php
declare(strict_types=1);

/**
 * @var \Throwable $error
 */
echo $error::class, ': ', $error->getMessage(), ' in ', $error->getFile(), ':', $error->getLine();
